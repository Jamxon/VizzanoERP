<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            margin: 0;
            padding: 0;
        }
        h2 {
            text-align: center;
            margin: 10px 0;
        }

        .order-info {
            width: 100%;
            font-size: 10pt;
            margin: 0 0 5px 0;
            border: none;
            border-collapse: collapse;
        }
        .order-info td {
            border: none;
            padding: 2px 4px;
        }

        table.main-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        table.main-table td {
            border: 1px solid #000;
            text-align: center;
            vertical-align: middle;
            padding: 4px;
        }
        .th-style {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .barcode {
            margin-top: 3px;
        }
    </style>
</head>
<body>
<table class="order-info">
    <tr>
        <td><strong>Buyurtma:</strong> {{ $submodel->orderModel->order->id ?? '-' }}</td>
        <td><strong>Model:</strong> {{ $submodel->orderModel->model->name ?? '-' }}</td>
        <td><strong></strong> {{ $submodel->submodel->name ?? '-' }}</td>
    </tr>
</table>

<table class="main-table">
    <tbody>
    @php $i = 1; @endphp
    @foreach($submodel->tarificationCategories as $category)
        @foreach($category->tarifications as $tarification)
            @if($i === 1)
                <tr class="th-style">
                    <td>No</td>
                    <td>Razmer</td>
                    <td>Operatsiya nomi</td>
                    <td>Soni</td>
                    <td>Sekund</td>
                    <td>Narxi</td>
                    <td>Operatsiya kodi</td>
                    <td>Shtrix kod</td>
                </tr>
            @endif
            <tr>
                <td>{{ $i++ }}</td>
                <td>{{ request()->input('size') }}</td>
                <td>{{ $tarification->name }}</td>
                <td>{{ request()->input('quantity') }}</td>
                <td>{{ $tarification->second }}</td>
                <td>{{ number_format($tarification->summa, 0, ',', ' ') }}</td>
                <td>{{ $tarification->code }}</td>
                <td class="barcode">
                    <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG((string) $tarification->code, 'C128', 1.2, 30) }}" alt="barcode">
                </td>
            </tr>
        @endforeach
    @endforeach
    </tbody>
</table>
</body>
</html>
