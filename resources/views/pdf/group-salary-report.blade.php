<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Group Salary Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #444; padding: 4px; text-align: left; }
        th { background-color: #f0f0f0; }
    </style>
</head>
<body>
<h2>Bo‘lim bo‘yicha ish haqi hisoboti</h2>
@if($startDate && $endDate)
    <p>Davr: {{ $startDate }} - {{ $endDate }}</p>
@endif

@foreach ($data as $group)
    <h3>Guruh: {{ $group['name'] }} (Jami hisoblangan: {{ number_format(collect($group['employees'])->sum('total_earned'), 2) }} so'm)</h3>

    <table>
        <thead>
        <tr>
            <th>#</th>
            <th>F.I.Sh.</th>
            <th>Davomat uchun</th>
            <th>Operatsiya uchun</th>
            <th>Imzo</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($group['employees'] as $index => $employee)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $employee['name'] }}</td>
                <td>
                    {{ number_format($employee['attendance_salary'] + $employee['employee_salary']) }}
                </td>
                <td>
                    {{ number_format($employee['tarification_salary']) }}
                </td>

                <td></td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endforeach
</body>
</html>
