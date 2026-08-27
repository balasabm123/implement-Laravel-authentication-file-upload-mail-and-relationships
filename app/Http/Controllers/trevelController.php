<?php

namespace App\Http\Controllers;

use App\Mail\travelemail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\mail;
use App\Jobs\resultJob;

class trevelController extends Controller
{
    //
    function send_email(Request $request)
    {
        $to = $request->to;
        $msg = $request->message;
        $subject = $request->subject;

        //  Email job send in bulk 
        $user = User::all();    
        foreach($user as $userEmail){ 
               resultJob::dispatch($userEmail->email,$userEmail->name);
        } 
         //  Email job send in bulk
        
        // Mail::to($to)->send(new travelemail($msg, $subject));
        $request->session()->flash('message', 'Email sent successfull');
        return view('emailForm');
    }
}
