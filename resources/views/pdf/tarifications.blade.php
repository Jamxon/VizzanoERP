<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; }
        h2, h4 { margin: 0 0 10px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
        }
        th { background-color: #f0f0f0; }
        .category-title {
            font-weight: bold;
            text-align: center;
            font-size: 12pt;
            margin: 15px 0 5px 0;
        }
    </style>
</head>
<body>

<h2>Operatsiyalar Ro'yxati</h2>
<h4>{{ $submodel->submodel->name ?? '-' }}</h4>

@foreach($submodel->tarificationCategories as $category)
    <div class="category-title">{{ $category->name }}</div>
    <table>
        {{-- faqat birinchi jadval uchun thead ko‘rsatiladi --}}
        @if($loop->first)
            <thead>
            <tr>
                <th>#</th>
                <th>Kod</th>
                <th>Ish nomi</th>
                <th>Razryad</th>
                <th>Tikuv mashinasi</th>
                <th>1 dona vaqti (sekund)</th>
                <th>1 dona narxi (so'm)</th>
                <th>Xodim</th>
            </tr>
            </thead>
        @endif
        <tbody>
        @foreach($category->tarifications as $index => $tar)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $tar->code ?? '-' }}</td>
                <td>{{ $tar->name }}</td>
                <td>{{ $tar->razryad->name ?? '-' }}</td>
                <td>{{ $tar->typewriter->name ?? '-' }}</td>
                <td>{{ number_format($tar->second, 2, '.', ' ') }}</td>
                <td>{{ number_format($tar->summa, 0, ',', ' ') }}</td>
                <td>{{ $tar->employee->name ?? '-' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endforeach

<script type="text/php">
        $pdf->page_script('
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $size = 10;
            $pageText = "Sahifa " . $PAGE_NUM . " / " . $PAGE_COUNT;
            $x = 520;
            $y = 820;
            $pdf->text($x, $y, $pageText, $font, $size);
        ');

</script>


</body>
</html>
