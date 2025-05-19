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
    <div class="employee">
        👤 <strong>{{ $plan['employee_name'] }}</strong><br>
        🕒: {{ $plan['used_minutes'] }} daqiqa<br>
        💰: {{ number_format($plan['total_earned'], 2, '.', '') }} so'm

        @foreach($plan['tarifications'] as $t)
            <div class="task">
                - {{ \Illuminate\Support\Str::limit($t['tarification_name'], 40) }}<br>
                💼 {{ $t['count'] }} dona | ⏱ {{ $t['total_minutes'] }} daq | 💵 {{ number_format($t['amount_earned'], 2) }} so'm
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
