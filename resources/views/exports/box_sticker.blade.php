<table>
    @foreach ($stickers as $index => $sticker)
        {{-- Logo & Number --}}
        <tr>
            <td colspan="5" rowspan="4">
                <img src="{{ public_path($imagePath) }}" height="60px">
            </td>
            <td colspan="2" rowspan="4" style="font-weight: bold; font-size: 22px; text-align: center; border: 2px solid #000;">
                {{ $index + 1 }}
            </td>
        </tr>
        <tr></tr>
        <tr></tr>
        <tr></tr>

        {{-- Submodel --}}
        <tr>
            <td colspan="7" style="font-style: italic; text-align: center;">{{ $submodel }}</td>
        </tr>

        {{-- Art / Rang --}}
        <tr>
            <td style="font-weight: bold;">Арт:</td>
            <td colspan="6">{{ $sticker[2][1] ?? '' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Цвет:</td>
            <td colspan="6">{{ $sticker[3][1] ?? '' }}</td>
        </tr>

        {{-- Размер / Количество --}}
        <tr>
            <td colspan="3" style="background-color: #e0e0e0; font-weight: bold; text-align: center;">Размер / Количество</td>
            <td colspan="1"></td>
            <td colspan="3" style="background-color: #f0f0f0; font-weight: bold; text-align: center;">Нетто / Брутто</td>
        </tr>

        {{-- Razmerlar --}}
        @foreach ($sticker as $row)
            @if (isset($row[0]) && str_contains($row[0], '-'))
                <tr>
                    <td colspan="3" style="text-align: center;">{{ $row[0] }}</td>
                    <td></td>
                    <td colspan="3" style="text-align: center;"></td>
                </tr>
            @elseif (isset($row[0]) && str_contains($row[0], 'Нетто'))
                <tr>
                    <td colspan="4"></td>
                    <td colspan="3" style="font-weight: bold; text-align: center;">{{ $row[0] }}</td>
                </tr>
            @elseif (isset($row[0]) && is_numeric($row[0]))
                <tr>
                    <td colspan="4"></td>
                    <td colspan="3" style="font-weight: bold; text-align: center;">{{ $row[0] }}</td>
                </tr>
            @endif
        @endforeach

        {{-- Separator --}}
        <tr><td colspan="7"></td></tr>
    @endforeach
</table>
