<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>ФИО</th>
        <th>Телефон</th>
        <th>Позиция</th>
        <th>Группа</th>
        <th>Отдел</th>
        <th>Дата найма</th>
        <th>Статус</th>
        <th>Логин</th>
        <th>Роль</th>
    </tr>
    </thead>
    <tbody>
    @foreach($employees as $employee)
        <tr>
            <td>{{ $employee->id }}</td>
            <td>{{ $employee->name }}</td>
            <td>{{ $employee->phone }}</td>
            <td>{{ $employee->position->name ?? '' }}</td>
            <td>{{ $employee->group->name ?? '' }}</td>
            <td>{{ $employee->department->name ?? '' }}</td>
            <td>{{ $employee->hiring_date }}</td>
            <td>{{ $employee->status }}</td>
            <td>{{ $employee->user->username ?? '' }}</td>
            <td>{{ $employee->user->role->description ?? '' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
