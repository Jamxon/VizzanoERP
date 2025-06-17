<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class EmployeeExport implements FromCollection, WithMapping, WithHeadings, WithDrawings, WithColumnWidths
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
            ->when($filters['group_id'] ?? null, fn($q, $val) => $q->where('group_id', $val))
            ->when($filters['status'] ?? null, fn($q, $val) => $q->where('status', $val))
            ->when($filters['role_id'] ?? null, fn($q, $val) => $q->whereHas('user', fn($q) => $q->where('role_id', $val)));

        return $this->employees = $query->latest('updated_at')->get();
    }

    public function headings(): array
    {
        return [
            'ID', 'ФИО', 'Логин', 'Разрешение', 'Телефон', 'Группа', 'Отдел', 'Ишга келган сана',
            'Позиция', 'Паспорт', 'Адрес', 'Дата рождения',
            'Комментарий', 'Фото',
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
            '', // Bu yerga rasm joylashadi
        ];
    }

    public function drawings()
    {
        $drawings = [];

        foreach ($this->employees as $index => $employee) {
            if ($employee->img && file_exists(public_path('storage/' . Str::after($employee->img, 'storage/')))) {
                $drawing = new Drawing();
                $drawing->setName($employee->name);
                $drawing->setDescription('Rasm');
                $drawing->setPath(public_path('storage/' . Str::after($employee->img, 'storage/')));
                $drawing->setHeight(50);
                $drawing->setCoordinates('R' . ($index + 2)); // 'R' = 18-column
                $drawings[] = $drawing;
            }
        }

        return $drawings;
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
            'J' => 18,  // Позиция
            'N' => 15,  // Паспорт
            'O' => 30,  // Адрес
            'P' => 15,  // Дата рождения
            'Q' => 25,  // Комментарий
            'R' => 20,  // Фото
        ];
    }
}