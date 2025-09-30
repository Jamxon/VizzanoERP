<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EmployeeMonthlyController extends Controller
{
    public function employeeMonthlySalaryStore(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month' => 'required|date_format:Y-m-d',
            'amount' => 'required|numeric|min:0',
        ]);

        $employee = \App\Models\Employee::find($request->employee_id);
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $existingRecord = \App\Models\EmployeeMonthlySalary::where('employee_id', $request->employee_id)
            ->where('month', $request->month)
            ->first();

        if ($existingRecord) {
            return response()->json(['error' => 'Salary record for this month already exists'], 400);
        }

        $salaryRecord = \App\Models\EmployeeMonthlySalary::create([
            'employee_id' => $request->employee_id,
            'month' => $request->month,
            'amount' => $request->amount,
            'status' => true,
            'created_by' => auth()->id(),
        ]);

        return response()->json(['message' => 'Salary record created successfully', 'data' => $salaryRecord], 201);
    }

    public function employeeMonthlyPieceworkStore(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month' => 'required|date_format:Y-m-d',
            'amount' => 'required|numeric|min:0',
        ]);

        $employee = \App\Models\Employee::find($request->employee_id);
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $existingRecord = \App\Models\EmployeeMonthlyPiecework::where('employee_id', $request->employee_id)
            ->where('month', $request->month)
            ->first();

        if ($existingRecord) {
            return response()->json(['error' => 'Piecework record for this month already exists'], 400);
        }

        $pieceworkRecord = \App\Models\EmployeeMonthlyPiecework::create([
            'employee_id' => $request->employee_id,
            'month' => $request->month,
            'amount' => $request->amount,
            'status' => true,
            'created_by' => auth()->id(),
        ]);

        return response()->json(['message' => 'Piecework record created successfully', 'data' => $pieceworkRecord], 201);
    }

    public function employeeMonthlySalaryUpdate(Request $request, $id)
    {
        $salaryRecord = \App\Models\EmployeeMonthlySalary::find($id);

        if (!$salaryRecord) {
            return response()->json([
                'error' => 'Salary record not found'
            ], 404);
        }

        // Faqat yuborilgan fieldlarni validatsiya qilamiz
        $validated = $request->validate([
            'amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|boolean',
        ]);

        $salaryRecord->fill($validated);
        $salaryRecord->save();

        return response()->json([
            'message' => 'Salary record updated successfully',
            'data' => $salaryRecord
        ], 200);
    }

    public function employeeMonthlyPieceworkUpdate(Request $request, $id)
    {
        $pieceworkRecord = \App\Models\EmployeeMonthlyPiecework::find($id);

        if (!$pieceworkRecord) {
            return response()->json([
                'error' => 'Piecework record not found'
            ], 404);
        }

        // Faqat yuborilgan fieldlarni validatsiya qilamiz
        $validated = $request->validate([
            'amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|boolean',
        ]);

        $pieceworkRecord->fill($validated);
        $pieceworkRecord->save();

        return response()->json([
            'message' => 'Piecework record updated successfully',
            'data' => $pieceworkRecord
        ], 200);
    }
}