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
        return ['Metric', 'Value (UZS)', 'Value (USD)'];
    }

    public function array(): array
    {
        $dollar = $this->stats['dollar_rate'] ?? 1;

        $rows = [
            ['Start Date', $this->stats['start_date'] ?? '', ''],
            ['End Date', $this->stats['end_date'] ?? '', ''],
            ['Days in period', $this->stats['days_in_period'] ?? '', ''],
            ['Dollar rate', $this->stats['dollar_rate'] ?? '', ''],
            [],
            ['AUP (UZS)', $this->stats['aup'] ?? 0, ($this->stats['aup'] ?? 0) / max($dollar,1)],
            ['KPI (UZS)', $this->stats['kpi'] ?? 0, ($this->stats['kpi'] ?? 0) / max($dollar,1)],
            ['Transport attendance (UZS)', $this->stats['transport_attendance'] ?? 0, ($this->stats['transport_attendance'] ?? 0) / max($dollar,1)],
            ['Tarification (UZS)', $this->stats['tarification'] ?? 0, ($this->stats['tarification'] ?? 0) / max($dollar,1)],
            ['Monthly expenses (UZS)', $this->stats['monthly_expenses'] ?? 0, ($this->stats['monthly_expenses'] ?? 0) / max($dollar,1)],
            [],
            ['Total earned (UZS)', $this->stats['total_earned_uzs'] ?? 0, ($this->stats['total_earned_uzs'] ?? 0) / max($dollar,1)],
            ['Total output cost (UZS)', $this->stats['total_output_cost_uzs'] ?? 0, ($this->stats['total_output_cost_uzs'] ?? 0) / max($dollar,1)],
            ['Total fixed cost (UZS)', $this->stats['total_fixed_cost_uzs'] ?? 0, ($this->stats['total_fixed_cost_uzs'] ?? 0) / max($dollar,1)],
            ['Net profit (UZS)', $this->stats['net_profit_uzs'] ?? 0, ($this->stats['net_profit_uzs'] ?? 0) / max($dollar,1)],
            [],
            ['Total output quantity', $this->stats['total_output_quantity'] ?? 0, ''],
            ['Cost per unit overall (UZS)', $this->stats['cost_per_unit_overall_uzs'] ?? 0, (($this->stats['cost_per_unit_overall_uzs'] ?? 0) / max($dollar,1))],
            ['Average employee count', $this->stats['average_employee_count'] ?? 0, ''],
            ['Per employee cost (UZS)', $this->stats['per_employee_cost_uzs'] ?? 0, (($this->stats['per_employee_cost_uzs'] ?? 0) / max($dollar,1))],
        ];

        return $rows;
    }

    public function title(): string
    {
        return 'Summary';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // header style
                $event->sheet->getDelegate()->getStyle('A1:C1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => 'solid','startColor' => ['rgb' => 'D9EDF7']],
                ]);
                $event->sheet->getDelegate()->freezePane('A2');
            },
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'C' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }
}

/**
 * DailySheet
 */
class DailySheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithEvents, WithColumnFormatting
{
    protected array $daily;

    public function __construct(array $daily)
    {
        $this->daily = $daily;
    }

