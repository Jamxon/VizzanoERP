<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class GroupsOrdersEarningsExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    protected array $data;
    protected string $department;
    protected string $group;
    protected string $month;

    public function __construct(array $data, string $department, string $group, string $month)
    {
        $this->data = $data;
        $this->department = $department;
        $this->group = $group;
        $this->month = $month;
    }

    public function array(): array
    {
        $rows = [];
        $i = 1;

        foreach ($this->data as $group) {
            usort($group['employees'], fn($a, $b) => strcmp($a['name'], $b['name']));

            foreach ($group['employees'] as $emp) {
                $paymentType = match ($emp['payment_type']) {
                    'piece_work'                 => 'Ishbay',
                    'monthly'                    => 'Oylik',
                    'hourly'                     => 'Soatbay',
                    'daily'                      => 'Kunlik',
                    'fixed_cutted_bonus'         => 'Kesilgan bonus',
                    'fixed_percentage_bonus'     => 'Foizli bonus',
                    'fixed_tailored_bonus_group' => 'Guruh bonus',
                    default                      => ucfirst($emp['payment_type'] ?? 'Oylik'),
                };

                $monthlySalary = isset($emp['monthly_salary']) && $emp['monthly_salary']['status'] === true
                    ? (int)$emp['monthly_salary']['amount']
                    : (int)$emp['attendance_salary'];

                $pieceworkSalary = isset($emp['monthly_piecework']) && $emp['monthly_piecework']['status'] === true
                    ? (int)$emp['monthly_piecework']['amount']
                    : (int)$emp['tarification_salary'];

                $usedMonthlyValues = ($emp['monthly_salary']['status'] ?? false) || ($emp['monthly_piecework']['status'] ?? false);

                $totalEarned = $usedMonthlyValues
                    ? $monthlySalary + $pieceworkSalary
                    : (int)$emp['total_earned'];

                $netBalance = $totalEarned - (int)$emp['total_paid'];

                $rows[] = [
                    $i++,
                    $emp['name'],
                    (int)$emp['attendance_days'],
                    $paymentType,
                    $monthlySalary,
                    $pieceworkSalary,
                    $totalEarned,
                    (int)$emp['total_paid'],
                    $netBalance,
                    $emp['passport_code'] ?? '', // ⬅️ YANGI USTUN
                    '',
                ];
            }
        }

        $totals = [
            '', 'Jami',
            array_sum(array_column($rows, 2)),
            '',
            array_sum(array_column($rows, 4)),
            array_sum(array_column($rows, 5)),
            array_sum(array_column($rows, 6)),
            array_sum(array_column($rows, 7)),
            array_sum(array_column($rows, 8)),
            '', // passport_code total bo’lmaydi
            '',
        ];

        $rows[] = $totals;
        return $rows;
    }

    public function headings(): array
    {
        return [
            [$this->department . ' - ' . $this->group . ' (' . $this->month . ')'],
            [
                'No',
                'FIO',
                'Ish kunlari',
                "To'lov turi",
                "Ish haqi (oylik)",
                "Ish haqi (ishbay)",
                "Topgan puli",
                "Avans",
                "Qolgan summa",
                "Passport kodi", // ⬅️ YANGI
                "Imzo",
            ]
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:K1'); // ⬅️ A dan K gacha kengaydi
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle('A2:K2')->getFont()->setBold(true);

        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A' . $lastRow . ':K' . $lastRow)->getFont()->setBold(true);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 35,
            'C' => 12,
            'D' => 15,
            'E' => 20,
            'F' => 20,
            'G' => 20,
            'H' => 15,
            'I' => 18,
            'J' => 20, // passport_code width
            'K' => 15, // signature width
        ];
    }
}
