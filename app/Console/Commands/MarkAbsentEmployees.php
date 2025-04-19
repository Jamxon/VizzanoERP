<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MarkAbsentEmployees extends Command
{
    protected $signature = 'attendance:mark-absent';
    protected $description = 'Bugun kelmagan hodimlarni attendance jadvaliga yozish';

    public function handle(): void
    {
        $today = Carbon::today()->toDateString();

        $allEmployees = Employee::all()->pluck('id')->toArray();

        $presentEmployeeIds = Attendance::whereDate('date', $today)
            ->pluck('employee_id')
            ->toArray();

        $absentEmployeeIds = array_diff($allEmployees, $presentEmployeeIds);

        foreach ($absentEmployeeIds as $employeeId) {
            Attendance::create([
                'employee_id' => $employeeId,
                'date' => $today,
                'status' => 'absent',
                'check_in' => null,
                'check_out' => null,
            ]);
        }

        $this->info('Kelmaganlar bazaga qo‘shildi: ' . count($absentEmployeeIds));
    }
}
