<?php

namespace App\Imports;

use App\Models\SpecificationCategory;
use App\Models\PartSpecification;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class SpecificationCategoryImport implements ToCollection
{
    protected $orderSubModelId;
    protected $currentCategoryId = null;
    protected $skipHeader = false; // Har bir kategoriya blokidagi ustun nomlari qatorini o'tkazish uchun

    /**
     * Agar kerak bo'lsa orderSubModel ID sini qabul qiladi.
     *
     * @param mixed $orderSubModelId
     */
    public function __construct($orderSubModelId = null)
    {
        $this->orderSubModelId = $orderSubModelId;
    }

    /**
     * Excel fayldan olingan qatorlar ustida ishlash.
     *
     * Strukturasi:
     * - Qator: Faqat bitta to'liq hujayra – kategoriya nomi.
     * - Qator: Header (code, name, quantity, comment) – o'tkazib yuboriladi.
     * - Qatorlar: Shu kategoriyaga tegishli specification maʼlumotlari.
     *
     * @param Collection $rows
     * @return void
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            // Bo'sh qatorlarni o'tkazamiz
            if (empty(array_filter($row->toArray(), function($value) {
                return !is_null($value) && trim($value) !== '';
            }))) {
                continue;
            }

            // Agar qatorda faqat bitta notekis hujayra bo'lsa – bu kategoriya nomi
            $nonEmptyCount = count(array_filter($row->toArray(), function ($value) {
                return !is_null($value) && trim($value) !== '';
            }));

            if ($nonEmptyCount === 1 && !empty($row[0])) {
                $categoryName = trim($row[0]);
                // Agar orderSubModelId berilgan bo'lsa, uni ham saqlaymiz
                $data = ['name' => $categoryName];
                if ($this->orderSubModelId) {
                    $data['submodel_id'] = $this->orderSubModelId;
                }
                $category = SpecificationCategory::create($data);
                $this->currentCategoryId = $category->id;
                $this->skipHeader = true; // Keyingi qator - ustun nomlari bo'ladi
                continue;
            }

            // Ustun nomlari qatorini o'tkazamiz
            if ($this->skipHeader) {
                $this->skipHeader = false;
                continue;
            }

            // Endi bu qator specification maʼlumotlari sifatida qabul qilinadi
            if ($this->currentCategoryId) {
                PartSpecification::create([
                    'specification_category_id' => $this->currentCategoryId,
                    'code'     => isset($row[0]) ? trim($row[0]) : null,
                    'name'     => isset($row[1]) ? trim($row[1]) : null,
                    'quantity' => isset($row[2]) ? trim($row[2]) : null,
                    'comment'  => isset($row[3]) ? trim($row[3]) : null,
                ]);
            }
        }
    }
}
