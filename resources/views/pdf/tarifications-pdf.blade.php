<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; }
        h2 { text-align: center; margin-bottom: 10px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .header div { font-size: 10pt; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
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
        .barcode {
            margin-top: 3px;
        }
    </style>
</head>
<body>

<h2>📋 Tarifikatsiya Ro'yxati</h2>

<div class="header">
    <div>📦 Buyurtma: {{ $submodel->orderModel->order->id ?? '-' }}</div>
    <div>🧵 Model: {{ $submodel->orderModel->model->name ?? '-' }}</div>
    <div>{{ $submodel->submodel->name }}</div>
</div>

<table>
    <thead>
    <tr>
        <th>No</th>
        <th>Razmer</th>
        <th>Operatsiya nomi</th>
        <th>Soni</th>
        <th>Narxi</th>
        <th>Sekund</th>
        <th>Operatsiya kodi</th>
        <th>Shtrix kod</th>
    </tr>
    </thead>
    <tbody>
    @php $i = 1; @endphp
    @foreach($submodel->tarificationCategories as $category)
        @foreach($category->tarifications as $tarification)
            <tr>
                <td>{{ $i++ }}</td>
                <td>{{ request()->input('size') }}</td>
                <td>{{ $tarification->name }}</td>
                <td>{{ request()->input('quantity') }}</td>
                <td>{{ number_format($tarification->summa, 0, ',', ' ') }}</td>
                <td>{{ $tarification->second }}</td>
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
