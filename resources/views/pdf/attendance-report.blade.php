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
<h2>📋 Davomat Hisoboti ({{ $filter }})</h2>
<p><strong>Sana oralig‘i:</strong> {{ $date_range[0] }} - {{ $date_range[1] }}</p>

<table>
    <thead>
    <tr>
        <th>#</th>
        <th>F.I.Sh</th>
        @if (in_array($filter, ['today', 'yesterday']))
            <th>Holat</th>
        @else
            <th>Kelgan kunlar</th>
            <th>Kelmagan kunlar</th>
        @endif
    </tr>
    </thead>
    <tbody>
    @foreach ($employees as $i => $employee)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $employee['name'] }}</td>
            @if ($employee['status_detail'])
                <td>
                    @foreach ($employee['status_detail'] as $status)
                        {{ $status['date'] }} - {{ $status['status'] }}<br>
                    @endforeach
                </td>
            @else
                <td>{{ $employee['present_count'] }}</td>
                <td>{{ $employee['absent_count'] }}</td>
            @endif
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
