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
            padding-bottom: 5px;
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
        }

        table th {
            background-color: #f0f0f0;
        }

        .summary {
            margin-top: 5px;
            font-weight: bold;
            font-size: 9px;
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
                <th>Ish kodi</th>
                <th>Dona narxi</th>
                <th>Reja</th>
                <th>Jami so'm</th>
                <th>Bajarildi</th>
            </tr>
            </thead>
            <tbody>
            @foreach($plan['tarifications'] as $index => $tar)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $tar['tarification_name'] }}</td>
                    <td>{{ $tar['code'] }}</td>
                    <td>{{ number_format($tar['sum'], 0, ',', ' ') }}</td>
                    <td>{{ $tar['count'] }}</td>
                    <td>{{ number_format($tar['amount_earned'], 0, ',', ' ') }}</td>
                    <td></td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="summary">
            ✅ Ushbu rejani to‘liq bajarsa: <br>
            {{ $plan['used_minutes'] }} daqiqa ishlaysiz va <strong>{{ number_format($plan['total_earned'], 0, ',', ' ') }} so'm</strong> topasiz.
        </div>
    </div>
@endforeach

</body>
</html>
