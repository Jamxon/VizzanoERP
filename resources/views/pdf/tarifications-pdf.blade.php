<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            margin: 0;
            padding: 15px;
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

        .page-break {
            page-break-before: always;
        }

        .barcode {
            margin-top: 4px;
        }
    </style>
</head>
<body>

@foreach($boxes as $index => $box)
    @if($index > 0)
        <div class="page-break"></div>
    @endif

    {{-- Header satr: Order, Model, Submodel, Quti raqami, Sana --}}
    <table class="header-table">
        <tr>
            <td><strong>Buyurtma:</strong> {{ $box['submodel']->orderModel->order->name ?? '-' }}</td>
            <td><strong>Model:</strong> {{ $box['submodel']->orderModel->model->name ?? '-' }}</td>
            <td><strong></strong> {{ $box['submodel']->submodel->name ?? '-' }}</td>
            <td><strong>Quti:</strong> #{{ $box['box_number'] }}</td>
            <td><strong>Sana:</strong> {{ now()->format('d.m.Y') }}</td>
        </tr>
    </table>

    {{-- Tarifikatsiya jadvali --}}
    <table>
        <thead>
        <tr>
            <th>No</th>
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
        @php $i = 1; @endphp
        @foreach($box['submodel']->tarificationCategories as $category)
            @foreach($category->tarifications as $tarification)
                <tr>
                    <td>{{ $tarification->box_tarification_id }}</td>
                    <td>{{ $size }}</td>
                    <td style="text-align: left;">{{ $tarification->name }}</td>
                    <td>{{ $box['quantity'] }}</td>
                    <td>{{ $tarification->second }}</td>
                    <td>{{ number_format($tarification->summa, 0, ',', ' ') }}</td>
                    <td>{{ $tarification->code }}</td>
                    <td class="barcode">
                        <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG((string) $tarification->box_tarification_id, 'C128', 1.0, 30) }}" alt="barcode">
                    </td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
@endforeach

</body>
</html>
