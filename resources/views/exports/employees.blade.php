<html>
<head>
    <meta charset="UTF-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            font-family: DejaVu Sans, sans-serif;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .kicked {
            background-color: #f8d7da;
            color: #721c24;
        }
        img {
            width: 50px;
            height: auto;
        }
    </style>
</head>
<body>
<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>ФИО</th>
        <th>Логин</th>
        <th>Разрешение</th>
        <th>Телефон</th>
        <th>Группа</th>
        <th>Отдел</th>
        <th>Дата найма</th>
        <th>Статус</th>
        <th>Позиция</th>
        <th>Тип</th>
        <th>Тип оплаты</th>
        <th>Оклад</th>
        <th>Паспорт</th>
        <th>Адрес</th>
        <th>Дата рождения</th>
        <th>Комментарий</th>
        <th>Фото</th>
    </tr>
    </thead>
    <tbody>
    @foreach($employees as $employee)
        <tr class="{{ $employee->status === 'kicked' ? 'kicked' : '' }}">
            <td>{{ $employee->id }}</td>
            <td>{{ $employee->name }}</td>
            <td>{{ $employee->user->username ?? '' }}</td>
            <td>
                {{ $employee->user->role->description ?? '' }}
            </td>
            <td>{{ $employee->phone }}</td>
            <td>{{ $employee->group->name ?? '' }}</td>
            <td>{{ $employee->department->name ?? '' }}</td>
            <td>{{ $employee->hiring_date }}</td>
            <td>{{ $employee->status }}</td>
            <td>{{ $employee->position->name ?? '' }}</td>
            <td>{{ $employee->type }}</td>
            <td>{{ $employee->payment_type }}</td>
            <td>{{ $employee->salary }}</td>
            <td>{{ $employee->passport_number }}</td>
            <td>{{ $employee->address }}</td>
            <td>{{ $employee->birthday }}</td>
            <td>{{ $employee->comment }}</td>
            <td>
                {{ $employee->img ?? "ssss" }}
                @if($employee->img)
                    <img src="{{ asset('storage/'.$employee->img) }}" alt="Фото">
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
