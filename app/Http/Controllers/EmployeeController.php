<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{

    public function employeeShow()
    {
        $page = "add";
        return view('employee.AddEmployee', ['page' => $page]);
    }
    public function addEmployee(Request $request)
    {
        $employee = new Employee();
        $employee->name = $request->input('name');
        $employee->email = $request->input('email');
        $employee->mobile = $request->input('mobile');
        $employee->save();
        return redirect('/employeeList');
    }

    public function employeeList()
    {
        $employees = Employee::orderBy('created_at', 'desc')->paginate(5);
        return view('employee.emplyeeList', ['employees' => $employees]);
    }
    public function deleteEmployee($id)
    {
        $employee = Employee::find($id);
        if ($employee) {
            $employee->delete();
        }
        return redirect('/employeeList');
    }
    public function editEmployee($id)
    {
        $employee = Employee::find($id); 
        return view('employee.addEmployee', ['employee' => $employee, 'page' => "updateEmployee"]);

    }
    public function updateEmployee(Request $request, $id)
    {
        $employee = Employee::find($id);
        if ($employee) {
            $employee->name = $request->input('name');
            $employee->email = $request->input('email');
            $employee->mobile = $request->input('mobile');
            $employee->save();
        }
        return redirect('/employeeList');
    }

    public function searchlist(Request $request)
    {

        // $employees=Employee::where('name','like',"%$request->search%")->get();
        $employees = Employee::where('name', 'like', "%{$request->search}%")
            ->orderBy('created_at', 'desc')
            ->paginate(5);

        return view('employee.emplyeeList', ['employees' => $employees, 'search' => $request->search]);
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids');
        if ($ids) {
            Employee::whereIn('id', $ids)->delete();
        }
        $request->session()->flash('success', 'Selected employees deleted successfully');
        return redirect('/employeeList');
    }
}
