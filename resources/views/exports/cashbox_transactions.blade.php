<table>
    @foreach($transactions as $purpose => $rows)
        {{-- Purpose nomi --}}
        <tr>
            <th colspan="3" style="background: #e0e0e0;">{{ $purpose }}</th>
        </tr>
        {{-- Sarlavhalar --}}
        <tr>
            <th>Sana</th>
            <th>Summa</th>
            <th>Komment</th>
        </tr>

        {{-- Purpose bo‘yicha transactionlar --}}
        @foreach($rows as $row)
            <tr>
                <td>{{ $row->date }}</td>
                <td>{{ number_format($row->amount, 0, '.', ' ') }}</td>
                <td>{{ $row->comment }}</td>
            </tr>
        @endforeach

        {{-- Purpose bo‘yicha jami --}}
        <tr>
            <td><b>Jami</b></td>
            <td><b>{{ number_format($rows->sum('amount'), 0, '.', ' ') }}</b></td>
            <td></td>
        </tr>

        {{-- Bo‘sh qator ajratish uchun --}}
        <tr><td colspan="3"></td></tr>
    @endforeach
</table>
