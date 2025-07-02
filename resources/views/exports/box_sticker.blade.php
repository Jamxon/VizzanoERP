<table>
    {{-- 1-4 qator: logo --}}
    <tr><td colspan="5" rowspan="4">
            @if(file_exists($imagePath))
                <img src="{{ $imagePath }}" alt="Logo" height="90">
            @else
                <strong>Rasm topilmadi</strong>
            @endif
        </td>
        <td colspan="2" style="text-align: center; font-weight: bold;">№ {{ $loop->iteration ?? 1 }}</td></tr>
    <tr></tr><tr></tr><tr></tr>

    {{-- 5-6 qator: Submodel --}}
    <tr><td colspan="7" style="text-align: center; font-weight: bold;">{{ $submodel }}</td></tr>
    <tr><td colspan="7">&nbsp;</td></tr>

    {{-- 7-qator: Artikul --}}
    <tr>
        <td><strong>Арт:</strong></td>
        <td colspan="6">{{ $sticker[2][1] ?? '---' }}</td>
    </tr>

    {{-- 8-qator: Color --}}
    <tr>
        <td><strong>Цвет:</strong></td>
        <td colspan="6">{{ $sticker[3][1] ?? '---' }}</td>
    </tr>

    {{-- 9-qator: Sarlavhalar --}}
    <tr>
        <td colspan="3" style="text-align:center; font-weight: bold;">Размер</td>
        <td colspan="4" style="text-align:center; font-weight: bold;">Количество</td>
    </tr>

    {{-- Size list --}}
    @php
        $sizes = array_filter($sticker, fn($row) => isset($row[0]) && $row[0] !== '' && $row[0] !== 'Размер');
        $netto = $sticker[array_key_last($sticker)-1] ?? ['',''];
        $brutto = $sticker[array_key_last($sticker)] ?? ['',''];
    @endphp

    @foreach($sizes as $row)
        @continue($loop->iteration < 6 || $loop->iteration > count($sticker) - 3) {{-- Skip static rows --}}
        <tr>
            <td colspan="3" style="text-align:center;">{{ $row[0] }}</td>
            <td colspan="4" style="text-align:center;">
                {{ $row[1] != 0 ? $row[1] : '' }}
            </td>
        </tr>
    @endforeach

    {{-- Separator --}}
    <tr><td colspan="7">&nbsp;</td></tr>

    {{-- Netto / Brutto --}}
    <tr>
        <td colspan="3" style="text-align:center; font-weight: bold;">Нетто(кг)</td>
        <td colspan="4" style="text-align:center;">{{ $netto[0] ?? '' }}</td>
    </tr>
    <tr>
        <td colspan="3" style="text-align:center; font-weight: bold;">Брутто(кг)</td>
        <td colspan="4" style="text-align:center;">{{ $brutto[1] ?? '' }}</td>
    </tr>
</table>
