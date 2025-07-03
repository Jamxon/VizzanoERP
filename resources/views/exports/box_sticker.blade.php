<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 5mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 14px;
        }

        .page-break {
            page-break-after: always;
            height: 150mm;
        }

        table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            border: 2px solid black;
            box-sizing: border-box;
            height: 150mm;
        }

        td {
            border: 2px solid black;
            padding: 4px;
        }

        .bold {
            font-weight: bold;
        }

        .center {
            text-align: center;
        }

        .big-text {
            font-size: 40px;
        }

        .xl-text {
            font-size: 55px;
        }

        .logo {
            width: 100mm;
            height: auto;
        }
    </style>
</head>
<body>

@foreach ($stickers as $index => $sticker)
    <div class="page-break">
        <table>
            {{-- Logo va № --}}
            <tr>
                <td colspan="5" rowspan="4" class="center">
                    @php
                        if (str_starts_with($imagePath, '/home')) {
                            $absoluteImagePath = $imagePath;
                        } else {
                            $absoluteImagePath = storage_path('app/public/' . ltrim(str_replace('/storage/', '', $imagePath), '/'));
                        }
                    @endphp

                    @if(file_exists($absoluteImagePath))
                        <img src="file://{{ $absoluteImagePath }}" alt="Logo" class="logo" />
                    @else
                        <strong>Logo yo'q</strong>
                    @endif
                </td>
                <td colspan="2" rowspan="4" class="center bold xl-text">{{ $index + 1 }}</td>
            </tr>
            <tr></tr>
            <tr></tr>
            <tr></tr>

            {{-- Submodel --}}
            <tr>
                <td colspan="7" class="center bold" style="font-size: 40px;">
                    {{ $submodel ?? 'Submodel nomi yo‘q' }}
                </td>
            </tr>

            {{-- Art --}}
            <tr>
                <td style="font-size: 25px;">Арт:</td>
                <td colspan="6" class="center bold" style="font-size: 50px;">{{ $model ?? '---' }}</td>
            </tr>

            {{-- Color --}}
            <tr>
                <td style="font-size: 20px;">Цвет:</td>
                <td colspan="6" class="center bold" style="font-size: 40px;">{{ $sticker['color'] ?? '---' }}</td>
            </tr>

            {{-- Header: Размер / Количество --}}
            <tr>
                <td colspan="3" class="center bold">Размер</td>
                <td colspan="4" class="center bold">Количество</td>
            </tr>

            @php
                // 1. Miqdor bilan chiqarilgan sizelarni va ularning qiymatini to‘playmiz
                $printedMap = collect($sticker)
                    ->filter(fn($val, $key) => is_int($key) && is_array($val) && count($val) === 2 && is_string($val[0]))
                    ->mapWithKeys(fn($val) => [$val[0] => $val[1]]); // ['36' => 10, '38' => 12, ...]

                // 2. orderSizes ni sort qilamiz va unikal qilamiz
                $allSizes = collect($sticker['orderSizes'] ?? [])->sort()->unique()->values();

                // 3. Chiqarilmagan (ya'ni qty ko‘rsatilmagan) sizelarni ajratamiz
                $remainingSizes = $allSizes->filter(fn($size) => !$printedMap->has($size))->values();

                // 4. Total rows = chiqarilgan + chiqmagan
                $totalRows = $printedMap->count() + $remainingSizes->count();

                // 5. 7 ga to‘ldirish uchun nechta bo‘sh qator kerak
                $emptyRowCount = max(0, 7 - $totalRows);
            @endphp

            {{-- 6. Chiqarilgan (qty bor) sizelarni chiqaramiz --}}
            @foreach($printedMap as $size => $qty)
                <tr>
                    <td colspan="3" class="center bold big-text">{{ $size }}</td>
                    <td colspan="4" class="center bold big-text">{{ $qty }}</td>
                </tr>
            @endforeach

            {{-- 7. Faqat chiqmagan sizelar (qty yo‘q) --}}
            @foreach($remainingSizes as $size)
                <tr>
                    <td colspan="3" class="center bold big-text">{{ $size }}</td>
                    <td colspan="4" class="center bold big-text"></td>
                </tr>
            @endforeach

            {{-- 8. Bo‘sh qatordan 7 taga to‘ldirish --}}
            @for($i = 0; $i < $emptyRowCount; $i++)
                <tr>
                    <td colspan="3" class="center bold big-text">&nbsp;</td>
                    <td colspan="4" class="center bold big-text"></td>
                </tr>
            @endfor




            {{-- Net & Brutto --}}
            @php
                $indexedItems = array_filter($sticker, fn($key) => is_int($key), ARRAY_FILTER_USE_KEY);
                $last = end($indexedItems);
            @endphp

            @if(is_array($last) && count($last) === 2 && is_numeric($last[0]) && is_numeric($last[1]))
                <tr>
                    <td colspan="3" class="center bold" style="font-size: 35px">Нетто (кг)</td>
                    <td colspan="4" class="center bold" style="font-size: 35px">Брутто (кг)</td>
                </tr>
                <tr>
                    <td colspan="3" class="center bold big-text">{{ $last[0] }}</td>
                    <td colspan="4" class="center bold big-text">{{ $last[1] }}</td>
                </tr>
            @endif
        </table>
    </div>
@endforeach

</body>
</html>
