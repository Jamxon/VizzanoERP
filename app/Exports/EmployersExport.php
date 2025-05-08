<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Illuminate\Support\Facades\Storage;

class EmployersExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Employee::with(['user', 'group', 'role']) // Tegishli modelni chaqirish
        ->select([
            'employees.name', // F.I.O
            'users.username', // User username
            'groups.name as group_name', // Group name
            'employees.phone', // Telefon
            'employees.payment_type', // To'lov turi
            'employees.salary', // Oylik maosh
            'employees.hiring_date', // Ishga kirgan sana
            'employees.status', // Status
            'employees.address', // Manzil
            'employees.passport_number', // Passport
            'roles.name as role_name', // Role name
        ])
            // 'leftJoin'ni ishlatish, agar group_id null bo'lsa, hali ham xodimni olish
            ->leftJoin('users', 'employees.user_id', '=', 'users.id')
            ->leftJoin('groups', 'employees.group_id', '=', 'groups.id')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->get();
    }


    public function headings(): array
    {
        return [
            'F.I.O',
            'Username',
            'Guruh',
            'Telefon',
            'To\'lov turi',
            'Oylik maosh',
            'Ishga kirgan sana',
            'Status',
            'Manzil',
            'Passport',
            'Rol',
        ];
    }
}
