<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyUserOfCompletedExport;
use App\Models\Item;
use Illuminate\Http\Request;
use App\Exports\ItemsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;


class ItemController extends Controller
{
    public function index()
    {
        $items = Item::orderBy('updated_at', 'asc')->get();
        return response()->json($items);
    }

    public function export()
    {
        // Oldindan mavjud faylni o'chirish
        $filePath = storage_path('app/public/materiallar.xlsx');
        if (file_exists($filePath)) {
            unlink($filePath);  // Faylni o'chirish
        }

        // Faylni queue orqali eksport qilish
        Excel::queue(new ItemsExport, 'public/materiallar.xlsx')->chain([
            new NotifyUserOfCompletedExport(auth()->user())
        ]);

        // Fayl URLini olish
        $fileUrl = url('storage/materiallar.xlsx');  // public diskda materiallar.xlsx fayl URLini olish

        // Javob yuborish
        return response()->json([
            'message' => 'Eksport jarayoni navbatga yuborildi.',
            'fileUrl' => $fileUrl,  // Fayl URLini yuborish
        ]);
    }







    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'price' => 'required',
            'unit_id' => 'required|exists:units,id',
            'color_id' => 'required|exists:colors,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Tasvir validatsiyasi
            'type_id' => 'required|exists:item_types,id',
        ]);
        $request->validate([
            'code' => 'unique:items,code',
        ], [
            'code.unique' => 'Code must be unique',
        ]);

        $imagePath = null;

        // Faylni saqlash

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            // Rasmni olish
            $image = $request->file('image');

            // Unikal nom yaratish
            $imageName = uniqid() . '.' . $image->getClientOriginalExtension();

            // Faylni saqlash (public disk)
            $imagePath = $image->storeAs('public/images', $imageName);

            // URL olish (agar kerak bo'lsa)
            $imageUrl = Storage::url($imagePath);

            $imagePath = str_replace('public/', '', $imagePath);
        } else {
            // Fayl topilmagan yoki noto'g'ri fayl
            // Xato qaytarish
            return response()->json(['error' => 'Image file is missing or invalid'], 400);
        }

//        $imageOriginalName = preg_split('/', $imageUrl)[2] ?? null;

        // Yangi yozuv yaratish
        $item = Item::create([
            'name' => $request->name,
            'price' => $request->price  ?? 0,
            'unit_id' => $request->unit_id,
            'color_id' => $request->color_id,
            'image' => $imagePath,
            'code' => $request->code ?? uniqid(),
            'type_id' => $request->type_id,
        ]);

        if ($item) {
            return response()->json([
                'message' => 'Item created successfully',
                'item' => $item,
            ]);
        } else {
            return response()->json([
                'message' => 'Item not created',
                'error' => $item->errors(),
            ]);
        }
    }

    public function update(Request $request, Item $item)
    {
        $item->update($request->all());
        return response()->json([
            'message' => 'Item updated successfully',
            'item' => $item,
        ]);
    }
}
