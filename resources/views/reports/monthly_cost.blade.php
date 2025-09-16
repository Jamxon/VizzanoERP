<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: right; }
        th { background-color: #e8f4fd; color: #2f4f4f; }
        td:first-child, th:first-child { text-align: left; }
        .highlight { background-color: #d4edda; font-weight: bold; }
        .negative { background-color: #f8d7da; font-weight: bold; }
        .section-title { background-color: #f9f9f9; font-weight: bold; text-align: left; }
    </style>
</head>
<body>
<h2>Monthly Report: {{ $summary['start_date'] ?? '' }} - {{ $summary['end_date'] ?? '' }}</h2>

<h3>Summary</h3>
<table>
    <tr>
        <th>Ko'rsatkich</th>
        <th>Qiymat (so'm)</th>
        <th>Qiymat (USD)</th>
        <th>Ulushi (%)</th>
    </tr>
    @foreach($summaryRows as $row)
        @php
            $class = '';
            if($row[0] === 'Sof foyda') $class = $row[1] >= 0 ? 'highlight' : 'negative';
        @endphp
        <tr class="{{ $class }}">
            <td>{{ $row[0] }}</td>
            <td>{{ number_format((float) ($row[1] ?? 0)) }}</td>
            <td>{{ number_format((float) ($row[2] ?? 0), 2) }}</td>
            <td>{{ $row[3] ?? '' }}</td>
        </tr>
    @endforeach
</table>

<h3>Daily Details</h3>
<table>
    <tr>
        <th>Sana</th>
        <th>AUP</th>
        <th>KPI</th>
        <th>Transport</th>
        <th>Tarifikatsiya</th>
        <th>Kunlik xarajatlar</th>
        <th>Jami daromad</th>
        <th>Doimiy xarajat</th>
        <th>Sof foyda</th>
        <th>Xodimlar soni</th>
        <th>Jami ishlab chiqarilgan qty</th>
    </tr>
    @foreach($daily as $d)
        <tr>
            <td>{{ $d['date'] }}</td>
            <td>{{ number_format((float) ($d['aup'] ?? 0)) }}</td>
            <td>{{ number_format((float) ($d['kpi'] ?? 0)) }}</td>
            <td>{{ number_format((float) ($d['transport_attendance'] ?? 0)) }}</td>
            <td>{{ number_format((float) ($d['tarification'] ?? 0)) }}</td>
            <td>{{ number_format((float) ($d['daily_expenses'] ?? 0)) }}</td>
            <td>{{ number_format((float) ($d['total_earned_uzs'] ?? 0)) }}</td>
            <td>{{ number_format((float) ($d['total_fixed_cost_uzs'] ?? 0)) }}</td>
            <td>{{ number_format((float) ($d['net_profit_uzs'] ?? 0)) }}</td>
            <td>{{ $d['employee_count'] }}</td>
            <td>{{ $d['total_output_quantity'] }}</td>
        </tr>
    @endforeach
</table>

<h3>Orders</h3>
<table>
    <tr>
        <th>Buyurtma ID</th>
        <th>Buyurtma nomi</th>
        <th>Model</th>
        <th>Submodel</th>
        <th>Mas’ul</th>
        <th>Narx USD</th>
        <th>Narx so'm</th>
        <th>Jami qty</th>
        <th>Doimiy xarajat</th>
        <th>Sof foyda</th>
    </tr>
    @foreach($orders as $o)
        <tr>
            <td>{{ $o['order']['id'] ?? '' }}</td>
            <td>{{ $o['order']['name'] ?? '' }}</td>
            <td>{{ $o['model']['name'] ?? '' }}</td>
            <td>{{ implode(', ', array_column($o['submodels'] ?? [], 'name')) }}</td>
            <td>{{ implode(', ', array_map(fn($u) => $u['employee']['name'] ?? '', $o['responsibleUser'] ?? [])) }}</td>
            <td>{{ number_format((float) ($o['price_usd'] ?? 0), 2) }}</td>
            <td>{{ number_format((float) ($o['price_uzs'] ?? 0)) }}</td>
            <td>{{ $o['total_quantity'] ?? 0 }}</td>
            <td>{{ number_format((float) ($o['total_fixed_cost_uzs'] ?? 0)) }}</td>
            <td>{{ number_format((float) ($o['net_profit_uzs'] ?? 0)) }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
