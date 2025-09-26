<table>
    @foreach($transactions as $purpose => $rows)
        {{-- Purpose nomi (yashil rangda) --}}
        <tr>
            <td colspan="3" style="background: #90EE90; font-weight: bold; text-align: center; padding: 8px; border: 1px solid #000; width: 500px;">
                {{ $purpose }}
            </td>
        </tr>

        {{-- Sarlavhalar --}}
        <tr style="background: #4CAF50; color: white;">
            <th style="border: 1px solid #000; padding: 5px; font-weight: bold; width: 100px">Sana</th>
            <th style="border: 1px solid #000; padding: 5px; font-weight: bold; width: 300px">Summa</th>
            <th style="border: 1px solid #000; padding: 5px; font-weight: bold; width: 100px">Komment</th>
        </tr>

        {{-- Purpose bo'yicha transactionlar --}}
        @foreach($rows as $row)
            <tr>
                <td style="border: 1px solid #000; padding: 3px; width: 100px;">{{ $row->date }}</td>
                <td style="border: 1px solid #000; padding: 3px; width: 300px;">{{ $row->comment ?? '' }}</td>
                <td style="border: 1px solid #000; padding: 3px; width: 100px; text-align: right;">{{ number_format($row->amount, 0, '.', ' ') }}</td>
            </tr>
        @endforeach

        {{-- Purpose bo'yicha jami (sariq rangda) --}}
        <tr style="background: #FFFF99;">
            <td style="border: 1px solid #000; padding: 3px; width: 100px; font-weight: bold;">Jami</td>
            <td style="border: 1px solid #000; padding: 3px; width: 300px; font-weight: bold; text-align: right;">{{ number_format($rows->sum('amount'), 0, '.', ' ') }}</td>
            <td style="border: 1px solid #000; padding: 3px; width: 100px;"></td>
        </tr>

        {{-- 3 ta bo'sh qator ajratish uchun --}}
        <tr><td colspan="3" style="height: 10px;"></td></tr>
        <tr><td colspan="3" style="height: 10px;"></td></tr>
        <tr><td colspan="3" style="height: 10px;"></td></tr>
    @endforeach
</table>