@php use PhpOffice\PhpSpreadsheet\Worksheet\Drawing; @endphp
@foreach($stickers as $index => $sticker)
    {{-- LOGO & INDEX --}}
    <table>
        <tr>
            <td colspan="5" rowspan="4" style="text-align:center;">
                <img src="{{ public_path($imagePath) }}" width="100" height="100" alt="Logo">
            </td>
            <td colspan="2" rowspan="4" style="text-align:center; font-size: 24px;">
                {{ $index + 1 }}
            </td>
        </tr>
        <tr></tr><tr></tr><tr></tr>

        {{-- SUBMODEL --}}
        <tr>
            <td colspan="7" style="text-align:center; font-weight:bold; font-size:18px;">
                {{ $submodel }}
            </td>
        </tr>

        {{-- ARTIKUL & RANG --}}
        <tr>
            <td style="font-weight:bold;">Арт:</td>
            <td colspan="6">{{ $sticker[2][1] ?? '' }}</td>
        </tr>
        <tr>
            <td style="font-weight:bold;">Цвет:</td>
            <td colspan="6">{{ $sticker[3][1] ?? '' }}</td>
        </tr>

        {{-- HEADER --}}
        <tr>
            <td>Размер</td>
            <td>Количество</td>
            <td colspan="5"></td>
        </tr>

        {{-- SIZES --}}
        @foreach(array_slice($sticker, 5, count($sticker)-8) as $row)
            <tr>
                <td>{{ $row[0] }}</td>
                <td>{{ $row[1] ?? '' }}</td>
                <td colspan="5"></td>
            </tr>
        @endforeach

        {{-- NETTO & BRUTTO --}}
        <tr>
            <td>Нетто(кг)</td>
            <td>Брутто(кг)</td>
            <td colspan="5"></td>
        </tr>
        <tr>
            <td>{{ $sticker[count($sticker)-2][0] }}</td>
            <td>{{ $sticker[count($sticker)-2][1] }}</td>
            <td colspan="5"></td>
        </tr>

        {{-- SEPARATOR --}}
        <tr><td colspan="7" style="height:20px;"></td></tr>
    </table>
@endforeach
