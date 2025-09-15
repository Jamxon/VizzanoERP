<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

/**
 * SummarySheet
 */
class SummarySheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithEvents, WithColumnFormatting
{
    protected array $stats;

    public function __construct(array $stats)
    {
        $this->stats = $stats;
    }

    public function headings(): array
    {
        return ['Ko‘rsatkich', 'Qiymat (so‘m)', 'Qiymat (USD)', 'Ulushi (%)'];
    }

    public function array(): array
    {
        $dollar = $this->stats['dollar_rate'] ?? 1;
        $income = max($this->stats['total_earned_uzs'] ?? 0, 1); // bo‘linishda 0 bo‘lmasin
        $days   = max($this->stats['days_in_period'] ?? 0, 1);

        $rows = [
            ['Boshlanish sanasi', $this->stats['start_date'] ?? '', '', ''],
            ['Tugash sanasi', $this->stats['end_date'] ?? '', '', ''],
            ['Davr ichidagi kunlar', $this->stats['days_in_period'] ?? '', '', ''],
            ['Dollar kursi', $this->stats['dollar_rate'] ?? '', '', ''],
            [],
            ['AUP', $this->stats['aup'] ?? 0, ($this->stats['aup'] ?? 0)/$dollar, round(($this->stats['aup'] ?? 0)/$income*100, 2)],
            ['KPI', $this->stats['kpi'] ?? 0, ($this->stats['kpi'] ?? 0)/$dollar, round(($this->stats['kpi'] ?? 0)/$income*100, 2)],
            ['Transport', $this->stats['transport_attendance'] ?? 0, ($this->stats['transport_attendance'] ?? 0)/$dollar, round(($this->stats['transport_attendance'] ?? 0)/$income*100, 2)],
            ['Tarifikatsiya', $this->stats['tarification'] ?? 0, ($this->stats['tarification'] ?? 0)/$dollar, round(($this->stats['tarification'] ?? 0)/$income*100, 2)],
            ['Oylik xarajatlar', $this->stats['monthly_expenses'] ?? 0, ($this->stats['monthly_expenses'] ?? 0)/$dollar, round(($this->stats['monthly_expenses'] ?? 0)/$income*100, 2)],
            [],
            ['Jami daromad', $this->stats['total_earned_uzs'] ?? 0, ($this->stats['total_earned_uzs'] ?? 0)/$dollar, '100'],
            ['Jami ishlab chiqarish tannarxi', $this->stats['total_output_cost_uzs'] ?? 0, ($this->stats['total_output_cost_uzs'] ?? 0)/$dollar, round(($this->stats['total_output_cost_uzs'] ?? 0)/$income*100, 2)],
            ['Jami doimiy xarajat', $this->stats['total_fixed_cost_uzs'] ?? 0, ($this->stats['total_fixed_cost_uzs'] ?? 0)/$dollar, round(($this->stats['total_fixed_cost_uzs'] ?? 0)/$income*100, 2)],
            ['Sof foyda', $this->stats['net_profit_uzs'] ?? 0, ($this->stats['net_profit_uzs'] ?? 0)/$dollar, round(($this->stats['net_profit_uzs'] ?? 0)/$income*100, 2)],
            [],
            ['Kunlik o‘rtacha daromad', round(($this->stats['total_earned_uzs'] ?? 0)/$days), round(($this->stats['total_earned_uzs'] ?? 0)/$days/$dollar,2), ''],
            ['Kunlik o‘rtacha sof foyda', round(($this->stats['net_profit_uzs'] ?? 0)/$days), round(($this->stats['net_profit_uzs'] ?? 0)/$days/$dollar,2), ''],
            [],
            ['Ishlab chiqarilgan umumiy qty', $this->stats['total_output_quantity'] ?? 0, '', ''],
            ['Bir dona mahsulot tannarxi', $this->stats['cost_per_unit_overall_uzs'] ?? 0, ($this->stats['cost_per_unit_overall_uzs'] ?? 0)/$dollar, ''],
            ['O‘rtacha xodimlar soni', $this->stats['average_employee_count'] ?? 0, '', ''],
            ['Bir xodimga to‘g‘ri keladigan xarajat', $this->stats['per_employee_cost_uzs'] ?? 0, ($this->stats['per_employee_cost_uzs'] ?? 0)/$dollar, ''],
        ];

        return $rows;
    }

