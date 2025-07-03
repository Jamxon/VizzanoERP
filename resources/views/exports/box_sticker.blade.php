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
            font-size: 45px;
        }

        .xl-text {
            font-size: 55px;
        }

        .logo {
            width: 70%;
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

            {{-- Body: Asosiy o'lchamlar va sonlar --}}
            @foreach($sticker as $key => $row)
                @if(is_int($key) && is_array($row) && count($row) == 2 && is_string($row[0]))
                    <tr>
                        <td colspan="3" class="center bold big-text">{{ $row[0] }}</td>
                        <td colspan="4" class="center bold big-text">{{ $row[1] }}</td>
                    </tr>
                @endif
            @endforeach

            @php
                // 1. Chiqarilgan sizelar ro‘yxatini tuzamiz (faqat string bo‘lganlarni olamiz)
                $printedSizes = collect($sticker)
                    ->filter(fn($val, $key) => is_int($key) && is_array($val) && count($val) == 2 && is_string($val[0]))
                    ->map(fn($val) => $val[0])
                    ->values()
                    ->all();

                // 2. orderSizes dan kelgan barcha size'lar
                $allOrderSizes = collect($sticker['orderSizes'] ?? [])->unique()->values();

                // 3. Faqat chiqarilmagan sizelarni olish
                $remainingSizes = $allOrderSizes->filter(fn($size) => !in_array($size, $printedSizes));
            @endphp

            {{-- 4. Faqat chiqarilmagan sizelarni chiqarish --}}
            @foreach($remainingSizes as $row)
                <tr>
                    <td colspan="3" class="center bold big-text">{{ $row }}</td>
                    <td colspan="4" class="center bold big-text"></td>
                </tr>
            @endforeach


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
