<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    //

    public function users($name)
    {
        return view('about', ['name' => $name]);
    }
    public function login()
    {
        $namee = "John Dork";
        $users = ['Alice', 'Bob', 'Charlie']; 
        return view('admin.login',['namee' => $namee, 'users' => $users]);
    }   
}