    public function title(): string
    {
        return 'Xulosa';
    }

    public function columnFormats(): array
    {
        return [
            'B' => '# ##0',
            'C' => NumberFormat::FORMAT_NUMBER_00,
            'D' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Headingsni ajratib qo‘yish
                $sheet->getStyle('A1:D1')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => 'center'],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => 'D9EDF7']
                    ]
                ]);

                // Sof foyda → yashil/qizil
                $lastRow = $sheet->getHighestRow();
                for ($i=2; $i<=$lastRow; $i++) {
                    $indicator = $sheet->getCell("A{$i}")->getValue();
                    if ($indicator === 'Sof foyda') {
                        $value = $sheet->getCell("B{$i}")->getValue();
                        if ($value >= 0) {
                            $sheet->getStyle("A{$i}:D{$i}")->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['rgb' => '006400']],
                                'fill' => ['fillType' => 'solid','color' => ['rgb' => 'C6EFCE']]
                            ]);
                        } else {
                            $sheet->getStyle("A{$i}:D{$i}")->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['rgb' => '9C0006']],
                                'fill' => ['fillType' => 'solid','color' => ['rgb' => 'FFC7CE']]
                            ]);
                        }
                    }
                }

                // Guruhlar oralig‘iga qalin border
                foreach ([5, 11, 15, 18] as $row) {
                    $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
                        'borders' => ['top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK]]
                    ]);
                }

                $sheet->freezePane('A2');
            },
        ];
    }
}

/**
 * DailySheet
 */

class DailySheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    protected array $daily;

    public function __construct(array $daily)
    {
        $this->daily = $daily;
    }

    public function headings(): array
    {
        return [
            'Sana', 'AUP (so‘m)', 'KPI (so‘m)', 'Transport (so‘m)', 'Tarifikatsiya (so‘m)',
            'Kunlik xarajatlar (so‘m)', 'Jami daromad (so‘m)', 'Doimiy xarajat (so‘m)', 'Sof foyda (so‘m)',
            'Xodimlar soni', 'Jami ishlab chiqarilgan qty'
        ];
    }

    public function title(): string
    {
        return 'Kunlik';
    }

    public function columnFormats(): array
    {
        return [
            'B' => '# ##0', // AUP (so‘m)
            'C' => '# ##0', // KPI (so‘m)
            'D' => '# ##0', // Transport
            'E' => '# ##0', // Tarifikatsiya
            'F' => '# ##0', // Kunlik xarajatlar
            'G' => '# ##0', // Jami daromad
            'H' => '# ##0', // Doimiy xarajat
            'I' => '# ##0', // Sof foyda
        ];
    }

    public function array(): array
    {
        $rows = array_map(function ($d) {
            return [
                $d['date'] ?? '',
                $d['aup'] ?? 0,
                $d['kpi'] ?? 0,
                $d['transport_attendance'] ?? 0,
                $d['tarification'] ?? 0,
                $d['daily_expenses'] ?? 0,
                $d['total_earned_uzs'] ?? 0,
                $d['total_fixed_cost_uzs'] ?? 0,
                $d['net_profit_uzs'] ?? 0,
                $d['employee_count'] ?? 0,
                $d['total_output_quantity'] ?? 0,
            ];
        }, $this->daily);

        // Umumiy hisob
        $totals = [
            'Umumiy:',
            array_sum(array_column($rows, 1)),
            array_sum(array_column($rows, 2)),
            array_sum(array_column($rows, 3)),
            array_sum(array_column($rows, 4)),
            array_sum(array_column($rows, 5)),
            array_sum(array_column($rows, 6)),
            array_sum(array_column($rows, 7)),
            array_sum(array_column($rows, 8)),
            array_sum(array_column($rows, 9)),
            array_sum(array_column($rows, 10)),
        ];

        // O‘rtacha hisob
        $count = count($rows) ?: 1;
        $averages = [
            "O‘rtacha:",
            round($totals[1] / $count),
            round($totals[2] / $count),
            round($totals[3] / $count),
            round($totals[4] / $count),
            round($totals[5] / $count),
            round($totals[6] / $count),
            round($totals[7] / $count),
            round($totals[8] / $count),
            round($totals[9] / $count),
            round($totals[10] / $count),
        ];

        $rows[] = $totals;
        $rows[] = $averages;

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Headings
                $sheet->getStyle('A1:K1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => 'FFFF99']
                    ]
                ]);

                $lastRow = $sheet->getHighestRow();

                // Umumiy → yashil
                $sheet->getStyle("A".($lastRow-1).":K".($lastRow-1))->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => '4CAF50']
                    ]
                ]);

                // O‘rtacha → ko‘k
                $sheet->getStyle("A{$lastRow}:K{$lastRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => '2196F3']
                    ]
                ]);
            }
        ];
    }
}

