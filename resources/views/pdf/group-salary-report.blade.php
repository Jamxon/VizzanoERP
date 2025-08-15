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
        // Piece work ishchilar
        $pieceWorkEmployees = collect($group['employees'])->filter(function($employee) {
            return $employee['payment_type'] === 'piece_work' &&
                   ($employee['tarification_salary'] + $employee['employee_salary']) > 0;
        })->values();

        // Oylik ishchilar
        $monthlyEmployees = collect($group['employees'])->filter(function($employee) {
            return $employee['payment_type'] !== 'piece_work' &&
                   ($employee['attendance_salary'] + $employee['employee_salary']) > 0;
        })->values();
    @endphp

    {{-- Piece Work ishchilar --}}
    @if($pieceWorkEmployees->count() > 0)
        <h3>
            Guruh: {{ $group['name'] }} (Donalik ishchilar)
            (Jami hisoblangan: {{ number_format($pieceWorkEmployees->sum(fn($e) => $e['tarification_salary'] + $e['employee_salary'])) }} so'm)
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
            @foreach ($pieceWorkEmployees as $index => $employee)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $employee['name'] }}</td>
                    <td>{{ number_format($employee['tarification_salary'] + $employee['employee_salary']) }}</td>
                    <td></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    {{-- Oylik ishchilar --}}
    @if($monthlyEmployees->count() > 0)
        <h3>
            Guruh: {{ $group['name'] }} (Oylik ishchilar)
            (Jami hisoblangan: {{ number_format($monthlyEmployees->sum(fn($e) => $e['attendance_salary'] + $e['employee_salary'])) }} so'm,
            Jami kunlar: {{ $monthlyEmployees->sum('attendance_days') }} kun)
        </h3>
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>F.I.Sh.</th>
                <th>Kuni</th>
                <th>Topgan puli</th>
                <th>Imzo</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($monthlyEmployees as $index => $employee)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $employee['name'] }}</td>
                    <td>{{ $employee['attendance_days'] ?? 0 }}</td>
                    <td>{{ number_format($employee['attendance_salary'] + $employee['employee_salary']) }}</td>
                    <td></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

@endforeach

</body>
</html>
