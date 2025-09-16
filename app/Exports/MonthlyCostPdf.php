<?php

namespace App\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * MonthlyCostPdf - Oylik xarajat hisobotini PDF formatida eksport qilish
 *
 * Composer requirements:
 * composer require barryvdh/laravel-dompdf
 *
 * Config (config/app.php providers):
 * Barryvdh\DomPDF\ServiceProvider::class,
 *
 * Usage:
 * $pdf = new MonthlyCostPdf($data);
 * return $pdf->generate()->download('hisobot.pdf');
 */

class MonthlyCostPdf
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function generate(): \Barryvdh\DomPDF\PDF
    {
        $html = $this->generateHtml();

        return Pdf::loadHTML($html)
            ->setPaper('A4', 'landscape')
            ->setOptions([
                'defaultFont' => 'Arial',
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'isFontSubsettingEnabled' => true,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'margin_left' => 10,
                'margin_right' => 10,
            ]);
    }

    protected function generateHtml(): string
    {
        $summary = $this->data['summary'] ?? [];
        $daily = $this->data['daily'] ?? [];
        $orders = $this->data['orders'] ?? [];

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Oylik Xarajat Hisoboti</title>
            <style>
                {$this->getStyles()}
            </style>
        </head>
        <body>
            <div class='container'>
                <!-- Header -->
                <div class='header'>
                    <h1>OYLIK XARAJAT HISOBOTI</h1>
                    <div class='date-info'>
                        <span>Hisobot sanasi: " . Carbon::now()->format('d.m.Y H:i') . "</span>
                    </div>
                </div>

                <!-- Summary Section -->
                {$this->generateSummarySection($summary)}
                
                <!-- Page Break -->
                <div class='page-break'></div>

                <!-- Daily Section -->
                {$this->generateDailySection($daily)}
                
                <!-- Page Break -->
                <div class='page-break'></div>

                <!-- Orders Section -->
                {$this->generateOrdersSection($orders)}
                
                <!-- Cost Types Section -->
                {$this->generateCostTypesSection($orders)}

                <!-- Footer -->
                <div class='footer'>
                    <p>© " . date('Y') . " Tikuv korxonasi - Buxgalteriya hisoboti</p>
                </div>
            </div>
        </body>
        </html>";
    }

    protected function getStyles(): string
    {
        return "
            @page {
                margin: 15mm;
                size: A4 landscape;
            }

            body {
                font-family: 'DejaVu Sans', Arial, sans-serif;
                font-size: 11px;
                line-height: 1.4;
                color: #333;
                margin: 0;
                padding: 0;
            }

            .container {
                width: 100%;
                max-width: 1000px;
                margin: 0 auto;
            }

            .header {
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 3px solid #2196F3;
            }

            .header h1 {
                color: #1976D2;
                font-size: 24px;
                font-weight: bold;
                margin: 0 0 10px 0;
                text-transform: uppercase;
            }

            .date-info {
                color: #666;
                font-size: 12px;
            }

            .section {
                margin-bottom: 30px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                overflow: hidden;
            }

            .section-title {
                background: linear-gradient(135deg, #2196F3, #21CBF3);
                color: white;
                font-size: 16px;
                font-weight: bold;
                padding: 12px 20px;
                margin: 0;
                text-align: center;
                text-transform: uppercase;
            }

            .summary-info {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 6px;
                border-left: 4px solid #17a2b8;
            }

            .summary-info h3 {
                color: #17a2b8;
                font-size: 14px;
                margin: 0 0 10px 0;
                font-weight: bold;
            }

            .info-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 8px;
                padding: 4px 0;
                border-bottom: 1px dotted #ddd;
            }

            .info-row:last-child {
                border-bottom: none;
            }

            .info-label {
                font-weight: bold;
                color: #495057;
            }

            .info-value {
                color: #007bff;
                font-weight: bold;
            }

            .summary-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }

            .summary-table th,
            .summary-table td {
                padding: 12px 8px;
                text-align: center;
                border: 1px solid #dee2e6;
                font-size: 11px;
            }

            .summary-table th {
                background: linear-gradient(135deg, #495057, #6c757d);
                color: white;
                font-weight: bold;
                text-transform: uppercase;
            }

            .summary-table tbody tr:nth-child(even) {
                background-color: #f8f9fa;
            }

            .summary-table tbody tr:hover {
                background-color: #e3f2fd;
            }

            .profit-positive {
                background-color: #d4edda !important;
                color: #155724;
                font-weight: bold;
            }

            .profit-negative {
                background-color: #f8d7da !important;
                color: #721c24;
                font-weight: bold;
            }

            .total-income {
                background-color: #cce5ff !important;
                color: #0056b3;
                font-weight: bold;
            }

            .highlight-row {
                background-color: #fff3cd !important;
                color: #856404;
                font-weight: bold;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 10px;
            }

            th, td {
                padding: 8px 4px;
                text-align: center;
                border: 1px solid #dee2e6;
            }

            th {
                background: #495057;
                color: white;
                font-weight: bold;
                font-size: 9px;
                text-transform: uppercase;
            }

            tbody tr:nth-child(even) {
                background-color: #f8f9fa;
            }

            .total-row {
                background-color: #28a745 !important;
                color: white;
                font-weight: bold;
            }

            .average-row {
                background-color: #007bff !important;
                color: white;
                font-weight: bold;
            }

            .number {
                text-align: right;
                font-family: 'DejaVu Sans Mono', monospace;
            }

            .page-break {
                page-break-before: always;
            }

            .footer {
                text-align: center;
                margin-top: 30px;
                padding-top: 20px;
                border-top: 2px solid #dee2e6;
                color: #6c757d;
                font-size: 10px;
            }

            .cost-types-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                padding: 20px;
            }

            .cost-item {
                display: flex;
                justify-content: space-between;
                padding: 10px 15px;
                background: #f8f9fa;
                border-radius: 6px;
                border-left: 4px solid #28a745;
                margin-bottom: 10px;
            }

            .cost-label {
                font-weight: bold;
                color: #495057;
            }

            .cost-amount {
                color: #28a745;
                font-weight: bold;
            }

            .grand-total {
                background: linear-gradient(135deg, #28a745, #34ce57) !important;
                color: white;
                font-size: 14px;
                font-weight: bold;
                text-align: center;
                padding: 15px;
                border-radius: 6px;
                margin-top: 15px;
            }
            .summary-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 20px; /* ustunlar orasidagi masofa */
            }
            
            .summary-info {
                background: #f9f9f9;
                padding: 15px;
                border-radius: 8px;
            }

        ";
    }

    protected function generateSummarySection(array $summary): string
    {
        $dollar = max(1, (float)($summary['dollar_rate'] ?? 1));
        $days = max((int)($summary['days_in_period'] ?? 0), 1);

        $toInt = fn($v) => (int) round((float)($v ?? 0));
        $toUsd = fn($v) => number_format(round($toInt($v) / $dollar, 2), 2, '.', ',');
        $toPct = fn($v, $income) => $income > 0 ? round($toInt($v) / $income * 100, 2) : 0;
        $formatUzs = fn($v) => number_format($toInt($v), 0, '.', ' ');

        $income = max((float)($summary['total_earned_uzs'] ?? 0), 1);
        $netProfit = $toInt($summary['net_profit_uzs'] ?? 0);

        $profitClass = $netProfit >= 0 ? 'profit-positive' : 'profit-negative';

        return "
            <div class='section'>
                <h2 class='section-title'>📊 UMUMIY XULOSA</h2>
            
                <div class='summary-grid'>
                    <div class='summary-info'>
                        <h3>🗓️ Davr ma'lumotlari</h3>
                        <div class='info-row'>
                            <span class='info-label'>Boshlanish sanasi:</span>
                            <span class='info-value'>" . ($summary['start_date'] ?? '') . "</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Tugash sanasi:</span>
                            <span class='info-value'>" . ($summary['end_date'] ?? '') . "</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Davr kunlari:</span>
                            <span class='info-value'>{$days} kun</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Dollar kursi:</span>
                            <span class='info-value'>" . number_format($dollar, 0, '.', ' ') . " so'm</span>
                        </div>
                    </div>
            
                    <div class='summary-info'>
                        <h3>💰 Asosiy ko'rsatkichlar</h3>
                        <div class='info-row'>
                            <span class='info-label'>Kunlik o'rtacha daromad:</span>
                            <span class='info-value'>" . $formatUzs($income / $days) . " so'm</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Kunlik o'rtacha foyda:</span>
                            <span class='info-value'>" . $formatUzs($netProfit / $days) . " so'm</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>O'rtacha xodimlar:</span>
                            <span class='info-value'>" . $toInt($summary['average_employee_count'] ?? 0) . " kishi</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Ishlab chiqarildi:</span>
                            <span class='info-value'>" . $toInt($summary['total_output_quantity'] ?? 0) . " dona</span>
                        </div>
                    </div>
                </div>
            </div>";
    }

    protected function generateDailySection(array $daily): string
    {
        if (empty($daily)) {
            return "<div class='section'><h2 class='section-title'>📅 KUNLIK HISOBOT</h2><p style='padding: 20px;'>Ma'lumot topilmadi.</p></div>";
        }

        $tableRows = '';
        $totals = [
            'aup' => 0, 'kpi' => 0, 'transport_attendance' => 0, 'tarification' => 0,
            'daily_expenses' => 0, 'total_earned_uzs' => 0, 'total_fixed_cost_uzs' => 0,
            'net_profit_uzs' => 0, 'employee_count' => 0, 'total_output_quantity' => 0
        ];

        foreach ($daily as $d) {
            $netProfit = (int)($d['net_profit_uzs'] ?? 0);
            $profitClass = $netProfit >= 0 ? 'profit-positive' : 'profit-negative';

            $tableRows .= "
            <tr>
                <td>" . date('d.m.Y', strtotime($d['date'] ?? '')) . "</td>
                <td class='number'>" . number_format($d['aup'] ?? 0, 0, '.', ' ') . "</td>
                <td class='number'>" . number_format($d['kpi'] ?? 0, 0, '.', ' ') . "</td>
                <td class='number'>" . number_format($d['transport_attendance'] ?? 0, 0, '.', ' ') . "</td>
                <td class='number'>" . number_format($d['tarification'] ?? 0, 0, '.', ' ') . "</td>
                <td class='number'>" . number_format($d['daily_expenses'] ?? 0, 0, '.', ' ') . "</td>
                <td class='number'>" . number_format($d['total_earned_uzs'] ?? 0, 0, '.', ' ') . "</td>
                <td class='number'>" . number_format($d['total_fixed_cost_uzs'] ?? 0, 0, '.', ' ') . "</td>
                <td class='number {$profitClass}'>" . number_format($netProfit, 0, '.', ' ') . "</td>
                <td class='number'>" . ($d['employee_count'] ?? 0) . "</td>
                <td class='number'>" . ($d['total_output_quantity'] ?? 0) . "</td>
            </tr>";

            foreach ($totals as $key => $value) {
                $totals[$key] += $d[$key] ?? 0;
            }
        }

        $count = count($daily);
        $averages = array_map(fn($total) => $count > 0 ? round($total / $count) : 0, $totals);

        return "
        <div class='section'>
            <h2 class='section-title'>📅 KUNLIK HISOBOT</h2>
            <table>
                <thead>
                    <tr>
                        <th>Sana</th>
                        <th>AUP<br>(so'm)</th>
                        <th>KPI<br>(so'm)</th>
                        <th>Transport<br>(so'm)</th>
                        <th>Tarifikatsiya<br>(so'm)</th>
                        <th>Kunlik xarajat<br>(so'm)</th>
                        <th>Jami daromad<br>(so'm)</th>
                        <th>Doimiy xarajat<br>(so'm)</th>
                        <th>Sof foyda<br>(so'm)</th>
                        <th>Xodimlar<br>soni</th>
                        <th>Jami qty</th>
                    </tr>
                </thead>
                <tbody>
                    {$tableRows}
                    <tr class='total-row'>
                        <td><strong>JAMI:</strong></td>
                        <td class='number'>" . number_format($totals['aup'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($totals['kpi'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($totals['transport_attendance'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($totals['tarification'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($totals['daily_expenses'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($totals['total_earned_uzs'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($totals['total_fixed_cost_uzs'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($totals['net_profit_uzs'], 0, '.', ' ') . "</td>
                        <td class='number'>" . $totals['employee_count'] . "</td>
                        <td class='number'>" . $totals['total_output_quantity'] . "</td>
                    </tr>
                    <tr class='average-row'>
                        <td><strong>O'RTACHA:</strong></td>
                        <td class='number'>" . number_format($averages['aup'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($averages['kpi'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($averages['transport_attendance'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($averages['tarification'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($averages['daily_expenses'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($averages['total_earned_uzs'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($averages['total_fixed_cost_uzs'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($averages['net_profit_uzs'], 0, '.', ' ') . "</td>
                        <td class='number'>" . $averages['employee_count'] . "</td>
                        <td class='number'>" . $averages['total_output_quantity'] . "</td>
                    </tr>
                </tbody>
            </table>
        </div>";
    }

    protected function generateOrdersSection(array $orders): string
    {
        if (empty($orders)) {
            return "<div class='section'><h2 class='section-title'>🛍️ BUYURTMALAR</h2><p style='padding: 20px;'>Buyurtmalar topilmadi.</p></div>";
        }

        $tableRows = '';
        $totals = [
            'price_usd' => 0, 'price_uzs' => 0, 'total_quantity' => 0, 'rasxod_limit_uzs' => 0,
            'bonus' => 0, 'tarification' => 0, 'total_fixed_cost_uzs' => 0, 'total_output_cost_uzs' => 0,
            'net_profit_uzs' => 0, 'cost_per_unit_uzs' => 0, 'profit_per_unit_uzs' => 0, 'profitability_percent' => 0
        ];

        foreach ($orders as $order) {
            $orderData = $order['order'] ?? [];
            $model = $order['model'] ?? [];
            $submodels = implode(', ', array_map(fn($s) => $s['name'] ?? '', $order['submodels'] ?? []));
            $responsibleUsers = implode(', ', array_map(fn($u) => $u['employee']['name'] ?? '', $order['responsibleUser'] ?? []));

            $netProfit = (int)($order['net_profit_uzs'] ?? 0);
            $profitClass = $netProfit >= 0 ? 'profit-positive' : 'profit-negative';

            $tableRows .= "
            <tr>
                <td>" . ($orderData['id'] ?? '') . "</td>
                <td>" . (strlen($orderData['name'] ?? '') > 20 ? substr($orderData['name'] ?? '', 0, 20) . '...' : ($orderData['name'] ?? '')) . "</td>
                <td>" . ($model['name'] ?? '') . "</td>
                <td>" . (strlen($submodels) > 15 ? substr($submodels, 0, 15) . '...' : $submodels) . "</td>
                <td>" . (strlen($responsibleUsers) > 15 ? substr($responsibleUsers, 0, 15) . '...' : $responsibleUsers) . "</td>
                <td class='number'>" . number_format($order['price_usd'] ?? 0, 2) . "</td>
                <td class='number'>" . number_format($order['price_uzs'] ?? 0, 0, '.', ' ') . "</td>
                <td class='number'>" . ($order['total_quantity'] ?? 0) . "</td>
                <td class='number'>" . number_format($order['rasxod_limit_uzs'] ?? 0, 0, '.', ' ') . "</td>
                <td class='number'>" . number_format($order['bonus'] ?? 0, 0, '.', ' ') . "</td>
                <td class='number'>" . number_format($order['tarification'] ?? 0, 0, '.', ' ') . "</td>
                <td class='number'>" . number_format($order['total_fixed_cost_uzs'] ?? 0, 0, '.', ' ') . "</td>
                <td class='number'>" . number_format($order['total_output_cost_uzs'] ?? 0, 0, '.', ' ') . "</td>
                <td class='number {$profitClass}'>" . number_format($netProfit, 0, '.', ' ') . "</td>
                <td class='number'>" . number_format($order['cost_per_unit_uzs'] ?? 0, 0, '.', ' ') . "</td>
                <td class='number'>" . number_format($order['profit_per_unit_uzs'] ?? 0, 0, '.', ' ') . "</td>
                <td class='number'>" . number_format($order['profitability_percent'] ?? 0, 2) . "%</td>
            </tr>";

            foreach ($totals as $key => $value) {
                $totals[$key] += $order[$key] ?? 0;
            }
        }

        $count = count($orders);

        return "
        <div class='section'>
            <h2 class='section-title'>🛍️ BUYURTMALAR</h2>
            <table style='font-size: 8px;'>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nomi</th>
                        <th>Model</th>
                        <th>Submodel</th>
                        <th>Mas'ul</th>
                        <th>Narx<br>USD</th>
                        <th>Narx<br>so'm</th>
                        <th>Qty</th>
                        <th>Rasxod limiti<br>(so'm)</th>
                        <th>Bonus<br>(so'm)</th>
                        <th>Tarifikatsiya<br>(so'm)</th>
                        <th>Doimiy xarajat<br>(so'm)</th>
                        <th>Jami tannarx<br>(so'm)</th>
                        <th>Sof foyda<br>(so'm)</th>
                        <th>Bir dona<br>tannarxi</th>
                        <th>Bir dona<br>foyda</th>
                        <th>Rentabellik<br>%</th>
                    </tr>
                </thead>
                <tbody>
                    {$tableRows}
                    <tr class='total-row'>
                        <td colspan='5'><strong>JAMI:</strong></td>
                        <td class='number'>" . number_format($totals['price_usd'], 2) . "</td>
                        <td class='number'>" . number_format($totals['price_uzs'], 0, '.', ' ') . "</td>
                        <td class='number'>" . $totals['total_quantity'] . "</td>
                        <td class='number'>" . number_format($totals['rasxod_limit_uzs'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($totals['bonus'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($totals['tarification'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($totals['total_fixed_cost_uzs'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($totals['total_output_cost_uzs'], 0, '.', ' ') . "</td>
                        <td class='number'>" . number_format($totals['net_profit_uzs'], 0, '.', ' ') . "</td>
                        <td class='number'>-</td>
                        <td class='number'>-</td>
                        <td class='number'>" . ($count > 0 ? number_format($totals['profitability_percent'] / $count, 2) : '0.00') . "%</td>
                    </tr>
                </tbody>
            </table>
        </div>";
    }

    protected function generateCostTypesSection(array $orders): string
    {
        if (empty($orders)) {
            return "<div class='section'><h2 class='section-title'>💰 XARAJAT TURLARI</h2><p style='padding: 20px;'>Ma'lumot topilmadi.</p></div>";
        }

        $costTotals = [];

        // Har bir order ichidan costs_uzs ni yig'amiz
        foreach ($orders as $order) {
            if (!empty($order['costs_uzs']) && is_array($order['costs_uzs'])) {
                foreach ($order['costs_uzs'] as $type => $amount) {
                    if (!isset($costTotals[$type])) {
                        $costTotals[$type] = 0;
                    }
                    $costTotals[$type] += $amount;
                }
            }
        }

        $costItems = '';
        $grandTotal = 0;

        foreach ($costTotals as $type => $amount) {
            $translatedType = $this->translateCostType($type);
            $costItems .= "
            <div class='cost-item'>
                <span class='cost-label'>{$translatedType}</span>
                <span class='cost-amount'>" . number_format(round($amount), 0, '.', ' ') . " so'm</span>
            </div>";
            $grandTotal += $amount;
        }

        return "
            <div class='section'>
                <h2 class='section-title'>💰 XARAJAT TURLARI</h2>
                <div style='display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px;'>
                    <div style='flex: 1 1 48%;'>
                        {$costItems}
                    </div>
                    <div style='flex: 1 1 48%;'>
                        <div class='grand-total'>
                            <div>JAMI XARAJAT</div>
                            <div style='font-size: 18px; margin-top: 10px;'>" . number_format(round($grandTotal), 0, '.', ' ') . " so'm</div>
                        </div>
                    </div>
                </div>
            </div>";

    }

    /**
     * Xarajat turlarini odamga tushunarli nomga o'tkazish
     */
    protected function translateCostType(string $key): string
    {
        $map = [
            'allocatedAup' => "AUP",
            'bonus' => "KPI",
            'total_fixed_cost_uzs' => "O'zgarmas xarajat",
            'allocatedTransport' => "Transport",
            'amortizationExpense' => "Amortizatsiya",
            'incomePercentageExpense' => "Soliq",
            'tarification' => "Tikuv uchun",
            'remainder' => "Tikuvchilar",
        ];

        return $map[$key] ?? $key;
    }
}