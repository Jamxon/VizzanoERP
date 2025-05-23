<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            margin: 0;
            padding: 0 !important;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        th, td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
            vertical-align: middle;
        }

        th {
            background-color: #f2f2f2;
        }

        .header-table td {
            border: none;
            padding: 4px 6px;
            font-size: 10pt;
        }

        .barcode {
            margin-top: 4px;
        }
    </style>
</head>
<body>

{{-- Header satr --}}
<table class="header-table">
    <tr>
        <td><strong>Buyurtma:</strong> {{ $box->submodel->orderModel->order->name ?? '-' }}</td>
        <td><strong>Model:</strong> {{ $box->submodel->orderModel->model->name ?? '-' }}</td>
        <td><strong></strong> {{ $box->submodel->submodel->name ?? '-' }}</td>
        <td><strong>Sana:</strong> {{ now()->format('d.m.Y') }}</td>
    </tr>
</table>

{{-- Yagona tarifikatsiya satri --}}
<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Razmer</th>
        <th>Operatsiya nomi</th>
        <th>Soni</th>
        <th>Sekund</th>
        <th>Narxi</th>
        <th>Kod</th>
        <th>Shtrix kod</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>{{ $box->id }}</td>
        <td>{{ $box->size->size->name }}</td>
        <td style="text-align: left;">{{ $box->tarification->name ?? '-' }}</td>
        <td>{{ $box->quantity }}</td>
        <td>{{ $box->tarification->second ?? '-' }}</td>
        <td>{{ number_format($box->tarification->summa, 0, ',', ' ') }}</td>
        <td>{{ $box->tarification->code ?? '-' }}</td>
        <td class="barcode">
            <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG('A' . (string) $box->id, 'C128', 1.0, 30) }}" alt="barcode">
        </td>
    </tr>
    </tbody>
</table>

</body>
</html>
