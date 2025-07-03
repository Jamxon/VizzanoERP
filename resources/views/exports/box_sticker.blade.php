@foreach ($stickers as $index => $sticker)
    <table style="margin-bottom: 40px; border-collapse: separate; border-spacing: 0; width: 100%; font-family: DejaVu Sans, sans-serif; border: 2px solid black;">
        {{-- Logo and Sticker Number --}}
        <tr>
            <td colspan="5" rowspan="4" style="text-align: center; border-right: 2px solid black; border-bottom: 2px solid black; box-sizing: border-box;">
                @php
                    if (str_starts_with($imagePath, '/home')) {
                        $absoluteImagePath = $imagePath;
                    } else {
                        $absoluteImagePath = storage_path('app/public/' . ltrim(str_replace('/storage/', '', $imagePath), '/'));
                    }
                @endphp

                @if(file_exists($absoluteImagePath))
                    <img src="file://{{ $absoluteImagePath }}" alt="Logo" style="width: 140px; height: auto;" />
                @else
                    <strong>Logo yo'q: {{ $absoluteImagePath }}</strong>
                @endif
            </td>
            <td colspan="2" rowspan="4" style="text-align: center; font-weight: bold; font-size: 55px; border-bottom: 2px solid black; box-sizing: border-box;">
                {{ $index + 1 }}
            </td>
        </tr>
        <tr></tr>
        <tr></tr>
        <tr></tr>

        {{-- Submodel --}}
        <tr>
            <td colspan="7" rowspan="2" style="text-align: center; font-weight: bold; font-size: 30px; border-bottom: 2px solid black; box-sizing: border-box;">
                {{ $submodel ?? 'Submodel nomi yo'q' }}
            </td>
        </tr>
        <tr></tr>

        {{-- Art --}}
        <tr>
            <td style="font-size: 25px; height: 60px; border-right: 2px solid black; border-bottom: 2px solid black; box-sizing: border-box;">Арт:</td>
            <td colspan="6" style="font-weight: bold; text-align: center; font-size: 40px; height: 60px; border-bottom: 2px solid black; box-sizing: border-box;">
                {{ $model ?? '---' }}
            </td>
        </tr>

        {{-- Color --}}
        <tr>
            <td style="font-size: 20px; height: 60px; border-right: 2px solid black; border-bottom: 2px solid black; box-sizing: border-box;">Цвет:</td>
            <td colspan="6" style="font-weight: bold; text-align: center; font-size: 34px; height: 60px; border-bottom: 2px solid black; box-sizing: border-box;">
                {{ $sticker['color'] ?? '---' }}
            </td>
        </tr>

        {{-- Table Headers --}}
        <tr>
            <td colspan="3" style="text-align: center; border-right: 2px solid black; border-bottom: 2px solid black; box-sizing: border-box;">Размер</td>
            <td colspan="4" style="text-align: center; border-bottom: 2px solid black; box-sizing: border-box;">Количество</td>
        </tr>

        {{-- Sizes from array --}}
        @foreach($sticker as $key => $row)
            @if(is_int($key) && is_array($row) && count($row) == 2 && is_string($row[0]))
                <tr>
                    <td colspan="3" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px; border-right: 2px solid black; border-bottom: 2px solid black; box-sizing: border-box;">
                        {{ $row[0] }}
                    </td>
                    <td colspan="4" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px; border-bottom: 2px solid black; box-sizing: border-box;">
                        {{ $row[1] }}
                    </td>
                </tr>
    @endif
@endforeach

{{-- Extra sizes from orderSizes --}}
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
                    <td colspan="3" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px; border-right: 2px solid black; border-bottom: 2px solid black; box-sizing: border-box;">
                        {{ $row }}
                    </td>
                    <td colspan="4" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px; border-bottom: 2px solid black; box-sizing: border-box;"></td>
                </tr>
            @endforeach
        @endforeach

        {{-- Net Weight & Gross Weight --}}
        @php
            $indexedItems = array_filter($sticker, fn($key) => is_int($key), ARRAY_FILTER_USE_KEY);
            $last = end($indexedItems);
        @endphp

        @if(is_array($last) && count($last) == 2 && is_numeric($last[0]) && is_numeric($last[1]))
            <tr>
                <td colspan="3" style="text-align: center; font-weight: bold; font-size: 15px; border-right: 2px solid black; border-bottom: 2px solid black; box-sizing: border-box;">
                    Нетто (кг)
                </td>
                <td colspan="4" style="text-align: center; font-weight: bold; font-size: 15px; border-bottom: 2px solid black; box-sizing: border-box;">
                    Брутто (кг)
                </td>
            </tr>
            <tr>
                <td colspan="3" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px; border-right: 2px solid black; box-sizing: border-box;">
                    {{ $last[0] }}
                </td>
                <td colspan="4" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px; box-sizing: border-box;">
                    {{ $last[1] }}
                </td>
            </tr>
        @endif
    </table>
@endforeach
