<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: 80mm auto; margin: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8pt;
            margin: 0;
            padding: 0;
            width: 80mm;
        }
        .page {
            page-break-after: always;
            padding: 2px 5px 10px 5px; /* tepadan 5px emas, 2px */
        }

        .footer {
            margin-top: 8px;
            margin-bottom: 12px; /* pastdan ko'proq joy qoldirildi */
            font-size: 6pt;
            border-top: 1px solid black;
            text-align: center;
            padding-top: 4mm;
        }

        .employee-info {
            border-bottom: 1px solid black;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .summary {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid black;
            padding: 1mm 0;
            margin-bottom: 2mm;
            font-size: 7pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        th, td {
            border: 1px solid #000;
            padding: 2px;
        }
        th {
            background-color: #eee;
        }
        td.task-name {
            width: 30mm;
            word-break: break-word;
            padding-top: 15px;
            padding-bottom: 15px;
        }
    </style>
</head>
<body>
@foreach($plans as $plan)
    <div class="page">
        <div class="employee-info">
            <strong>Xodim:</strong> {{ $plan['employee_name'] }} <br>
        </div>

        <div class="summary">
            <div>Umumiy vaqt: {{ $plan['total_minutes'] }} daq</div>
            <div>Umumiy summa: {{ number_format($plan['total_earned'], 0, ',', ' ') }} so'm</div>
        </div>

        <div class="summary">
            <div>Ishga ketadigan vaqt: {{ $plan['used_minutes'] }} daq</div>
            <div>Qo'shimcha vaqt: {{ $plan['total_minutes'] - $plan['used_minutes'] }} daq</div>
        </div>

        <table>
            <thead>
            <tr>
                <th>Ish</th>
                <th>Vaqt</th>
                <th>Narx</th>
                <th>Reja</th>
                <th>Natija</th>
            </tr>
            </thead>
            <tbody>
            @foreach($plan['tarifications'] as $task)
                <tr>
                    <td class="task-name">{{ $task['code'] ?? ' ' . \Illuminate\Support\Str::limit($task['tarification_name'], 50) }}</td>
                    <td><div class="double-cell">
                            <div>
                                {{ number_format($task['seconds'], 0, ',', ' ') }}
                            </div>
                            <hr />
                            <div>
                                {{ number_format($task['seconds'] * $task['count'], 0, ',', ' ') }}
                            </div>
                        </div>
                    </td>
                    <td><div class="double-cell">
                            <div>
                                {{ number_format($task['sum'], 0, ',', ' ') }}
                            </div>
                            <hr />
                            <div>
                                {{ number_format($task['amount_earned'], 0, ',', ' ') }}
                            </div>
                        </div>
                    </td>
                    <td>{{ $task['count'] }}</td>
                    <td style="min-height: 30px"></td> {{-- Natija uchun bo'sh joy --}}
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="footer">
            <br>
            <br>
            Sana: {{ $plan['date'] }} | Imzo: ______________________<br><br>
            <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG((string) $plan['plan_id'], 'C128', 1.5, 40) }}">
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <hr>
        </div>
    </div>
@endforeach
</body>
</html>
