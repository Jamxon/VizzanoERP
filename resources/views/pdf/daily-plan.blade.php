<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0 2px;
            width: 58mm;
        }
        .header {
            text-align: center;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .employee {
            margin-top: 5px;
            border-top: 1px dashed #000;
            padding-top: 3px;
        }
        .task {
            margin-left: 5px;
            margin-bottom: 2px;
        }
        .summary {
            margin-top: 3px;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="header">👷 Kunlik Ish Rejasi</div>
@foreach($plans as $plan)
    <div style="page-break-after: always; font-size: 10px;">
        <strong>Xodim:</strong> {{ $plan['employee_name'] }}<br>
        <strong>Umumiy daqiqa:</strong> {{ $plan['used_minutes'] }}<br>
        <strong>Umumiy summa:</strong> {{ number_format($plan['total_earned'], 0, ',', ' ') }} so'm<br><br>

        @foreach($plan['tarifications'] as $tar)
            <div>
                {{ $tar['tarification_name'] }}<br>
                {{ $tar['count'] }} dona ({{ $tar['total_minutes'] }} daq) -
                {{ number_format($tar['amount_earned'], 0, ',', ' ') }} so'm
            </div>
        @endforeach
    </div>
@endforeach

</body>
</html>
