<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SessionUser extends Controller
{
    //
    function login(Request  $request){ 
        $user_details= array();
        $user_details['name']= $request->input('name');
        $user_details['email']= $request->input('email');
        $user_details['mobile']= $request->input('mobile');
        $user_details['password']= $request->input('password'); 
        $request->session()->put('user',$user_details); 
        $request->session()->flash('message','Login successful');
        return redirect('sessionProfile');
        
    }
    function logout(Request $request){
        $request->session()->forget('user'); // remove user session
        $request->session()->flush(); // clear all session data 
        return redirect('/login');
    }
}
