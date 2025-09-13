<?php

namespace App\Exports;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmployeeAttendanceExport implements FromCollection, WithHeadings, WithMapping
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = Carbon::parse($startDate)->startOfDay();
        $this->endDate   = Carbon::parse($endDate)->endOfDay();
    }

    public function collection()
    {
        return Employee::with(['user', 'position'])
            ->where('status', 'working')
            ->get();
    }

    public function map($employee): array
    {
        $totalDays = $this->startDate->diffInDaysFiltered(function (Carbon $date) {
                // yakshanba = 0, ishlamaydigan kunni chiqarib tashlaymiz
                return $date->dayOfWeek != Carbon::SUNDAY;
            }, $this->endDate) + 1;

        // Sababli yo‘qliklar -> faqat holidays
        $holidayCount = $employee->employeeHolidays()
            ->where(function ($q) {
                $q->whereBetween('start_date', [$this->startDate, $this->endDate])
                    ->orWhereBetween('end_date', [$this->startDate, $this->endDate]);
            })
            ->count();

        // Sababsiz yo‘qliklar -> absences + attendance.status=absent
        $absenceCount = $employee->employeeAbsences()
            ->where(function ($q) {
                $q->whereBetween('start_date', [$this->startDate, $this->endDate])
                    ->orWhereBetween('end_date', [$this->startDate, $this->endDate]);
            })
            ->count();

        $absentByAttendance = $employee->attendances()
            ->where('status', 'absent')
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->whereRaw('EXTRACT(DOW FROM date) != 0')
            ->count();

        $unexcusedCount = $absenceCount + $absentByAttendance;

        // Kelgan kunlari
        $presentCount = $totalDays - ($holidayCount + $unexcusedCount);

        return [
            $employee->id,
            $employee->name,
            $holidayCount,     // sababli
            $unexcusedCount,   // sababsiz
            $presentCount,     // kelgan
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'F.I.Sh',
            'Sababli',
            'Sababsiz',
            'Kelgan',
        ];
    }
}