/**
 * OrdersSheet
 */
class OrdersSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithEvents, WithColumnFormatting
{
    protected array $orders;

    public function __construct(array $orders)
    {
        $this->orders = $orders;
    }

    public function headings(): array
    {
        return [
            'Buyurtma ID',
            'Buyurtma nomi',
            'Model',
            'Submodellari',
            'Mas’ul',
            'Narx USD',
            'Narx so‘m',
            'Umumiy qty',
            'Rasxod limiti (so‘m)',
            'Bonus',
            'Tarifikatsiya',

            // costs_uzs ichidagi maydonlar
            'Ajratilgan transport',
            'Ajratilgan AUP',
            'Daromad % xarajat',
            'Amortizatsiya',
            'Jami qo‘shimcha',

            // umumiy xarajat va daromadlar
            'Doimiy xarajat (so‘m)',
            'Jami ishlab chiqarish tannarxi (so‘m)',
            'Sof foyda (so‘m)',
            'Bir dona mahsulot tannarxi (so‘m)',
            'Bir dona foyda (so‘m)',
            'Rentabellik %',
        ];
    }

    public function title(): string
    {
        return 'Buyurtmalar';
    }

    public function array(): array
    {
        $rows = [];
        $count = count($this->orders);

        $totals = [
            'price_usd' => 0,
            'price_uzs' => 0,
            'total_quantity' => 0,
            'rasxod_limit_uzs' => 0,
            'bonus' => 0,
            'tarification' => 0,
            'allocatedTransport' => 0,
            'allocatedAup' => 0,
            'incomePercentageExpense' => 0,
            'amortizationExpense' => 0,
            'remainder' => 0,
            'total_fixed_cost_uzs' => 0,
            'total_output_cost_uzs' => 0,
            'net_profit_uzs' => 0,
            'cost_per_unit_uzs' => 0,
            'profit_per_unit_uzs' => 0,
            'profitability_percent' => 0,
        ];

        foreach ($this->orders as $o) {
            $order = $o['order'] ?? [];
            $model = $o['model'] ?? [];
            $submodels = implode(', ', array_map(fn($s) => $s['name'] ?? '', $o['submodels'] ?? []));
            $responsibleUsers = implode(', ', array_map(fn($u) => $u['employee']['name'] ?? '', $o['responsibleUser'] ?? []));

            $costs = $o['costs_uzs'] ?? [];

            $row = [
                $order['id'] ?? '',
                $order['name'] ?? '',
                $model['name'] ?? '',
                $submodels,
                $responsibleUsers,
                $o['price_usd'] ?? 0,
                $o['price_uzs'] ?? 0,
                $o['total_quantity'] ?? 0,
                $o['rasxod_limit_uzs'] ?? 0,
                $o['bonus'] ?? 0,
                $o['tarification'] ?? 0,
                $costs['allocatedTransport'] ?? 0,
                $costs['allocatedAup'] ?? 0,
                $costs['incomePercentageExpense'] ?? 0,
                $costs['amortizationExpense'] ?? 0,
                $costs['remainder'] ?? 0,
                $o['total_fixed_cost_uzs'] ?? 0,
                $o['total_output_cost_uzs'] ?? 0,
                $o['net_profit_uzs'] ?? 0,
                $o['cost_per_unit_uzs'] ?? 0,
                $o['profit_per_unit_uzs'] ?? 0,
                $o['profitability_percent'] ?? 0,
            ];

            $rows[] = $row;

            // totals
            foreach ($totals as $key => $val) {
                $totals[$key] += $o[$key] ?? ($costs[$key] ?? 0);
            }
        }

        // bitta row ichida jami va o‘rtacha
        $rows[] = [
            '', 'JAMI:', '', '', '',
            $totals['price_usd'],
            $totals['price_uzs'],
            $totals['total_quantity'],
            $totals['rasxod_limit_uzs'],
            $totals['bonus'],
            $totals['tarification'],
            $totals['allocatedTransport'],
            $totals['allocatedAup'],
            $totals['incomePercentageExpense'],
            $totals['amortizationExpense'],
            $totals['remainder'],
            $totals['total_fixed_cost_uzs'],
            $totals['total_output_cost_uzs'],
            $totals['net_profit_uzs'],

            // shu joydan O‘RTACHA qiymatlar ketadi
            $count > 0 ? round($totals['cost_per_unit_uzs'] / $count, 2) : 0,
            $count > 0 ? round($totals['profit_per_unit_uzs'] / $count, 2) : 0,
            $count > 0 ? round($totals['profitability_percent'] / $count, 2) : 0,
            'O‘RTACHA',
        ];

        return $rows;
    }

