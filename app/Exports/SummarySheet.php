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
        return ["Ko'rsatkich", "Qiymat (so'm)", 'Qiymat (USD)', 'Ulushi (%)'];
    }

    public function array(): array
    {
        $d = $this->stats;
        $dollar = max(1, (float)($d['dollar_rate'] ?? 1));
        $income = max((float)($d['total_earned_uzs'] ?? 0), 1);
        $days   = max((int)($d['days_in_period'] ?? 0), 1);

        $toInt = fn($v) => (int) round((float)($v ?? 0));
        $toUsd = fn($v) => round($toInt($v) / $dollar, 2);
        $toPct = fn($v) => round($toInt($v) / $income * 100, 2);

        return [
            ['Boshlanish sanasi', $d['start_date'] ?? '', '', ''],
            ['Tugash sanasi', $d['end_date'] ?? '', '', ''],
            ['Davr ichidagi kunlar', $days, '', ''],
            ['Dollar kursi', $dollar, '', ''],
            [],
            ['AUP', $toInt($d['aup'] ?? 0), $toUsd($d['aup'] ?? 0), $toPct($d['aup'] ?? 0)],
            ['KPI', $toInt($d['kpi'] ?? 0), $toUsd($d['kpi'] ?? 0), $toPct($d['kpi'] ?? 0)],
            ['Transport', $toInt($d['transport_attendance'] ?? 0), $toUsd($d['transport_attendance'] ?? 0), $toPct($d['transport_attendance'] ?? 0)],
            ['Tarifikatsiya', $toInt($d['tarification'] ?? 0), $toUsd($d['tarification'] ?? 0), $toPct($d['tarification'] ?? 0)],
            ['Oylik xarajatlar', $toInt($d['monthly_expenses'] ?? 0), $toUsd($d['monthly_expenses'] ?? 0), $toPct($d['monthly_expenses'] ?? 0)],
            [],
            ['Jami daromad', $toInt($d['total_earned_uzs'] ?? 0), $toUsd($d['total_earned_uzs'] ?? 0), 100],
            ['Jami ishlab chiqarish tannarxi', $toInt($d['total_output_cost_uzs'] ?? 0), $toUsd($d['total_output_cost_uzs'] ?? 0), $toPct($d['total_output_cost_uzs'] ?? 0)],
            ['Jami doimiy xarajat', $toInt($d['total_fixed_cost_uzs'] ?? 0), $toUsd($d['total_fixed_cost_uzs'] ?? 0), $toPct($d['total_fixed_cost_uzs'] ?? 0)],
            ['Sof foyda', $toInt($d['net_profit_uzs'] ?? 0), $toUsd($d['net_profit_uzs'] ?? 0), $toPct($d['net_profit_uzs'] ?? 0)],
            [],
            ["Kunlik o'rtacha daromad", $toInt($d['total_earned_uzs'] ?? 0) / $days, $toUsd(($d['total_earned_uzs'] ?? 0) / $days), ''],
            ["Kunlik o'rtacha sof foyda", $toInt($d['net_profit_uzs'] ?? 0) / $days, $toUsd(($d['net_profit_uzs'] ?? 0) / $days), ''],
            [],
            ['Ishlab chiqarilgan umumiy qty', $toInt($d['total_output_quantity'] ?? 0), '', ''],
            ['Bir dona mahsulot tannarxi', $toInt($d['cost_per_unit_overall_uzs'] ?? 0), $toUsd($d['cost_per_unit_overall_uzs'] ?? 0), ''],
            ["O'rtacha xodimlar soni", $toInt($d['average_employee_count'] ?? 0), '', ''],
            ["Bir xodimga to'g'ri keladigan xarajat", $toInt($d['per_employee_cost_uzs'] ?? 0), $toUsd($d['per_employee_cost_uzs'] ?? 0), ''],
        ];
    }

    public function title(): string
    {
        return 'Xulosa';
    }

    public function columnFormats(): array
    {
        return [
            'B' => '#,##0_-;[Red]-#,##0_-',  // so'm → ming ajratgichlari bilan
            'C' => '#,##0.00_-;[Red]-#,##0.00_-',  // USD → ming ajratgichlari + 2 kasr
            'D' => '0.00"%"_-;[Red]-0.00"%"_-',   // % → foiz belgisi bilan
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Headings stili - JUDA KATTA
                $sheet->getStyle('A1:D1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 36,  // 11 dan 36 ga (3x katta)
                        'color' => ['rgb' => '2F4F4F'],
                        'name' => 'Arial'
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        'wrapText' => true
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => 'E8F4FD']
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                            'color' => ['rgb' => '4A90E2']
                        ]
                    ]
                ]);

                // Header qatori balandligi - KATTA
                $sheet->getRowDimension(1)->setRowHeight(80);

                // Umumiy ma'lumotlar stili (2-5 qatorlar) - KATTA
                $sheet->getStyle('A2:D5')->applyFromArray([
                    'font' => [
                        'size' => 28,  // 10 dan 28 ga
                        'name' => 'Arial'
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => 'F9F9F9']
                    ]
                ]);

                // 2-5 qatorlar balandligi
                for ($i = 2; $i <= 5; $i++) {
                    $sheet->getRowDimension($i)->setRowHeight(50);
                }

                // Asosiy ma'lumotlar uchun
                $lastRow = $sheet->getHighestRow();
                for ($i = 6; $i <= $lastRow; $i++) {
                    $cellValue = $sheet->getCell("A{$i}")->getValue();

                    // Bo'sh qatorlarni o'tkazib yuborish
                    if (empty($cellValue)) {
                        $sheet->getRowDimension($i)->setRowHeight(25); // Bo'sh qatorlar kichikroq
                        continue;
                    }

                    // Har bir qator balandligi
                    $sheet->getRowDimension($i)->setRowHeight(55);

                    // Sof foyda uchun maxsus rang - JUDA KATTA
                    if ($cellValue === 'Sof foyda') {
                        $value = (float)$sheet->getCell("B{$i}")->getValue();
                        if ($value >= 0) {
                            $sheet->getStyle("A{$i}:D{$i}")->applyFromArray([
                                'font' => [
                                    'bold' => true,
                                    'color' => ['rgb' => '155724'],
                                    'size' => 32,  // 11 dan 32 ga
                                    'name' => 'Arial'
                                ],
                                'fill' => [
                                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                    'color' => ['rgb' => 'D4EDDA']
                                ],
                                'borders' => [
                                    'allBorders' => [
                                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                                        'color' => ['rgb' => '28A745']
                                    ]
                                ],
                                'alignment' => [
                                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                                ]
                            ]);
                            // Sof foyda qatori yanada baland
                            $sheet->getRowDimension($i)->setRowHeight(65);
                        } else {
                            $sheet->getStyle("A{$i}:D{$i}")->applyFromArray([
                                'font' => [
                                    'bold' => true,
                                    'color' => ['rgb' => '721C24'],
                                    'size' => 32,  // 11 dan 32 ga
                                    'name' => 'Arial'
                                ],
                                'fill' => [
                                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                    'color' => ['rgb' => 'F8D7DA']
                                ],
                                'borders' => [
                                    'allBorders' => [
                                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                                        'color' => ['rgb' => 'DC3545']
                                    ]
                                ],
                                'alignment' => [
                                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                                ]
                            ]);
                            // Sof foyda qatori yanada baland
                            $sheet->getRowDimension($i)->setRowHeight(65);
                        }
                    }
                    // Jami daromad uchun maxsus stil - KATTA
                    elseif ($cellValue === 'Jami daromad') {
                        $sheet->getStyle("A{$i}:D{$i}")->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'color' => ['rgb' => '0C5460'],
                                'size' => 30,  // 11 dan 30 ga
                                'name' => 'Arial'
                            ],
                            'fill' => [
                                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                'color' => ['rgb' => 'B8DAFF']
                            ],
                            'alignment' => [
                                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                            ]
                        ]);
                        $sheet->getRowDimension($i)->setRowHeight(60);
                    }
                    // Oddiy qatorlar uchun - KATTA
                    else {
                        $bgColor = ($i % 2 === 0) ? 'FFFFFF' : 'F8F9FA';
                        $sheet->getStyle("A{$i}:D{$i}")->applyFromArray([
                            'font' => [
                                'size' => 26,  // 10 dan 26 ga
                                'name' => 'Arial'
                            ],
                            'fill' => [
                                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                'color' => ['rgb' => $bgColor]
                            ],
                            'alignment' => [
                                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                            ]
                        ]);
                    }
                }

                // Ustunlar kengligi - JUDA KENG
                $sheet->getColumnDimension('A')->setWidth(80);  // 35 dan 80 ga
                $sheet->getColumnDimension('B')->setWidth(40);  // 18 dan 40 ga
                $sheet->getColumnDimension('C')->setWidth(35);  // 15 dan 35 ga
                $sheet->getColumnDimension('D')->setWidth(25);  // 12 dan 25 ga

                // Raqamlar uchun o'ng tomondan tekislash + vertikal markazlash
                $sheet->getStyle('B:D')->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT)
                    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

                // Birinchi ustunni chap tomondan tekislash + vertikal markazlash
                $sheet->getStyle('A:A')->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
                    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

                // Freeze pane
                $sheet->freezePane('A2');

                // Print uchun sozlamalar - landscape qilamiz katta bo'lgani uchun
                $sheet->getPageSetup()
                    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)  // Landscape
                    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setScale(85);  // 85% scale katta fontlar uchun

                // Margins - kichikroq qilamiz
                $sheet->getPageMargins()
                    ->setTop(0.5)
                    ->setRight(0.5)
                    ->setLeft(0.5)
                    ->setBottom(0.5);
            },
        ];
    }
}/**
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
            'B' => '# ##0;[Red]-# ##0', // AUP (so‘m)
            'C' => '# ##0;[Red]-# ##0', // KPI (so‘m)
            'D' => '# ##0;[Red]-# ##0', // Transport
            'E' => '# ##0;[Red]-# ##0', // Tarifikatsiya
            'F' => '# ##0;[Red]-# ##0', // Kunlik xarajatlar
            'G' => '# ##0;[Red]-# ##0', // Jami daromad
            'H' => '# ##0;[Red]-# ##0', // Doimiy xarajat
            'I' => '# ##0;[Red]-# ##0', // Sof foyda
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
            'F' => '# ##0;[Red]-# ##0', // Narx USD
            'G' => '# ##0;[Red]-# ##0', // Narx so‘m
            'I' => '# ##0;[Red]-# ##0', // Rasxod limiti
            'Q' => '# ##0;[Red]-# ##0', // Doimiy xarajat
            'R' => '# ##0;[Red]-# ##0', // Jami ishlab chiqarish tannarxi
            'S' => '# ##0;[Red]-# ##0', // Sof foyda
            'T' => '# ##0;[Red]-# ##0', // Bir dona tannarxi
            'U' => '# ##0;[Red]-# ##0', // Bir dona foyda
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