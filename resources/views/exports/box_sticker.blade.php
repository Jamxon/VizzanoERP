@foreach ($stickers as $index => $sticker)
    <table style="margin-bottom: 40px; border-collapse: collapse; width: 100%;">
        {{-- 1-4 qator: Logo va № --}}
        <tr>
            <td colspan="5" rowspan="4" style="text-align: center;">
                {{-- @if(file_exists($imagePath)) --}}
                {{--     <img src="{{ $imagePath }}" alt="Logo" style="width: 100px; height: 100px;"> --}}
                {{-- @else --}}
                {{--     <strong>Logo yo'q</strong> --}}
                {{-- @endif --}}
            </td>
            <td colspan="2" rowspan="4" style="text-align: center; font-weight: bold; font-size: 55px;">{{ $index + 1 }}</td>
        </tr>
        <tr></tr>
        <tr></tr>
        <tr></tr>

        {{-- 5-6 qator: Submodel --}}
        <tr>
            <td colspan="7" rowspan="2" style="text-align: center; font-weight: bold; font-size: 30px;">
                {{ $submodel ?? 'Submodel nomi yo‘q' }}
            </td>
        </tr>

        <tr></tr>

        <tr>
            <td style="font-size: 25px; height: 60px;"><p>Арт:</p></td>
            <td colspan="6" style="font-weight: bold; text-align: center; font-size: 40px; height: 60px;">{{ $model ?? '---' }}</td>
        </tr>

        <tr>
            <td style="font-size: 20px; height: 60px;"><p>Цвет:</p></td>
            <td colspan="6" style="font-weight: bold; text-align: center; font-size: 34px; height: 60px;">{{ $sticker['color'] ?? '---' }}</td>
        </tr>

        <tr>
            <td colspan="3" style="text-align: center;"><p>Размер</p></td>
            <td colspan="4" style="text-align: center;"><p>Количество</p></td>
        </tr>

        @foreach($sticker as $key => $row)
            @if(is_int($key) && is_array($row) && count($row) == 2 && is_string($row[0]))
                <tr>
                    <td colspan="3" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px;">{{ $row[0] }}</td>
                    <td colspan="4" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px;">{{ $row[1] }}</td>
                </tr>
            @endif
        @endforeach

        @php
            $indexedItems = array_filter($sticker, fn($key) => is_int($key), ARRAY_FILTER_USE_KEY);
            $last = end($indexedItems);
        @endphp

        @if(is_array($last) && count($last) == 2 && is_numeric($last[0]) && is_numeric($last[1]))
            <tr>
                <td colspan="3" style="text-align: center; font-weight: bold; font-size: 15px;">Нетто (кг)</td>
                <td colspan="4" style="text-align: center; font-weight: bold; font-size: 15px;">Брутто (кг)</td>
            </tr>
            <tr>
                <td colspan="3" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px;">{{ $last[0] }}</td>
                <td colspan="4" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px;">{{ $last[1] }}</td>
            </tr>
        @endif
    </table>
@endforeach
