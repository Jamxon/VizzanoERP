@foreach ($stickers as $index => $sticker)
    <table style="margin-bottom: 40px; border-collapse: collapse; width: 100%; border: 2px solid black;  font-family: DejaVu Sans, sans-serif;">
        {{-- 1-4 qator: Logo va № --}}
        <tr>
            <td colspan="5" rowspan="4" style="text-align: center; border: 2px solid black;">
                @php
                    // Agar absolute path bo‘lsa, to‘g‘ridan-to‘g‘ri ishlatamiz
                    if (str_starts_with($imagePath, '/home')) {
                        $absoluteImagePath = $imagePath;
                    } else {
                        // Nisbiy path bo‘lsa
                        $absoluteImagePath = storage_path('app/public/' . ltrim(str_replace('/storage/', '', $imagePath), '/'));
                    }
                @endphp

                @if(file_exists($absoluteImagePath))
                    <img src="file://{{ $absoluteImagePath }}" alt="Logo" style="width: 140px; height: auto;" />
                @else
                    <strong>Logo yo‘q: {{ $absoluteImagePath }}</strong>
                @endif



            </td>
            <td colspan="2" rowspan="4" style="text-align: center; font-weight: bold; font-size: 55px; border: 2px solid black;">{{ $index + 1 }}</td>
        </tr>
        <tr></tr>
        <tr></tr>
        <tr></tr>

        {{-- 5-6 qator: Submodel --}}
        <tr>
            <td colspan="7" rowspan="2" style="text-align: center; font-weight: bold; font-size: 30px; border: 2px solid black;">
                {{ $submodel ?? 'Submodel nomi yo‘q' }}
            </td>
        </tr>

        <tr></tr>

        <tr>
            <td style="font-size: 25px; height: 60px; border: 2px solid black;"><p>Арт:</p></td>
            <td colspan="6" style="font-weight: bold; text-align: center; font-size: 40px; height: 60px; border: 2px solid black;">{{ $model ?? '---' }}</td>
        </tr>

        <tr>
            <td style="font-size: 20px; height: 60px; border: 2px solid black;"><p>Цвет:</p></td>
            <td colspan="6" style="font-weight: bold; text-align: center; font-size: 34px; height: 60px; border: 2px solid black;">{{ $sticker['color'] ?? '---' }}</td>
        </tr>

        <tr>
            <td colspan="3" style="text-align: center; border: 2px solid black;"><p>Размер</p></td>
            <td colspan="4" style="text-align: center; border: 2px solid black;"><p>Количество</p></td>
        </tr>

        @foreach($sticker as $key => $row)
            @if(is_int($key) && is_array($row) && count($row) == 2 && is_string($row[0]))
                <tr>
                    <td colspan="3" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px; border: 2px solid black;">{{ $row[0] }}</td>
                    <td colspan="4" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px; border: 2px solid black;">{{ $row[1] }}</td>
                </tr>
            @endif
        @endforeach

        @php
            $sizes = [];
            foreach ($sticker as $key => $value) {
                if ($key == 'orderSizes') {
                    $sizes[] = $value;
                }
            }
        @endphp

        @foreach ($sizes as $size)
            @foreach($size as $row)
                <tr>
                    <td colspan="3" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px; border: 2px solid black;">{{ $row }}</td>
                    <td colspan="4" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px; border: 2px solid black;"></td>
                </tr>
@endforeach
@endforeach

@php
    $indexedItems = array_filter($sticker, fn($key) => is_int($key), ARRAY_FILTER_USE_KEY);
    $last = end($indexedItems);
@endphp


        @if(is_array($last) && count($last) == 2 && is_numeric($last[0]) && is_numeric($last[1]))
            <tr>
                <td colspan="3" style="text-align: center; font-weight: bold; font-size: 15px; border: 2px solid black;">Нетто (кг)</td>
                <td colspan="4" style="text-align: center; font-weight: bold; font-size: 15px; border: 2px solid black;">Брутто (кг)</td>
            </tr>
            <tr>
                <td colspan="3" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px; border: 2px solid black;">{{ $last[0] }}</td>
                <td colspan="4" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px; border: 2px solid black;">{{ $last[1] }}</td>
            </tr>
        @endif
    </table>
@endforeach
