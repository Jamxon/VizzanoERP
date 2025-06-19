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
            padding: 2px 5px 10px 5px;
        }

        .footer {
            margin-top: 20px;
            margin-bottom: 30px;
            font-size: 6pt;
            border-top: 1px solid black;
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding-top: 3mm;
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
            text-align: center;
        }

        th {
            background-color: #eee;
        }

        td.task-name {
            width: 30mm;
            text-align: left;
            word-break: break-word;
            padding-top: 15px;
            padding-bottom: 15px;
        }

        .double-cell {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            gap: 2mm;
        }

        .double-cell hr {
            width: 100%;
            text-align: center;
            border: 0;
            border-top: 1px solid black;
        }

        .barcode {
            text-align: center;
            padding-bottom: 15px;
            padding-top:  25px;
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
            <div>Qo'shimcha vaqt: {{ number_format($plan['total_minutes'] - $plan['used_minutes'], 0, ',', ' ') }} daq</div>
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
            @for($i = 0; $i < 10; $i++) {{-- Har bir ishchi uchun 10 ta bo‘sh qator --}}
            <tr>
                <td class="task-name">&nbsp;</td>
                <td>
                    <div class="double-cell">
                        <div>&nbsp;</div>
                        <hr />
                        <div>&nbsp;</div>
                    </div>
                </td>
                <td>
                    <div class="double-cell">
                        <div>&nbsp;</div>
                        <hr />
                        <div>&nbsp;</div>
                    </div>
                </td>
                <td>&nbsp;</td>
                <td></td>
            </tr>
            @endfor
            </tbody>
        </table>

        <table style="width: 100%; font-size: 6pt; margin-top: 10px;">
            <tr>
                <td style="text-align: left; padding-top: 15px; padding-bottom: 5px">
                    Sana: {{ $plan['date'] }}
                </td>
                <td style="text-align: right; padding-top: 15px; padding-bottom: 5px">
                    Imzo: ______________________
                </td>
            </tr>
        </table>

        <div class="barcode">
            <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG('E' . $plan['employee_id'], 'C128', 1.5, 40) }}">
            <h2>{{ $plan['employee_id'] }}</h2>
            <hr style="margin-top: 40px">
        </div>
    </div>
@endforeach
</body>
</html>
