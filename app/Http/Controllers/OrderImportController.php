<?php

namespace App\Http\Controllers;

use App\Models\ModelImages;
use App\Models\Models;
use App\Models\Order;
use App\Models\OrderModel;
use App\Models\OrderSize;
use App\Models\OrderSubModel;
use App\Models\Size;
use App\Models\SubModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderImportController extends Controller
{
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = is_array($request->data) ? $request->data : json_decode($request->data, true);

            $issetModel = Models::where('name', $data['model'])
                ->where('branch_id', auth()->user()->employee->branch_id)
                ->first();

            if ($issetModel) {
                $model = $issetModel;
            }else{
                $model = Models::create([
                    'name' => $data['model'],
                    'rasxod' => $data['model_summa'],
                    'branch_id' => auth()->user()->employee->branch_id,
                ]);
            }

            $model = Models::where('name', $data['model'])
                ->where('branch_id', auth()->user()->employee->branch_id)
                ->first();

            if (!$model){
                return response()->json(['error' => 'Model topilmadi'], 404);
            }

            $issetSubModel = SubModel::where('name', $data['submodel'])
                ->where('model_id', $model->id)
                ->first();

            if ($issetSubModel) {
                $submodel = $issetSubModel;
            }else{
                $submodel = SubModel::create([
                    'name' => $data['submodel'],
                    'model_id' => $model->id,
                ]);
            }

            $submodel = SubModel::where('name', $data['submodel'])
                ->where('model_id', $model->id)
                ->first();

            if (!$submodel) {
                return response()->json(['error' => 'Submodel topilmadi'], 404);
            }

            $order = Order::create([
                'name' => $data['model'] . ' ' . $data['quantity'],
                'rasxod' => $data['rasxod'] ?? 0,
                'quantity' => $data['quantity'],
                'price' => $data['price'],
                'status' => 'inactive',
                'start_date' => now()->format('Y-m-d'),
                'end_date' => null,
                'branch_id' => auth()->user()->employee->branch_id,
                'contragent_id' => $data['contragent_id'] ?? null,
            ]);

            $orderModel = OrderModel::create([
                'order_id' => $order->id,
                'model_id' => $model->id,
                'material_id' => null,
                'status' => false,
                'rasxod' => $data['model_summa'],
            ]);

            $orderSubModel = OrderSubModel::create([
                'order_model_id' => $orderModel->id,
                'submodel_id' => $submodel->id,
            ]);

            foreach ($data['sizes'] as $size) {

                $sizeModel = Size::where('name', $size)
                    ->where('model_id', $model->id)
                    ->first();

                if (!$sizeModel) {
                    $sizeModel = Size::create([
                        'name' => $size,
                        'model_id' => $model->id,
                    ]);
                }

                $sizeModel = Size::where('name', $size)
                    ->where('model_id', $model->id)
                    ->first();

                OrderSize::create([
                    'order_model_id' => $orderModel->id,
                    'size_id' => $sizeModel->id,
                    'quantity' => 0,
                ]);
            }

            // Rasmlarni saqlash
            if (!empty($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $image) {
                    $sourcePath = storage_path('app/public/models/' . $image);

                    if (!file_exists($sourcePath)) {
                        throw new \Exception("Rasm topilmadi: $image");
                    }

                    $imageName = Str::uuid() . '.' . pathinfo($image, PATHINFO_EXTENSION);
                    $newPath = "models/$imageName";

                    Storage::disk('public')->put($newPath, file_get_contents($sourcePath));

                    ModelImages::create([
                        'model_id' => $model->id,
                        'name' => $newPath,
                    ]);
                }

            }

            DB::commit();

            return response()->json(['message' => 'Ma\'lumotlar muvaffaqiyatli saqlandi'], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Xatolik yuz berdi: ' . $e->getMessage()], 500);
        }
    }

    public function import(Request $request): \Illuminate\Http\JsonResponse
    {
        $file = $request->file('file');

        if (!$file || !$file->isValid()) {
            return response()->json(['success' => false, 'message' => "Fayl noto'g'ri yuklangan!"], 400);
        }

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => "Faylni o'qishda xatolik: " . $e->getMessage()], 500);
        }

        $highestRow = $sheet->getHighestRow();
        $data = [];
        $modelImages = [];
        $currentGroup = null;
        $currentSubModel = null;
        $currentSizes = [];
        $currentBlock = [];

        // Rasmlarni olish
        foreach ($sheet->getDrawingCollection() as $drawing) {
            if ($drawing instanceof \PhpOffice\PhpSpreadsheet\Worksheet\Drawing) {
                $coordinates = $drawing->getCoordinates();
                $imageExtension = $drawing->getExtension();
                $imageName = Str::uuid() . '.' . $imageExtension;
                $imagePath = "models/$imageName";

                Storage::disk('public')->put($imagePath, file_get_contents($drawing->getPath()));

                preg_match('/\d+/', $coordinates, $matches);
                $rowNumber = $matches[0] ?? null;

                if ($rowNumber) {
                    $modelImages[$rowNumber][] = url('storage/' . $imagePath);
                }
            }
        }

        for ($row = 2; $row <= $highestRow; $row++) {
            $aValue = trim((string)$sheet->getCell("A$row")->getValue());
            $dValue = trim((string)$sheet->getCell("D$row")->getValue());
            $eValue = trim((string)$sheet->getCell("E$row")->getValue());
            $fValue = (float)$sheet->getCell("F$row")->getValue();
            $gValue = (float)$sheet->getCell("G$row")->getValue();
            $hValue = (float)$sheet->getCell("H$row")->getValue();
            $iValue = (float)$sheet->getCell("I$row")->getValue();
            $jValue = (float)$sheet->getCell("J$row")->getValue();
            $mValue = (float)$sheet->getCell("M$row")->getCalculatedValue();

            // Agar yangi model guruhiga o'tilsa, avvalgi blokni saqlash
            if ($eValue && $eValue !== $currentGroup) {
                if (!empty($currentBlock)) {
                    $nonZeroItem = collect($currentBlock)->firstWhere(function ($item) {
                        return $item['quantity'] > 0;
                    });

                    $data[] = [
                        'model' => $currentGroup,
                        'submodel' => $currentSubModel,
                        'price' => $nonZeroItem['price'] ?? 0,
                        'quantity' => array_sum(array_column($currentBlock, 'quantity')),
                        'sizes' => array_values(array_unique($currentSizes)),
                        'model_summa' => array_sum(array_column($currentBlock, 'model_summa')),
                        'images' => $modelImages[$row] ?? [],
                    ];
                }

                // Yangi model uchun qiymatlarni tiklash
                $currentGroup = $eValue;
                $currentSubModel = $dValue;
                $currentBlock = [];
                $currentSizes = [];
            }

            // O'lchamlarni yig'ish (E guruhi uchun)
            if ($currentGroup && (
                    preg_match('/^\d{2,3}(?:\/\d{2,3})?$/', $aValue) ||
                    preg_match('/^\d{2,3}-\d{2,3}$/', $aValue)
                ) && $aValue !== '') {
                $currentSizes[] = $aValue;
            }

            // Qatorlarni yig'ish
            if ($fValue > 0 && $gValue > 0) {
                $currentBlock[] = [
                    'size' => $aValue,
                    'price' => $fValue,
                    'quantity' => $gValue,
                    'model_summa' => $mValue
                ];
            }
        }

        // **Oxirgi blokni qo'shish**
        if (!empty($currentBlock)) {
            $nonZeroItem = collect($currentBlock)->firstWhere(function ($item) {
                return $item['quantity'] > 0;
            });

            $data[] = [
                'model' => $currentGroup,
                'submodel' => $currentSubModel,
                'price' => $nonZeroItem['price'] ?? 0,
                'quantity' => array_sum(array_column($currentBlock, 'quantity')),
                'sizes' => array_values(array_unique($currentSizes)),
                'model_summa' => array_sum(array_column($currentBlock, 'model_summa')),
                'images' => $modelImages[$highestRow] ?? [],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}