<?php

namespace App\Http\Controllers;

use App\Mail\travelemail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\mail;

class trevelController extends Controller
{
    //
    function send_email(Request $request){
        $to=$request->to;
        $msg=$request->message;
        $subject=$request->subject;
        Mail::to($to)->send(new travelemail($msg,$subject));
        $request->session()->flash('message','Email sent successfull');
        return view('emailForm');
    }
}
