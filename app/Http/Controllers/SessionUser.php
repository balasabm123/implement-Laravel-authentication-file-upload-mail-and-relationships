<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SessionUser extends Controller
{
    //
    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();
    // return $user;
        if (!$user) {
            return back()->with('error', 'Email not found');
        }

        if (!Hash::check($request->password, $user->password)) {
            return back()->with('error', 'Invalid password');
        }

        $request->session()->put('user', [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
        ]);

        return redirect('sessionProfile')
            ->with('message', 'Login successful');
    }
    /*function login(Request  $request){ 
        return $request->All();
        $user_details= array();
        $user_details['name']= $request->input('name');
        $user_details['email']= $request->input('email');
        $user_details['mobile']= $request->input('mobile');
        $user_details['password']= $request->input('password'); 
        $request->session()->put('user',$user_details); 
        $request->session()->flash('message','Login successful');
        return redirect('sessionProfile');

    }*/
    function logout(Request $request)
    {
        $request->session()->forget('user'); // remove user session
        $request->session()->flush(); // clear all session data 
        // return redirect('lg-in');
        return redirect()->route('lg-in');
    }
}
