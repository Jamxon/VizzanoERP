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
            $branchId = auth()->user()->employee->branch_id;

            // MODEL — mavjud bo‘lsa olib kelamiz, bo‘lmasa yaratamiz
            $model = Models::where('name', $data['model'])
                ->where('branch_id', $branchId)
                ->first();

            if (!$model) {
                // minute va rasxod hisoblanadi (agar 0 bo‘lsa)
                $minute = $data['minute'] ?? 0;
                $rasxod = $data['model_summa'] ?? 0;

                if ($minute == 0 || $rasxod == 0) {
                    $minute = $minute ?: ($data['price'] / 0.065);
                    $rasxod = $rasxod ?: ($minute * 250);
                }

                $model = Models::create([
                    'name' => $data['model'],
                    'rasxod' => $rasxod,
                    'branch_id' => $branchId,
                    'minute' => $minute,
                ]);
            }

            // SUBMODEL — mavjud bo‘lsa olib kelamiz, bo‘lmasa yaratamiz
            $submodel = SubModel::where('name', $data['submodel'])
                ->where('model_id', $model->id)
                ->first();

            if (!$submodel) {
                $submodel = SubModel::create([
                    'name' => $data['submodel'],
                    'model_id' => $model->id,
                ]);
            }

            // ORDER
            $order = Order::create([
                'name' => $data['model'] . ' ' . $data['quantity'],
                'rasxod' => $data['rasxod'] ?? 0,
                'quantity' => $data['quantity'],
                'price' => $data['price'],
                'status' => 'inactive',
                'start_date' => now()->format('Y-m-d'),
                'end_date' => null,
                'branch_id' => $branchId,
                'contragent_id' => $data['contragent_id'] ?? null,
            ]);

            // ORDER MODEL — rasxod yoki minute 0 bo‘lsa modeldan oladi, bo‘lmasa hisoblaydi
            $orderMinute = $data['minute'] ?? 0;
            $orderRasxod = $data['model_summa'] ?? 0;

            if ($orderMinute == 0 || $orderRasxod == 0) {
                $orderMinute = $model->minute ?: ($data['price'] / 0.065);
                $orderRasxod = $model->rasxod ?: ($orderMinute * 250);
            }

            $orderModel = OrderModel::create([
                'order_id' => $order->id,
                'model_id' => $model->id,
                'material_id' => null,
                'status' => false,
                'rasxod' => $orderRasxod,
                'minute' => $orderMinute,
            ]);

            // ORDER SUBMODEL
            OrderSubModel::create([
                'order_model_id' => $orderModel->id,
                'submodel_id' => $submodel->id,
            ]);

            // ORDER SIZES
            foreach ($data['sizes'] as $size) {
                $sizeModel = Size::firstOrCreate(
                    ['name' => $size, 'model_id' => $model->id]
                );

                OrderSize::create([
                    'order_model_id' => $orderModel->id,
                    'size_id' => $sizeModel->id,
                    'quantity' => 0,
                ]);
            }

            // RASMLARNI SAQLASH
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
                        'image' => $newPath,
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
            try {
                if ($drawing instanceof \PhpOffice\PhpSpreadsheet\Worksheet\Drawing) {
                    $coordinates = $drawing->getCoordinates();
                    $imageExtension = $drawing->getExtension();
                    $imageName = Str::uuid() . '.' . $imageExtension;
                    $imagePath = "models/$imageName";

                    $drawingPath = $drawing->getPath();
                        Storage::disk('public')->put($imagePath, file_get_contents($drawingPath));

                        preg_match('/\d+/', $coordinates, $matches);
                        $rowNumber = $matches[0] ?? null;

                        if ($rowNumber) {
                            $modelImages[$rowNumber][] = url('storage/' . $imagePath);
                        }

                }
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => "Rasmlarni yuklashda xatolik: " . $e->getMessage()], 500);
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
                        'minute' => array_sum(array_column($currentBlock, 'minute')),
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
                    'model_summa' => $mValue,
                    'minute' => $iValue,
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
                'minute' => array_sum(array_column($currentBlock, 'minute')),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}