    public function columnFormats(): array
    {
        return [
            'F' => '# ##0', // Narx USD
            'G' => '# ##0', // Narx so‘m
            'I' => '# ##0', // Rasxod limiti
            'Q' => '# ##0', // Doimiy xarajat
            'R' => '# ##0', // Jami ishlab chiqarish tannarxi
            'S' => '# ##0', // Sof foyda
            'T' => '# ##0', // Bir dona tannarxi
            'U' => '# ##0', // Bir dona foyda
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                // heading style
                $sheet->getStyle('A1:V1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => 'FFFFCC']
                    ]
                ]);

                // JAMI segment (masalan A:U ustunlari)
                $sheet->getStyle("A{$highestRow}:S{$highestRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => 'CCFFCC'] // yashil
                    ]
                ]);

                // O‘RTACHA segment (masalan T:V ustunlari)
                $sheet->getStyle("T{$highestRow}:W{$highestRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => 'CCCCFF'] // ko‘k
                    ]
                ]);
            }
        ];
    }
}

/**
 * CostsByTypeSheet
 */
class CostsByTypeSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithColumnFormatting
{
    protected array $orders;

    public function __construct(array $orders)
    {
        $this->orders = $orders;
    }

    public function headings(): array
    {
        return ['Xarajat turi', 'Jami (so‘m)'];
    }

    public function title(): string
    {
        return 'Xarajat turlari';
    }

    public function array(): array
    {
        $totals = [];

        // Har bir order ichidan costs_uzs ni yig‘amiz
        foreach ($this->orders as $order) {
            if (!empty($order['costs_uzs']) && is_array($order['costs_uzs'])) {
                foreach ($order['costs_uzs'] as $type => $amount) {
                    if (!isset($totals[$type])) {
                        $totals[$type] = 0;
                    }
                    $totals[$type] += $amount;
                }
            }
        }

        $rows = [];
        $grandTotal = 0;

        foreach ($totals as $type => $amount) {
            $rows[] = [
                $this->translateCostType($type),
                round($amount),
            ];
            $grandTotal += $amount;
        }

        // Umumiy yig‘indi satri
        $rows[] = [
            'JAMI',
            round($grandTotal),
        ];

        return $rows;
    }

    public function columnFormats(): array
    {
        return [
            'B' => '#,##0', // Jami (so‘m) formatlash
        ];
    }

    /**
     * Xarajat turlarini odamga tushunarli nomga o‘tkazish
     */
    protected function translateCostType(string $key): string
    {
        $map = [
            'allocatedAup' => "AUP",
            'bonus' => "KPI", // bonusni KPI deb chiqaramiz
            'total_fixed_cost_uzs' => "O‘zgarmas xarajat",
            'allocatedTransport' => "Transport",
            'amortizationExpense' => "Amortizatsiya",
            'incomePercentageExpense' => "Soliq",
            'tarification' => "Tikuv uchun",
            'remainder' => "Tikuvchilar", // agar bu qoldiqni tikuvchilarga taqsimlangan deb ko‘rsatayotgan bo‘lsang
        ];

        return $map[$key] ?? $key;
    }

}