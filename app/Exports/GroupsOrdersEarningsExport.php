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
                    'piece_work'                     => 'Ishbay',
                    'monthly'                        => 'Oylik',
                    'hourly'                         => 'Soatbay',
                    'daily'                          => 'Kunlik',
                    'fixed_cutted_bonus'             => 'Kesilgan bonus',
                    'fixed_percentage_bonus'         => 'Foizli bonus',
                    'fixed_tailored_bonus_group'     => 'Guruh bonus',
                    default                          => ucfirst($emp['payment_type'] ?? 'Oylik'),
                };

                // Oylik va Ishbay uchun qiymatlarni aniqlash
                $monthlySalary = 0;
                $pieceworkSalary = 0;

                // Agar monthly_salary mavjud va status = true bo'lsa, o'sha amountni ishlatamiz
                if (isset($emp['monthly_salary']) && $emp['monthly_salary'] && $emp['monthly_salary']['status'] === true) {
                    $monthlySalary = (int) $emp['monthly_salary']['amount'];
                } else {
                    // Aks holda attendance_salary ni ishlatamiz
                    $monthlySalary = (int) $emp['attendance_salary'];
                }

                // Agar monthly_piecework mavjud va status = true bo'lsa, o'sha amountni ishlatamiz
                if (isset($emp['monthly_piecework']) && $emp['monthly_piecework'] && $emp['monthly_piecework']['status'] === true) {
                    $pieceworkSalary = (int) $emp['monthly_piecework']['amount'];
                } else {
                    // Aks holda tarification_salary ni ishlatamiz
                    $pieceworkSalary = (int) $emp['tarification_salary'];
                }

                $rows[] = [
                    $i++,
                    $emp['name'],
                    $emp['attendance_days'],
                    $paymentType,
                    $monthlySalary,          // oylik (monthly_salary yoki attendance_salary)
                    $pieceworkSalary,        // ishbay (monthly_piecework yoki tarification_salary)
                    (int) $emp['total_earned'],        // umumiy topgan puli
                    (int) $emp['total_paid'],          // avans
                    (int) $emp['net_balance'],         // qolgan summa
                    '',                                // imzo uchun bo'sh joy
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
            'B' => 35,  // FIO
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