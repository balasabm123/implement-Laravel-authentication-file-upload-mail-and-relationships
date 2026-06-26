<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\student;

class StudentController extends Controller
{
    function getStudetsdata()
    {
        /*$data = student::where('name',"test")->get(); 
        $data = student::all(); 
        $data = student::find(2);
        echo "<pre>";
        print_r($data); die;
        $data = student::insert([
            'name'=>"bala",
            'email'=>"bala12233@gmail.com",
            'age'=>30,
            'batch'=>"20024",
            ]); */
        $data = student::all();     
        return view('student',['data'=>$data]);
    }
}
