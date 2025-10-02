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
            // sort employees by name before looping
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

                $monthlySalary = 0;
                $pieceworkSalary = 0;
                $usedMonthlySalary = false;
                $usedMonthlyPiecework = false;

                if (isset($emp['monthly_salary']) && $emp['monthly_salary'] && $emp['monthly_salary']['status'] === true) {
                    $monthlySalary = (int) $emp['monthly_salary']['amount'];
                    $usedMonthlySalary = true;
                } else {
                    $monthlySalary = (int) $emp['attendance_salary'];
                }

                if (isset($emp['monthly_piecework']) && $emp['monthly_piecework'] && $emp['monthly_piecework']['status'] === true) {
                    $pieceworkSalary = (int) $emp['monthly_piecework']['amount'];
                    $usedMonthlyPiecework = true;
                } else {
                    $pieceworkSalary = (int) $emp['tarification_salary'];
                }

                $totalEarned = ($usedMonthlySalary || $usedMonthlyPiecework)
                    ? $monthlySalary + $pieceworkSalary
                    : (int) $emp['total_earned'];

                $netBalance = $totalEarned - (int) $emp['total_paid'];

                $rows[] = [
                    $i++,
                    $emp['name'],
                    (int) $emp['attendance_days'],
                    $paymentType,
                    $monthlySalary,
                    $pieceworkSalary,
                    $totalEarned,
                    (int) $emp['total_paid'],
                    $netBalance,
                    '',
                ];
            }
        }

        // totals row
        $totals = [
            '', 'Jami',
            array_sum(array_column($rows, 2)), // attendance_days
            '',
            array_sum(array_column($rows, 4)), // monthly
            array_sum(array_column($rows, 5)), // piecework
            array_sum(array_column($rows, 6)), // total earned
            array_sum(array_column($rows, 7)), // total paid
            array_sum(array_column($rows, 8)), // net balance
            '',
        ];

        $rows[] = $totals;

        return $rows;
    }

    public function headings(): array
    {
        // Empty row for merged title, then actual headings
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
                "Imzo",
            ]
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Merge first row across all columns
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Bold headings
        $sheet->getStyle('A2:J2')->getFont()->setBold(true);

        // Bold totals row
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A' . $lastRow . ':J' . $lastRow)->getFont()->setBold(true);

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
            'J' => 15,
        ];
    }
}