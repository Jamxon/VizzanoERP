<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GroupsOrdersEarningsExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        $rows = [];
        $i = 1;

        foreach ($this->data as $group) {
            foreach ($group['employees'] as $emp) {
                // payment_type ni tarjima qilish
                $paymentType = match ($emp['payment_type']) {
                    'piece_work' => 'Ishbay',
                    'salary'     => 'Oylik',
                    default      => ucfirst($emp['payment_type'] ?? 'Oylik'),
                };

                $rows[] = [
                    $i++,
                    $emp['name'],
                    $emp['attendance_days'],
                    $paymentType,
                    $emp['attendance_salary'],   // oylik
                    $emp['tarification_salary'], // ishbay
                    $emp['total_earned'],        // umumiy topgan puli
                    $emp['total_paid'],          // avans
                    $emp['net_balance'],         // qolgan summa
                    '',                          // imzo uchun bo‘sh joy
                ];
            }
        }

        return $rows;
    }


    public function headings(): array
    {
        return [
            'No',
            'FIO',
            'Ish kunlari',
            "To'lov turi",
            "Ish haqi (oylik)",
            "Ish haqi (ishbay)",
            "Topgan puli",
            "Avans",
            "Qolgan summa",
            "Imzo",
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]], // header bold
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 25,  // FIO
            'C' => 12,  // Ish kunlari
            'D' => 15,  // To'lov turi
            'E' => 20,  // Ish haqi (oylik)
            'F' => 20,  // Ish haqi (ishbay)
            'G' => 20,  // Topgan puli
            'H' => 15,  // Avans
            'I' => 18,  // Qolgan summa
            'J' => 15,  // Imzo
        ];
    }
}
