<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class extendFiles extends Controller
{
    //
    public function commonFile(){
        return view('extendfiles');
    }
    public function search(Request $request){
        // dd($request->all()); 
        $from = $request->input('from');
        $to = $request->input('to');
        $departure_date = $request->input('departure_date');
        $return_date = $request->input('return_date');
        $passengers = $request->input('passengers');

        // Perform your search logic here, e.g., query the database or an external API

        // For demonstration purposes, let's just return the input values
        return view('booking.prebook', compact('from', 'to', 'departure_date', 'return_date', 'passengers'));
    }   
}
