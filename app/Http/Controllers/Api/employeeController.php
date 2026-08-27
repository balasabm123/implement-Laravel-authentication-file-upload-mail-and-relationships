<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Validator;

class employeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $employee = Employee::orderBy('created_at', 'desc')->get();

        return $employee;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // return $request->input();
        $rules = array(
            'name' => 'required | min:2 | max:10',
            'email' => 'required|email|unique:employees,email',
            'mobile' => 'required | min:10 | max:15'

        );
        $validation = Validator::make($request->all(), $rules);
        if ($validation->fails()) {
            return $validation->errors();
        }
        $employee = new Employee();
        $employee->name = $request->name;
        $employee->email = $request->email;
        $employee->mobile = $request->mobile;
        $employee->save();
        return $employee;
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $employee = Employee::find($id);
        if ($employee) {
            return $employee;
        } else {
            return "Record for id:$id not found";
        }

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $employee = Employee::find($id);
        $employee->name = $request->name;
        $employee->email = $request->email;
        $employee->mobile = $request->mobile;
        $employee->save();
        return $employee;

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {

        $employee = Employee::find($id);
        if ($employee) {
            $employee->delete();
            return "Record deleted succesfully";
        } else {
            return "Record id:$id not found";
        }
    }
}
