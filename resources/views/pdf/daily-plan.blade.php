<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: 50mm 80mm portrait;
            margin: 0;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            margin: 0;
            padding: 2px 4px;
            width: 100%;
        }

        .page {
            page-break-after: always;
            padding: 2px 2px 5px 2px;
        }

        .header {
            text-align: center;
            font-weight: bold;
            margin-bottom: 6px;
            font-size: 10px;
        }

        .info {
            margin-bottom: 5px;
            font-size: 9px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        table th, table td {
            border: 1px solid #000;
            padding: 2px;
            text-align: left;
            font-size: 8px;
        }

        table th {
            background-color: #f0f0f0;
        }

        .summary {
            margin-top: 5px;
            font-weight: bold;
            font-size: 9px;
        }

        .footer {
            margin-top: 6px;
            font-size: 7px;
            text-align: center;
            border-top: 1px dashed #000;
            padding-top: 3px;
        }
    </style>
</head>
<body>

@foreach($plans as $plan)
    <div class="page">
        <div class="header">👷 Kunlik Ish Rejasi</div>

        <div class="info">
            <strong>Xodim:</strong> {{ $plan['employee_name'] }}<br>
            <strong>Rejalashtirilgan daqiqa:</strong> {{ $plan['used_minutes'] }}<br>
            <strong>Umumiy summa:</strong> {{ number_format($plan['total_earned'], 0, ',', ' ') }} so'm
        </div>

        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Ish nomi</th>
                <th>Kodi</th>
                <th>Narx</th>
                <th>Soni</th>
                <th>Summa</th>
                <th>Bajarildi</th>
            </tr>
            </thead>
            <tbody>
            @php
                $totalCount = 0;
                $totalMinutes = 0;
            @endphp
            @foreach($plan['tarifications'] as $index => $tar)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $tar['tarification_name'] }}</td>
                    <td>{{ $tar['code'] ?? '-' }}</td>
                    <td>{{ number_format($tar['sum'], 0, ',', ' ') }}</td>
                    <td>{{ $tar['count'] }}</td>
                    <td>{{ number_format($tar['amount_earned'], 0, ',', ' ') }}</td>
                    <td></td>
                </tr>
                @php
                    $totalCount += $tar['count'];
                    $totalMinutes += $tar['total_minutes'];
                @endphp
            @endforeach
            <tr>
                <td colspan="4"><strong>JAMI</strong></td>
                <td><strong>{{ $totalCount }}</strong></td>
                <td><strong>{{ number_format($plan['total_earned'], 0, ',', ' ') }}</strong></td>
                <td></td>
            </tr>
            </tbody>
        </table>

        <div class="footer">
            Sana: {{ now()->format('d.m.Y') }}<br>
            Imzo: ___________________
        </div>
    </div>
@endforeach

</body>
</html>
