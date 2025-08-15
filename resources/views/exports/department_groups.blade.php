<table>
    <thead>
    <tr>
        <th>No</th>
        <th>FIO</th>
        <th>To'lov turi</th>
        <th>Ish haqi (oylik)</th>
        <th>Ish kunlari</th>
        <th>Ishbay</th>
        <th>Imzo</th>
    </tr>
    </thead>
    <tbody>
    @foreach($groups as $group)
        @foreach($group['employees'] as $emp)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $emp['name'] }}</td>
                <td>@if($emp['payment_type'] === 'piece_work')
                    Ishbay
                @else
                    Oylik
                @endif</td>
                <td>{{ $emp['attendance_salary'] }}</td>
                <td>{{ $emp['attendance_days'] }}</td>
                <td>{{ number_format($emp['tarification_salary']) }}</td>
                <td> </td>
            </tr>
        @endforeach
    @endforeach
    </tbody>
</table>
