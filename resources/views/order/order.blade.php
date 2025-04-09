<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyurtma Hujjati</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .logo-placeholder {
            width: 150px;
            height: 60px;
            border: 1px dashed #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #777;
        }
        .document-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        .document-number {
            font-size: 18px;
            margin-bottom: 20px;
            color: #7f8c8d;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        .info-group {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .info-item {
            width: 50%;
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            margin-right: 10px;
            color: #7f8c8d;
        }
        .info-value {
            font-weight: normal;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #f2f2f2;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
        }
        td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        .footer {
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        .signature-block {
            width: 45%;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 50px;
            width: 70%;
        }
        .signature-title {
            margin-top: 5px;
            font-weight: bold;
        }
        .status-approved {
            color: #27ae60;
            font-weight: bold;
        }
        .status-pending {
            color: #f39c12;
            font-weight: bold;
        }
        .status-rejected {
            color: #e74c3c;
            font-weight: bold;
        }
        .stamp-area {
            width: 120px;
            height: 120px;
            border: 1px dashed #aaa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="company-info">
            <div>
                <div class="logo-placeholder">KOMPANIYA LOGOSI</div>
                <div>
                    <p>Tashkilot nomi</p>
                    <p>Manzil: Toshkent shahri</p>
                    <p>Tel: +998 XX XXX XX XX</p>
                </div>
            </div>
            <div>
                <h1 class="document-title">BUYURTMA HUJJATI</h1>
                <div class="document-number">Hujjat № {order.id}</div>
                <p>Sana: {{ $order }}</p>
                <p>{{}}</p>
            </div>
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">Asosiy ma'lumotlar</h2>
        <div class="info-group">
            <div class="info-item">
                <span class="info-label">Buyurtma №:</span>
                <span class="info-value">{order.id}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Nomi:</span>
                <span class="info-value">{order.name}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Miqdori:</span>
                <span class="info-value">{order.quantity}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Holati:</span>
                <span class="info-value status-approved">{order.status}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Boshlangan sana:</span>
                <span class="info-value">{order.start_date}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Tugash sanasi:</span>
                <span class="info-value">{order.end_date}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Rasxod:</span>
                <span class="info-value">{order.rasxod}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Narxi:</span>
                <span class="info-value">{order.price}</span>
            </div>
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">Model ma'lumotlari</h2>
        <div class="info-group">
            <div class="info-item">
                <span class="info-label">Model №:</span>
                <span class="info-value">{order.order_model.id}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Model nomi:</span>
                <span class="info-value">{order.order_model.model}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Material:</span>
                <span class="info-value">{order.order_model.material}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Rasxod:</span>
                <span class="info-value">{order.order_model.rasxod}</span>
            </div>
        </div>

        <h3 class="section-title">O'lchamlar</h3>
        <table>
            <thead>
            <tr>
                <th>№</th>
                <th>O'lcham</th>
                <th>Miqdori</th>
            </tr>
            </thead>
            <tbody>
            <!-- {For each size in order.order_model.sizes} -->
            <tr>
                <td>{size.id}</td>
                <td>{size.size}</td>
                <td>{size.quantity}</td>
            </tr>
            <!-- {End for} -->
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Qo'shimcha modellar</h2>
        <table>
            <thead>
            <tr>
                <th>№</th>
                <th>Submodel nomi</th>
                <th>Guruh</th>
                <th>Sifat tekshiruvi</th>
            </tr>
            </thead>
            <tbody>
            <!-- {For each submodel in order.order_model.submodels} -->
            <tr>
                <td>{submodel.id}</td>
                <td>{submodel.submodel.name}</td>
                <td>{submodel.group.group}</td>
                <td>Tasdiqlangan: {submodel.qualityChecks_status_count.true}<br>
                    Tasdiqlanmagan: {submodel.qualityChecks_status_count.false}</td>
            </tr>
            <!-- {End for} -->
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Spetsifikatsiyalar</h2>
        <!-- {For each category in order.specification_categories} -->
        <h3>{category.name}</h3>
        <table>
            <thead>
            <tr>
                <th>№</th>
                <th>Sana</th>
                <th>Miqdori</th>
                <th>Holati</th>
                <th>Mas'ul shaxs</th>
            </tr>
            </thead>
            <tbody>
            <!-- {For each cut in category.orderCuts} -->
            <tr>
                <td>{cut.id}</td>
                <td>{cut.cut_at}</td>
                <td>{cut.quantity}</td>
                <td>{cut.status}</td>
                <td>{cut.user}</td>
            </tr>
            <!-- {End for} -->
            </tbody>
        </table>
        <!-- {End for} -->
    </div>

    <div class="section">
        <h2 class="section-title">Ko'rsatmalar</h2>
        <table>
            <thead>
            <tr>
                <th>№</th>
                <th>Sarlavha</th>
                <th>Tavsif</th>
            </tr>
            </thead>
            <tbody>
            <!-- {For each instruction in order.instructions} -->
            <tr>
                <td>{instruction.id}</td>
                <td>{instruction.title}</td>
                <td>{instruction.description}</td>
            </tr>
            <!-- {End for} -->
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Chop etish vaqti</h2>
        <div class="info-group">
            <div class="info-item">
                <span class="info-label">Rejalashtirilgan vaqt:</span>
                <span class="info-value">{order.orderPrintingTimes.planned_time}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Haqiqiy vaqt:</span>
                <span class="info-value">{order.orderPrintingTimes.actual_time}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Holati:</span>
                <span class="info-value">{order.orderPrintingTimes.status}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Mas'ul shaxs:</span>
                <span class="info-value">{order.orderPrintingTimes.user}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Izoh:</span>
                <span class="info-value">{order.orderPrintingTimes.comment}</span>
            </div>
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">Qadoqlash ma'lumotlari</h2>
        <table>
            <thead>
            <tr>
                <th>№</th>
                <th>Qadoq o'lchami</th>
                <th>Qadoq miqdori</th>
            </tr>
            </thead>
            <tbody>
            <!-- {For each package in order.packageOutcomes} -->
            <tr>
                <td>{package.id}</td>
                <td>{package.package_size}</td>
                <td>{package.package_quantity}</td>
            </tr>
            <!-- {End for} -->
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Mijoz ma'lumotlari</h2>
        <div class="info-group">
            <div class="info-item">
                <span class="info-label">Mijoz №:</span>
                <span class="info-value">{order.contragent.id}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Nomi:</span>
                <span class="info-value">{order.contragent.name}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Tavsif:</span>
                <span class="info-value">{order.contragent.description}</span>
            </div>
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">Buyurtma bo'yicha izoh</h2>
        <p>{order.comment}</p>
    </div>

    <div class="footer">
        <div class="signature-block">
            <p>Buyurtmachi vakili:</p>
            <div class="signature-line"></div>
            <p class="signature-title">F.I.O.</p>
            <div class="stamp-area">Muhri joyi</div>
        </div>
        <div class="signature-block">
            <p>Ishlab chiqaruvchi vakili:</p>
            <div class="signature-line"></div>
            <p class="signature-title">F.I.O.</p>
            <div class="stamp-area">Muhri joyi</div>
        </div>
    </div>
</div>
</body>
</html>