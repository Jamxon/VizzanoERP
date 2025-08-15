<table>
    <thead>
    <tr>
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
                <td>{{ $emp['name'] }}</td>
                <td>{{ $emp['payment_type'] }}</td>
                <td>{{ $emp['attendance_salary'] }}</td>
                <td>{{ $emp['attendance_days'] }}</td>
                <td>{{ $emp['tarification_salary'] }}</td>
                <td> </td>
            </tr>
        @endforeach
    @endforeach
    </tbody>
</table>
