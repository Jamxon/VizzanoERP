<table>
    {{-- 1-4 qator: Logo va № --}}
    <tr>
        <td colspan="5" rowspan="3" style="text-align: center;">
{{--            @if(file_exists($imagePath))--}}
{{--                <img src="{{ $imagePath }}" alt="Logo" style="width: 100px; height: 100px;">--}}
{{--            @else--}}
{{--                <strong>Logo yo'q</strong>--}}
{{--            @endif--}}
        </td>
        <td colspan="2" rowspan="3" style="text-align: center; font-weight: bold; font-size: 55px;">{{ $index }}</td>
    </tr>
    <tr></tr>
    <tr></tr>

    {{-- 5-6 qator: Submodel --}}
    <tr>
        <td colspan="7" rowspan="2" style="text-align: center; font-weight: bold; font-size: 30px;">
            {{ $submodel ?? 'Submodel nomi yo‘q' }}
        </td>
    </tr>

    <tr></tr>

    {{-- 7-qator: Artikul --}}
    <tr>
        <td style="font-size: 25px" colspan="2"><strong>Арт:</strong></td>
        <td colspan="6" style="font-weight: bold; font-size: 40px">{{ $sticker[2][1] ?? '---' }}</td>
    </tr>
    <tr></tr>

    {{-- 8-qator: Color --}}
    <tr>
        <td style="font-size: 25px" colspan="2"><strong>Цвет:</strong></td>
        <td colspan="6" style="font-weight: bold; font-size: 30px">{{ $sticker[3][1] ?? '---' }}</td>
    </tr>

    <tr></tr>

    {{-- 9-qator: Размер / Количество sarlavha --}}
    <tr>
        <td colspan="3" style="text-align: center; font-weight: bold; background-color: #f2f2f2;">Размер</td>
        <td colspan="4" style="text-align: center; font-weight: bold; background-color: #f2f2f2;">Количество</td>
    </tr>

    {{-- 10+ qatorlar: Size and Qty --}}
    @php
        $dataRows = [];
        foreach ($sticker as $row) {
            if (isset($row[0], $row[1]) && $row[0] !== 'Размер' && $row[0] !== 'Нетто(кг)' && $row[0] !== 'Брутто(кг)' && $row[0] !== '') {
                $dataRows[] = $row;
            }
        }

        $netto = $sticker[count($sticker) - 2] ?? [0, 0];
        $brutto = $sticker[count($sticker) - 1] ?? [0, 0];
    @endphp

    @foreach($dataRows as $row)
        <tr>
            <td colspan="3" style="text-align: center;">{{ $row[0] }}</td>
            <td colspan="4" style="text-align: center;">
                {{ $row[1] != 0 ? $row[1] : '' }}
            </td>
        </tr>
    @endforeach

    {{-- Ajratilgan qator --}}
    <tr><td colspan="7">&nbsp;</td></tr>

    {{-- Netto va Brutto --}}
    <tr>
        <td colspan="3" style="text-align: center; font-weight: bold;">Нетто (кг)</td>
        <td colspan="4" style="text-align: center;">{{ $netto[0] ?? '' }}</td>
    </tr>
    <tr>
        <td colspan="3" style="text-align: center; font-weight: bold;">Брутто (кг)</td>
        <td colspan="4" style="text-align: center;">{{ $brutto[1] ?? '' }}</td>
    </tr>
</table>
