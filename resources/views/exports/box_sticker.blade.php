<style>
    table, tr, td {
        border: 2px solid black;
        border-collapse: collapse;
        box-sizing: border-box;
    }

    td {
        page-break-inside: avoid;
    }

    @media print {
        table {
            page-break-inside: avoid;
        }
    }
</style>

@foreach ($stickers as $index => $sticker)
    <table style="margin-bottom: 40px; width: 100%; font-family: DejaVu Sans, sans-serif;">
        {{-- Logo and Number --}}
        <tr>
            <td colspan="5" rowspan="4" style="text-align: center;">
                @php
                    $absoluteImagePath = str_starts_with($imagePath, '/home')
                        ? $imagePath
                        : storage_path('app/public/' . ltrim(str_replace('/storage/', '', $imagePath), '/'));
                @endphp

                @if(file_exists($absoluteImagePath))
                    <img src="file://{{ $absoluteImagePath }}" alt="Logo" style="width: 140px; height: auto;" />
                @else
                    <strong>Logo yo‘q: {{ $absoluteImagePath }}</strong>
                @endif
            </td>
            <td colspan="2" rowspan="4" style="text-align: center; font-weight: bold; font-size: 55px;">
                {{ $index + 1 }}
            </td>
        </tr>
        {{-- Remove empty <tr></tr> lines that cause broken rendering --}}

        {{-- Submodel --}}
        <tr>
            <td colspan="7" style="text-align: center; font-weight: bold; font-size: 30px;">
                {{ $submodel ?? 'Submodel nomi yo‘q' }}
            </td>
        </tr>

        {{-- Art --}}
        <tr>
            <td style="font-size: 25px; height: 60px;">Арт:</td>
            <td colspan="6" style="font-weight: bold; text-align: center; font-size: 40px; height: 60px;">
                {{ $model ?? '---' }}
            </td>
        </tr>

        {{-- Color --}}
        <tr>
            <td style="font-size: 20px; height: 60px;">Цвет:</td>
            <td colspan="6" style="font-weight: bold; text-align: center; font-size: 34px; height: 60px;">
                {{ $sticker['color'] ?? '---' }}
            </td>
        </tr>

        {{-- Size / Quantity Header --}}
        <tr>
            <td colspan="3" style="text-align: center;">Размер</td>
            <td colspan="4" style="text-align: center;">Количество</td>
        </tr>

        {{-- Size Rows (Regular) --}}
        @foreach($sticker as $key => $row)
            @if(is_int($key) && is_array($row) && count($row) === 2 && is_string($row[0]))
                <tr>
                    <td colspan="3" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px;">
                        {{ $row[0] }}
                    </td>
                    <td colspan="4" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px;">
                        {{ $row[1] }}
                    </td>
                </tr>
            @endif
        @endforeach

        {{-- Extra Sizes (e.g. orderSizes) --}}
        @php
            $sizes = [];
            foreach ($sticker as $key => $value) {
                if ($key === 'orderSizes') {
                    $sizes[] = $value;
                }
            }
        @endphp

        @foreach ($sizes as $sizeGroup)
            @foreach($sizeGroup as $row)
                <tr>
                    <td colspan="3" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px;">
                        {{ $row }}
                    </td>
                    <td colspan="4" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px;"></td>
                </tr>
@endforeach
@endforeach

{{-- Net Weight / Gross Weight --}}
@php
    $indexedItems = array_filter($sticker, fn($key) => is_int($key), ARRAY_FILTER_USE_KEY);
    $last = end($indexedItems);
@endphp


        @if(is_array($last) && count($last) === 2 && is_numeric($last[0]) && is_numeric($last[1]))
            <tr>
                <td colspan="3" style="text-align: center; font-weight: bold; font-size: 15px;">
                    Нетто (кг)
                </td>
                <td colspan="4" style="text-align: center; font-weight: bold; font-size: 15px;">
                    Брутто (кг)
                </td>
            </tr>
            <tr>
                <td colspan="3" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px;">
                    {{ $last[0] }}
                </td>
                <td colspan="4" style="text-align: center; font-size: 35px; font-weight: bold; height: 50px;">
                    {{ $last[1] }}
                </td>
            </tr>
        @endif
    </table>
@endforeach
