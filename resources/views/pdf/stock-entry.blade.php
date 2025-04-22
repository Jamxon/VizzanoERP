<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Stock Entry PDF</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h2, h3 { margin-bottom: 10px; }
        p { margin: 2px 0; }
        .section { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; }
        th { background-color: #f0f0f0; }
    </style>
</head>
<body>

<h2>{{ $entry->type === 'incoming' ? 'Kirim' : 'Chiqim' }} Hujjati</h2>

<div class="section">
    <p><strong>ID:</strong> {{ $entry->id }}</p>
    <p><strong>{{ $entry->type === 'incoming' ? 'Kirim' : 'Chiqim' }} bo'lgan sana:</strong> {{ $entry->created_at->format('d.m.Y H:i') }}</p>
    <p><strong>Ombor:</strong> {{ $entry->warehouse->name ?? '-' }}</p>
    <p><strong>Izoh:</strong> {{ $entry->comment }}</p>
    <p><strong>Buyurtma ID:</strong> {{ $entry->order_id }}</p>
    <p><strong>Manba:</strong> {{ $entry->source->name ?? '-' }}</p>
    <p><strong>Manzil:</strong> {{ $entry->destination->name ?? '-' }}</p>
    <p><strong>Foydalanuvchi:</strong> {{ $entry->employee->name ?? '-' }}</p>
    <p><strong>Kontragent:</strong> {{ $entry->contragent->name ?? '-' }}</p>
</div>

@php
    $totals = [];
@endphp

<div class="section">
    <h3>Kiritilgan Mahsulotlar</h3>
    <table>
        <thead>
        <tr>
            <th>#</th>
            <th>Mahsulot</th>
            <th>Miqdor</th>
            <th>Narx</th>
            <th>Valyuta</th>
            <th>Summa</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($entry->items as $i => $item)
            @php
                $amount = $item->quantity * $item->price;
                $currencyName = $item->currency->name ?? $item->currency_id;
                if (!isset($totals[$currencyName])) {
                    $totals[$currencyName] = 0;
                }
                $totals[$currencyName] += $amount;
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->item->name ?? $item->item_id }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ number_format($item->price, 2) }}</td>
                <td>{{ $currencyName }}</td>
                <td>{{ number_format($amount, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="section">
    <h3>Umumiy Summa (valyutalar bo‘yicha)</h3>
    <table>
        <thead>
        <tr>
            <th>Valyuta</th>
            <th>Jami</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($totals as $currency => $total)
            <tr>
                <td>{{ $currency }}</td>
                <td>{{ number_format($total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

</body>
</html>
