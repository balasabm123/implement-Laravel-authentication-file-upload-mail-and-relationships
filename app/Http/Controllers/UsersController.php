<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UsersController extends Controller
{
   function addUser(Request $req)
   {
      $req->validate([
         'name' => 'required | min:3 | max:20 | alpha',
         'email' => 'required|email | unique:users,email',
         'password' => 'required|min:6',
         'age' => 'required|numeric'
      ],
      [
         'name.required' => 'Name is required',
         'name.min' => 'Name must be at least 3 characters',
         'name.max' => 'Name must be less than 20 characters',
         'name.alpha' => 'Name must only contain letters',
         'email.required' => 'Email is required',
         'email.email' => 'Email must be a valid email address',
         'email.unique' => 'Email is already taken',
         'password.required' => 'Password is required',
         'password.min' => 'Password must be at least 6 characters',
         'age.required' => 'Age is required',
         'age.numeric' => 'Age must be a number'
      ]);
      dd($req->all());
      return "Name: " . $req->name . " Email: " . $req->email;
   }
}
