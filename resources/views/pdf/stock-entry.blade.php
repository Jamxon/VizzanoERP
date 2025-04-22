<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Stock Entry PDF</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h2 { margin-bottom: 5px; }
        p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; }
        th { background-color: #eee; }
    </style>
</head>
<body>
<h2>Kirim Hujjati (Stock Entry)</h2>

<p><strong>ID:</strong> {{ $entry->id }}</p>
<p><strong>Yaratilgan sana:</strong> {{ $entry->created_at }}</p>
<p><strong>Ombor ID:</strong> {{ $entry->warehouse_id }}</p>
<p><strong>Turi:</strong> {{ $entry->type }}</p>
<p><strong>Izoh:</strong> {{ $entry->comment }}</p>
<p><strong>Buyurtma ID:</strong> {{ $entry->order_id }}</p>
<p><strong>Yangilangan sana:</strong> {{ $entry->updated_at }}</p>
<p><strong>Manba ID:</strong> {{ $entry->source_id }}</p>
<p><strong>Manzil ID:</strong> {{ $entry->destination_id }}</p>
<p><strong>Foydalanuvchi ID:</strong> {{ $entry->user_id }}</p>
<p><strong>Kontragent ID:</strong> {{ $entry->contragent_id }}</p>

<h3 style="margin-top: 20px;">Kiritilgan Mahsulotlar</h3>
<table>
    <thead>
    <tr>
        <th>#</th>
        <th>Item ID</th>
        <th>Miqdor</th>
        <th>Narx</th>
        <th>Valyuta ID</th>
        <th>Yaratilgan</th>
        <th>Yangilangan</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($entry->items as $i => $item)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $item->item_id }}</td>
            <td>{{ $item->quantity }}</td>
            <td>{{ number_format($item->price, 2) }}</td>
            <td>{{ $item->currency_id }}</td>
            <td>{{ $item->created_at }}</td>
            <td>{{ $item->updated_at }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
