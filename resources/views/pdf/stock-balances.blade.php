<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ombor Holati</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #444; padding: 5px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
<h2>Ombor Holati ({{ now()->format('Y-m-d') }})</h2>
<table>
    <thead>
    <tr>
        <th>#</th>
        <th>Mahsulot</th>
        <th>Tur</th>
        <th>Rang</th>
        <th>Lot</th>
        <th>Birlik</th>
        <th>Valyuta</th>
        <th>Ombor</th>
        <th>Miqdori</th>
    </tr>
    </thead>
    <tbody>
    @foreach($stockBalances as $index => $stock)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $stock->item->name }}</td>
            <td>{{ $stock->item->type->name ?? '-' }}</td>
            <td>{{ $stock->item->color->name ?? '-' }}</td>
            <td>{{ $stock->item->lot ?? '-' }}</td>
            <td>{{ $stock->item->unit->name ?? '-' }}</td>
            <td>{{ $stock->item->currency->code ?? '-' }}</td>
            <td>{{ $stock->warehouse->name ?? '-' }}</td>
            <td>{{ $stock->quantity }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
