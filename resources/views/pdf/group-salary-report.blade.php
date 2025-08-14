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
    @php
        // Faqat summasi 0 bo'lmagan xodimlarni olish
        $filteredEmployees = collect($group['employees'])->filter(function($employee) {
            if ($employee['payment_type'] === 'piece_work') {
                return ($employee['tarification_salary'] + $employee['employee_salary']) > 0;
            } else {
                return ($employee['attendance_salary'] + $employee['employee_salary']) > 0;
            }
        })->values();
    @endphp

    @if($filteredEmployees->count() > 0)
        <h3>
            Guruh: {{ $group['name'] }}
            (Jami hisoblangan: {{ number_format($filteredEmployees->sum('total_earned')) }} so'm)
        </h3>

        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>F.I.Sh.</th>
                <th>Topgan puli</th>
                <th>Imzo</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($filteredEmployees as $index => $employee)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $employee['name'] }}</td>
                    <td>
                        @if($employee['payment_type'] === 'piece_work')
                            {{ number_format($employee['tarification_salary'] + $employee['employee_salary']) }}
                        @else
                            {{ number_format($employee['attendance_salary'] + $employee['employee_salary']) }}
                        @endif
                    </td>
                    <td></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endforeach
</body>
</html>
