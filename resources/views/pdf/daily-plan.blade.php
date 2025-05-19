<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5px;
            margin: 0;
            padding: 0;
            width: 80mm;
        }

        .page {
            page-break-after: always;
            padding: 3mm 3mm 5mm 3mm;
        }

        .header {
            text-align: center;
            font-weight: bold;
            margin-bottom: 4px;
            font-size: 10px;
        }

        .employee-info {
            margin-bottom: 2mm;
        }

        .summary {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            margin-bottom: 3mm;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }

        th, td {
            border: 1px solid #000;
            padding: 1mm;
            text-align: left;
            word-break: break-word;
        }

        th {
            background-color: #efefef;
        }

        .total-row {
            font-weight: bold;
            background-color: #e0e0e0;
        }

        .footer {
            margin-top: 3mm;
            font-size: 8px;
            text-align: center;
        }
    </style>
</head>
<body>

@foreach($plans as $plan)
    <div class="page">
        <div class="header">👷 Kunlik Ish Rejasi</div>

        <div class="employee-info">
            <strong>Xodim:</strong> {{ $plan['employee_name'] }}
        </div>

        <div class="summary">
            <div>⏱ {{ $plan['used_minutes'] }} daqiqa</div>
            <div>💰 {{ number_format($plan['total_earned'], 0, ',', ' ') }} so'm</div>
        </div>

        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Ish nomi</th>
                <th>Kodi</th>
                <th>Narx</th>
                <th>Soni</th>
                <th>Jami</th>
                <th>✔</th>
            </tr>
            </thead>
            <tbody>
            @php
                $totalCount = 0;
                $totalSum = 0;
                $totalMinutes = 0;
            @endphp
            @foreach($plan['tarifications'] as $index => $tar)
                @php
                    $totalCount += $tar['count'];
                    $totalSum += $tar['amount_earned'];
                    $totalMinutes += $tar['total_minutes'];
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $tar['tarification_name'] }}</td>
                    <td>{{ $tar['code'] ?? '-' }}</td>
                    <td>{{ number_format($tar['sum'], 0, ',', ' ') }}</td>
                    <td>{{ $tar['count'] }}</td>
                    <td>{{ number_format($tar['amount_earned'], 0, ',', ' ') }}</td>
                    <td></td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">JAMI</td>
                <td>-</td>
                <td>-</td>
                <td>{{ $totalCount }}</td>
                <td>{{ number_format($totalSum, 0, ',', ' ') }}</td>
                <td></td>
            </tr>
            </tbody>
        </table>

        <div class="footer">
            Sana: {{ now()->format('d.m.Y') }} | Imzo: ____________________
        </div>
    </div>
@endforeach

</body>
</html>
