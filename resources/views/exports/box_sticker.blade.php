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
                // 1. Chiqarilgan sizelar: ['36' => 10, '38' => 12, ...]
                $printedMap = collect($sticker)
                    ->filter(fn($val, $key) => is_int($key) && is_array($val) && count($val) === 2 && is_string($val[0]))
                    ->mapWithKeys(fn($val) => [$val[0] => $val[1]]);

                // 2. orderSizes bo'yicha haqiqiy tartibda barcha sizelar
                $orderedSizes = collect($sticker['orderSizes'] ?? [])
                    ->unique()
                    ->values()
                    ->sort(function ($a, $b) {

                        // Sizelarni raqamiy tartibda solishtirish
                        $aNum = is_numeric($a) ? (int)$a : PHP_INT_MAX;
                        $bNum = is_numeric($b) ? (int)$b : PHP_INT_MAX;

                        return $aNum <=> $bNum;
                    })
                    ->values(); // Indekslarni qayta tiklash

                // 3. Sizelarga mos qty biriktirish (agar yo'q bo'lsa `''`)
                $fullSizes = $orderedSizes->mapWithKeys(function ($size) use ($printedMap) {
                    return [$size => $printedMap->get($size, '')];
                })
                ->filter(function ($qty) {
                    // Faqat qiymatli size'larni qoldirish
                    return $qty !== '' && $qty > 0;
                });

                // 4. To'ldirish uchun bo'sh qatorlar
                $emptyRowCount = max(0, 7 - $fullSizes->count());
            @endphp

            {{-- 5. Tartiblangan barcha sizelarni chiqarish --}}
            @foreach($fullSizes as $size => $qty)
                <tr>
                    <td colspan="3" class="center bold big-text">{{ $size }}</td>
                    <td colspan="4" class="center bold big-text">{{ $qty }}</td>
                </tr>
            @endforeach

            {{-- 6. Bo'sh qatordan 7 taga to'ldirish --}}
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
