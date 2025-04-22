<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Kirim hujjati</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 6px; text-align: left; }
        th { background-color: #f0f0f0; }
    </style>
</head>
<body>
<h2>Kirim hujjati - ID: {{ $entry->id }}</h2>
<p><strong>Ombor:</strong> {{ $entry->warehouse->name ?? '-' }}</p>
<p><strong>Manba:</strong> {{ $entry->source->name ?? '-' }}</p>
<p><strong>Izoh:</strong> {{ $entry->comment }}</p>
<p><strong>Foydalanuvchi:</strong> {{ $entry->user->employee->name ?? '-' }}</p>
<p><strong>Sana:</strong> {{ $entry->created_at->format('d.m.Y H:i') }}</p>

<table>
    <thead>
    <tr>
        <th>#</th>
        <th>Mahsulot</th>
        <th>Miqdor</th>
        <th>Narx</th>
        <th>Valyuta</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($entry->items as $i => $item)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $item->item->name ?? '-' }}</td>
            <td>{{ $item->quantity }}</td>
            <td>{{ number_format($item->price, 2) }}</td>
            <td>{{ $item->currency->name ?? '-' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
