<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class EmployeeExport implements FromCollection, WithMapping, WithHeadings, WithColumnWidths
{
    protected Request $request;
    protected $employees;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $filters = $this->request->only(['search', 'department_id', 'group_id', 'status', 'role_id']);
        $user = auth()->user();

        $query = Employee::with(['user.role', 'position', 'group', 'department'])
            ->where('branch_id', $user->employee->branch_id);

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $searchLatin = transliterate_to_latin($search);
            $searchCyrillic = transliterate_to_cyrillic($search);

            $query->where(function ($q) use ($search, $searchLatin, $searchCyrillic) {
                foreach ([$search, $searchLatin, $searchCyrillic] as $term) {
                    $q->orWhereRaw('LOWER(name) LIKE ?', ["%$term%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%$term%"])
                        ->orWhereHas('position', fn($q) => $q->whereRaw('LOWER(name) LIKE ?', ["%$term%"]))
                        ->orWhereHas('user', function ($q) use ($term) {
                            $q->whereRaw('LOWER(username) LIKE ?', ["%$term%"])
                                ->orWhereHas('role', fn($q) => $q->whereRaw('LOWER(description) LIKE ?', ["%$term%"]));
                        });
                }
            });
        }

        $query->when($filters['department_id'] ?? null, fn($q, $val) => $q->where('department_id', $val))
            ->when(
                true,
                function ($q) use ($user, $filters) {
                    if ($user->role->name === 'groupMaster') {
                        // group_id ni user->employee ichidan olish kerak
                        $q->where('employees.group_id', $user->employee->group_id);
                    } elseif (!empty($filters['group_id'])) {
                        $q->where('employees.group_id', $filters['group_id']);
                    }
                }
            )
            ->when($filters['status'] ?? null, fn($q, $val) => $q->where('status', $val))
            ->when($filters['role_id'] ?? null, fn($q, $val) => $q->whereHas('user', fn($q) => $q->where('role_id', $val)));

        return $this->employees = $query->latest('updated_at')->get();
    }

    public function headings(): array
    {
        return [
            'ID', 'ФИО', 'Логин', 'Разрешение', 'Телефон', 'Группа', 'Отдел', 'Ишга келган сана',
            'Позиция', 'Паспорт', 'Адрес', 'Дата рождения', 'Комментарий', "Тўлов тури", 'Ойлик', // <-- salary ustuni qo'shildi
        ];
    }

    public function map($employee): array
    {
        return [
            $employee->id,
            $employee->name,
            $employee->user->username ?? '',
            $employee->user->role->description ?? '',
            $employee->phone,
            $employee->group->name ?? '',
            $employee->department->name ?? '',
            $employee->hiring_date,
            $employee->position->name ?? '',
            $employee->passport_number,
            $employee->address,
            $employee->birthday,
            $employee->comment,
            $employee->payment_type == 'hourly' ? 'Соатлик' : ($employee->payment_type == 'daily' ? 'Кунлик' : 'Ойлик'),
            $employee->salary_visible ? $employee->salary : '----', // <-- shart bilan
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // ID
            'B' => 40,  // ФИО
            'C' => 15,  // Логин
            'D' => 35,  // Разрешение
            'E' => 15,  // Телефон
            'F' => 18,  // Группа
            'G' => 18,  // Отдел
            'H' => 15,  // Ишга келган сана
            'I' => 18,  // Позиция
            'J' => 15,  // Паспорт
            'K' => 30,  // Адрес
            'L' => 15,  // Дата рождения
            'M' => 25,  // Комментарий
            'N' => 15,  // Тўлов тури
            'O' => 10,  // Ойлик
        ];
    }
}