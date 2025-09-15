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
        // TODO: Implement columnFormats() method.
    }

    public function registerEvents(): array
    {
        // TODO: Implement registerEvents() method.
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
        // TODO: Implement columnFormats() method.
    }

    public function registerEvents(): array
    {
        // TODO: Implement registerEvents() method.
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
            'Buyurtma ID','Buyurtma#','Model','Submodellari','Mas’ul','Narx USD','Narx so‘m',
            'Umumiy qty','Rasxod limiti (so‘m)','Bonus','Tarifikatsiya','Ajratilgan transport','Ajratilgan AUP',
            'Ajratilgan oylik xarajat','Daromad % xarajat','Amortizatsiya','Jami qo‘shimcha','Doimiy xarajat (so‘m)','Jami ishlab chiqarish tannarxi (so‘m)',
            'Sof foyda (so‘m)','Bir dona mahsulot tannarxi (so‘m)','Bir dona foyda (so‘m)','Rentabellik %'
        ];
    }

    public function title(): string
    {
        return 'Buyurtmalar';
    }

    public function array(): array
    {
        // TODO: Implement array() method.
    }

    public function columnFormats(): array
    {
        // TODO: Implement columnFormats() method.
    }

    public function registerEvents(): array
    {
        // TODO: Implement registerEvents() method.
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
        return ['Xarajat turi', 'Jami (so‘m)'];
    }

    public function title(): string
    {
        return 'Xarajat turlari';
    }

    public function array(): array
    {
        // TODO: Implement array() method.
    }

    public function registerEvents(): array
    {
        // TODO: Implement registerEvents() method.
    }
}