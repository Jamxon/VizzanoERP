<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MonthlyCostExport implements WithMultipleSheets
{
    protected array $payload;

    /**
     * $payload keys:
     *  - 'summary' => array (getMonthlyCost response)
     *  - 'daily' => array of daily rows
     *  - 'orders' => array of orders (detailed)
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function sheets(): array
    {
        return [
            new SummarySheet($this->payload['summary'] ?? []),
            new DailySheet($this->payload['daily'] ?? []),
            new OrdersSheet($this->payload['orders'] ?? []),
            new CostsByTypeSheet($this->payload['orders'] ?? []),
        ];
    }
}


