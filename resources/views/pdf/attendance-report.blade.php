<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Davomat Hisoboti</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; }
    </style>
</head>
<body>
<h2>📋 Davomat Hisoboti</h2>
<p><strong>Sana oralig‘i:</strong> {{ $date_range[0] }} - {{ $date_range[1] }}</p>

<table>
    <thead>
    <tr>
        <th>#</th>
        <th>F.I.Sh</th>
        <th>Davomat Tafsiloti</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($employees as $i => $employee)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $employee['name'] }}</td>
            <td>
                @foreach ($employee['status_detail'] as $status)
                    {{ $status['date'] }} - &#10005; {{ $status['status'] }}<br>
                @endforeach
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