    public function headings(): array
    {
        return [
            'Date', 'AUP (UZS)', 'KPI (UZS)', 'Transport (UZS)', 'Tarification (UZS)',
            'Daily expenses (UZS)', 'Total earned (UZS)', 'Total fixed cost (UZS)', 'Net profit (UZS)',
            'Employee count', 'Total output qty'
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
        return 'Daily';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getStyle('A1:K1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => 'solid','startColor' => ['rgb' => 'F0F8FF']],
                ]);
                $event->sheet->getDelegate()->freezePane('A2');
            },
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'C' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'D' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'E' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'I' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'J' => NumberFormat::FORMAT_NUMBER,
            'K' => NumberFormat::FORMAT_NUMBER,
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
            'Order ID','Order#','Model','Submodels','Responsible','Price USD','Price UZS',
            'Total Qty','Rasxod Limit (UZS)','Bonus','Tarification','Allocated Transport','Allocated AUP',
            'Monthly Allocated','Income % Exp','Amortization','Total Extra','Fixed Cost (UZS)','Total Earned (UZS)',
            'Net Profit (UZS)','Cost/Unit (UZS)','Profit/Unit (UZS)','Profitability %'
        ];
    }

    protected function joinNames($collection)
    {
        if (is_array($collection)) {
            return implode(", ", array_map(function($v){ return is_array($v) && isset($v['name']) ? $v['name'] : (string)$v; }, $collection));
        }
        return (string)$collection;
    }

    public function array(): array
    {
        return array_map(function ($o) {
            $order = $o['order'] ?? [];
            $model = $o['model'] ?? [];
            $submodels = $o['submodels'] ?? [];
            $responsible = $o['responsibleUser'] ?? [];

            // costs_uzs stored as associative keys
            $costs = $o['costs_uzs'] ?? [];

            return [
                $order['id'] ?? '',
                $order['order_number'] ?? ($order['name'] ?? ''),
                $model['name'] ?? ($model['title'] ?? ''),
                $this->joinNames($submodels),
                is_array($responsible) ? implode(", ", array_map(fn($r)=>($r['employee']['name'] ?? ''), $responsible)) : '',
                $o['price_usd'] ?? 0,
                $o['price_uzs'] ?? 0,
                $o['total_quantity'] ?? 0,
                $o['rasxod_limit_uzs'] ?? 0,
                $o['bonus'] ?? 0,
                $o['tarification'] ?? 0,
                $costs['allocatedTransport'] ?? ($o['costs_uzs']['allocatedTransport'] ?? 0),
                $costs['allocatedAup'] ?? ($o['costs_uzs']['allocatedAup'] ?? 0),
                $costs['allocatedMonthlyExpenseMonthly'] ?? 0,
                $costs['incomePercentageExpense'] ?? 0,
                $costs['amortizationExpense'] ?? 0,
                ($o['total_fixed_cost_uzs'] ?? 0) - ($o['bonus'] ?? 0), // estimate total extra
                $o['total_fixed_cost_uzs'] ?? 0,
                $o['total_output_cost_uzs'] ?? 0,
                $o['net_profit_uzs'] ?? 0,
                $o['cost_per_unit_uzs'] ?? 0,
                $o['profit_per_unit_uzs'] ?? 0,
                $o['profitability_percent'] ?? 0,
            ];
        }, $this->orders);
    }

    public function title(): string
    {
        return 'Orders';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getStyle('A1:W1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => 'solid','startColor' => ['rgb' => 'FFF2CC']],
                ]);
                $event->sheet->getDelegate()->freezePane('A2');
            },
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_NUMBER_00,
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'H' => NumberFormat::FORMAT_NUMBER,
            'I' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'J' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'K' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'L' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'M' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'N' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'O' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'P' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'Q' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'R' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'S' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'T' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'U' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'V' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'W' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }
}

/**
 * CostsByTypeSheet
 */
class CostsByTypeSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithEvents
{
    protected array $orders;

    public function __construct(array $orders)
    {
        $this->orders = $orders;
    }

    public function headings(): array
    {
        return ['Cost Type', 'Total (UZS)'];
    }

    public function array(): array
    {
        $totals = [];

        foreach ($this->orders as $o) {
            $costs = $o['costs_uzs'] ?? [];
            foreach ($costs as $k => $v) {
                $totals[$k] = ($totals[$k] ?? 0) + ($v ?? 0);
            }
        }

        $rows = [];
        foreach ($totals as $k => $v) {
            $rows[] = [$k, $v];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Costs by Type';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getStyle('A1:B1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => 'solid','startColor' => ['rgb' => 'E2EFDA']],
                ]);
            },
        ];
    }
}

/**
 * RawDataSheet
 */
class RawDataSheet implements FromArray, WithTitle, ShouldAutoSize
{
    protected array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function array(): array
    {
        return [
            ['Raw JSON payload'],
            [json_encode($this->payload, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)]
        ];
    }

    public function title(): string
    {
        return 'Raw Data';
    }
}
