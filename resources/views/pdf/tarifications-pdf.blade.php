<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kesish Tarifikatsiyasi - Barcha Qutilar</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
            line-height: 1.4;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }

        .header .info {
            margin-top: 10px;
            font-size: 14px;
            color: #666;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            background-color: #f8f9fa;
        }

        .summary-table th,
        .summary-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .summary-table th {
            background-color: #e9ecef;
            font-weight: bold;
        }

        .box-container {
            margin-bottom: 40px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }

        .box-header {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            font-weight: bold;
            font-size: 14px;
        }

        .box-content {
            padding: 15px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .info-table th,
        .info-table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
            font-size: 11px;
        }

        .info-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            width: 20%;
        }

        .tarification-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .tarification-table th,
        .tarification-table td {
            border: 1px solid #ddd;
            padding: 5px;
            text-align: center;
            font-size: 10px;
        }

        .tarification-table th {
            background-color: #e9ecef;
            font-weight: bold;
        }

        .page-break {
            page-break-before: always;
        }

        .total-row {
            background-color: #fff3cd;
            font-weight: bold;
        }

        .signature-section {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            width: 45%;
            border: 1px solid #ddd;
            padding: 15px;
            text-align: center;
        }

        @media print {
            body {
                margin: 0;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
<!-- HEADER -->
<div class="header">
    <h1>KESISH TARIFIKATSIYASI</h1>
    <div class="info">
        <strong>Sana:</strong> {{ now()->format('d.m.Y H:i') }} |
        <strong>Order ID:</strong> {{ $order_id }} |
        <strong>Jami Qutilar:</strong> {{ $totalBoxes }} |
        <strong>Jami Miqdor:</strong> {{ number_format($totalQuantity) }}
    </div>
</div>

<!-- UMUMIY MA'LUMOTLAR -->
<table class="summary-table">
    <tr>
        <th>Model Nomi</th>
        <td>{{ $submodel->orderModel->model->name ?? '-' }}</td>
        <th>Submodel</th>
        <td>{{ $submodel->submodel->name ?? '-' }}</td>
    </tr>
    <tr>
        <th>Order</th>
        <td>{{ $submodel->orderModel->order->name ?? '-' }}</td>
        <th>O'lcham</th>
        <td>{{ $size }}</td>
    </tr>
    <tr>
        <th>Jami Qutilar</th>
        <td>{{ $totalBoxes }}</td>
        <th>Jami Miqdor</th>
        <td>{{ number_format($totalQuantity) }}</td>
    </tr>
</table>

<!-- HAR BIR QUTI UCHUN MA'LUMOTLAR -->
@foreach($boxes as $index => $box)
    @if($index > 0 && $index % 3 === 0)
        <div class="page-break"></div>
    @endif

    <div class="box-container">
        <div class="box-header">
            QUTI #{{ $box['box_number'] }} - Miqdor: {{ number_format($box['quantity']) }}
        </div>

        <div class="box-content">
            <!-- Quti ma'lumotlari -->
            <table class="info-table">
                <tr>
                    <th>Quti Raqami:</th>
                    <td>{{ $box['box_number'] }}</td>
                    <th>Miqdor:</th>
                    <td>{{ number_format($box['quantity']) }}</td>
                </tr>
                <tr>
                    <th>Model:</th>
                    <td>{{ $box['submodel']->orderModel->model->name ?? '-' }}</td>
                    <th>O'lcham:</th>
                    <td>{{ $box['size'] }}</td>
                </tr>
            </table>

            <!-- Tarifikatsiya ma'lumotlari -->
            @if($box['submodel']->tarificationCategories->count() > 0)
                <table class="tarification-table">
                    <thead>
                    <tr>
                        <th>Kategoriya</th>
                        <th>Razryad</th>
                        <th>Typewriter</th>
                        <th>Ishchi</th>
                        <th>Narx</th>
                        <th>Miqdor</th>
                        <th>Jami</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php $totalPrice = 0; @endphp
                    @foreach($box['submodel']->tarificationCategories as $category)
                        @foreach($category->tarifications as $tarification)
                            @php
                                $itemTotal = $tarification->price * $box['quantity'];
                                $totalPrice += $itemTotal;
                            @endphp
                            <tr>
                                <td>{{ $category->name }}</td>
                                <td>{{ $tarification->razryad->name ?? '-' }}</td>
                                <td>{{ $tarification->typewriter->name ?? '-' }}</td>
                                <td>{{ $tarification->employee->name ?? '-' }}</td>
                                <td>{{ number_format($tarification->price, 2) }}</td>
                                <td>{{ number_format($box['quantity']) }}</td>
                                <td>{{ number_format($itemTotal, 2) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                    <tr class="total-row">
                        <td colspan="6"><strong>JAMI:</strong></td>
                        <td><strong>{{ number_format($totalPrice, 2) }}</strong></td>
                    </tr>
                    </tbody>
                </table>
            @else
                <p style="text-align: center; color: #666; font-style: italic;">
                    Bu quti uchun tarifikatsiya ma'lumotlari topilmadi
                </p>
            @endif
        </div>
    </div>
@endforeach

<!-- IMZO BO'LIMI -->
<div class="page-break"></div>
<div class="signature-section">
    <div class="signature-box">
        <p><strong>Mas'ul shaxs:</strong></p>
        <br><br>
        <p>Imzo: ________________</p>
        <p>F.I.O: ________________</p>
        <p>Sana: {{ now()->format('d.m.Y') }}</p>
    </div>

    <div class="signature-box">
        <p><strong>Nazoratchi:</strong></p>
        <br><br>
        <p>Imzo: ________________</p>
        <p>F.I.O: ________________</p>
        <p>Sana: {{ now()->format('d.m.Y') }}</p>
    </div>
</div>

<!-- OXIRGI SAHIFA - UMUMIY HISOBOT -->
<div class="page-break"></div>
<div class="header">
    <h1>UMUMIY HISOBOT</h1>
</div>

<table class="summary-table">
    <tr>
        <th>Jami Qutilar Soni</th>
        <td>{{ $totalBoxes }}</td>
    </tr>
    <tr>
        <th>Jami Mahsulot Miqdori</th>
        <td>{{ number_format($totalQuantity) }}</td>
    </tr>
    <tr>
        <th>Yaratilgan Sana</th>
        <td>{{ now()->format('d.m.Y H:i:s') }}</td>
    </tr>
    @php
        $grandTotal = 0;
        foreach($boxes as $box) {
            foreach($box['submodel']->tarificationCategories as $category) {
                foreach($category->tarifications as $tarification) {
                    $grandTotal += $tarification->price * $box['quantity'];
                }
            }
        }
    @endphp
    <tr class="total-row">
        <th>JAMI SUMMA</th>
        <td><strong>{{ number_format($grandTotal, 2) }}</strong></td>
    </tr>
</table>

<!-- QR Code yoki shtrix kod qo'shish mumkin -->
<div style="text-align: center; margin-top: 30px;">
    <p style="font-size: 10px; color: #999;">
        Bu hisobot avtomatik tarzda yaratilgan | {{ now()->format('d.m.Y H:i:s') }}
    </p>
</div>
</body>
</html>