<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: 80mm auto; margin: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; margin: 0; padding: 0; width: 80mm; }
        .page { page-break-after: always; padding: 5px 5px 10px 5px; }
        .employee-info { border-bottom: 1px solid black; margin-bottom: 5px; font-weight: bold; }
        .summary { margin-bottom: 4px; display: flex; justify-content: space-between; font-size: 7pt; }
        table { width: 100%; border-collapse: collapse; font-size: 7pt; }
        th, td { border: 1px solid #000; padding: 2px; }
        th { background-color: #eee; }
        .footer { margin-top: 8px; font-size: 6pt; border-top: 1px solid black; text-align: center; padding-top: 4mm; }
    </style>
</head>
<body>
@foreach($plans as $plan)
    <div class="page">
        <div class="employee-info">
            👷 <strong>Xodim:</strong> {{ $plan['employee_name'] }} <br>
            🆔 Reja ID: {{ $plan['plan_id'] }}
        </div>

        <div class="summary">
            <div>🕒 Reja vaqti: {{ $plan['used_minutes'] }} daq</div>
            <div>💰 Umumiy summa: {{ number_format($plan['total_earned'], 0, ',', ' ') }} so'm</div>
        </div>

        <table>
            <thead>
            <tr>
                <th>Ish</th>
                <th>Kod</th>
                <th>Narx</th>
                <th>Reja</th>
                <th>Jami</th>
            </tr>
            </thead>
            <tbody>
            @foreach($plan['tarifications'] as $task)
                <tr>
                    <td>{{ $task['tarification_name'] }}</td>
                    <td>{{ $task['code'] }}</td>
                    <td>{{ number_format($task['sum'], 0, ',', ' ') }}</td>
                    <td>{{ $task['count'] }}</td>
                    <td>{{ number_format($task['amount_earned'], 0, ',', ' ') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="footer">
            Sana: {{ $plan['date'] }} | Imzo: ______________________<br>
            <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG((string) $plan['plan_id'], 'C128') }}" height="25">
        </div>
    </div>
@endforeach
</body>
</html>
