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
        return ['Ko‘rsatkich', 'Qiymat (so‘m)', 'Qiymat (USD)'];
    }

    public function array(): array
    {
        $dollar = $this->stats['dollar_rate'] ?? 1;

        return [
            ['Boshlanish sanasi', $this->stats['start_date'] ?? '', ''],
            ['Tugash sanasi', $this->stats['end_date'] ?? '', ''],
            ['Davr ichidagi kunlar', $this->stats['days_in_period'] ?? '', ''],
            ['Dollar kursi', $this->stats['dollar_rate'] ?? '', ''],
            [],
            ['AUP (so‘m)', $this->stats['aup'] ?? 0, ($this->stats['aup'] ?? 0) / max($dollar,1)],
            ['KPI (so‘m)', $this->stats['kpi'] ?? 0, ($this->stats['kpi'] ?? 0) / max($dollar,1)],
            ['Transport davomat (so‘m)', $this->stats['transport_attendance'] ?? 0, ($this->stats['transport_attendance'] ?? 0) / max($dollar,1)],
            ['Tarifikatsiya (so‘m)', $this->stats['tarification'] ?? 0, ($this->stats['tarification'] ?? 0) / max($dollar,1)],
            ['Oylik xarajatlar (so‘m)', $this->stats['monthly_expenses'] ?? 0, ($this->stats['monthly_expenses'] ?? 0) / max($dollar,1)],
            [],
            ['Jami daromad (so‘m)', $this->stats['total_earned_uzs'] ?? 0, ($this->stats['total_earned_uzs'] ?? 0) / max($dollar,1)],
            ['Jami ishlab chiqarish tannarxi (so‘m)', $this->stats['total_output_cost_uzs'] ?? 0, ($this->stats['total_output_cost_uzs'] ?? 0) / max($dollar,1)],
            ['Jami doimiy xarajat (so‘m)', $this->stats['total_fixed_cost_uzs'] ?? 0, ($this->stats['total_fixed_cost_uzs'] ?? 0) / max($dollar,1)],
            ['Sof foyda (so‘m)', $this->stats['net_profit_uzs'] ?? 0, ($this->stats['net_profit_uzs'] ?? 0) / max($dollar,1)],
            [],
            ['Ishlab chiqarilgan umumiy miqdor', $this->stats['total_output_quantity'] ?? 0, ''],
            ['Bir dona mahsulot tannarxi (so‘m)', $this->stats['cost_per_unit_overall_uzs'] ?? 0, ($this->stats['cost_per_unit_overall_uzs'] ?? 0) / max($dollar,1)],
            ['O‘rtacha xodimlar soni', $this->stats['average_employee_count'] ?? 0, ''],
            ['Bir xodimga to‘g‘ri keladigan xarajat (so‘m)', $this->stats['per_employee_cost_uzs'] ?? 0, ($this->stats['per_employee_cost_uzs'] ?? 0) / max($dollar,1)],
        ];
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
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getStyle('A1:C1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => 'solid','startColor' => ['rgb' => 'D9EDF7']],
                ]);
                $event->sheet->getDelegate()->freezePane('A2');
            },
        ];
    }
}

/**
 * DailySheet
 */

class DailySheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithColumnFormatting
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

    public function array(): array
    {
        return array_map(function ($d) {
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1-qator (headings)ni qalin va sariq qilish
                $sheet->getStyle('A1:K1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => 'FFFF99']
                    ]
                ]);

                // Ustunlarni avtomatik kengaytirish
                foreach (range('A', 'K') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
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
            'O‘RTACHA:',
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
    protected array $costs;

    public function __construct(array $costs)
    {
        $this->costs = $costs;
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
        return array_map(function ($c) {
            return [
                $c['type'] ?? '',
                $c['amount'] ?? 0,
            ];
        }, $this->costs);
    }

    public function columnFormats(): array
    {
        return [
            'B' => '# ##0', // Jami (so‘m)
        ];
    }
}
