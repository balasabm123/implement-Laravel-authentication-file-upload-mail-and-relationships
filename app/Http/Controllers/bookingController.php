<?php

namespace App\Http\Controllers;

use App\Models\booking;
use App\Models\User;
use Illuminate\Http\Request;


class bookingController extends Controller
{
    //
    public function bookingList()
    {
        $booking = booking::all()->load('booking_user');
        // belongsTo relationship is used to get the data from the related table.
        // here booking_user is the function name in the booking model which is used to get the data from the user table. 
        // we can use any name for the function but it should be same as the function name in the model. 
        // here we are using booking_user because it is a good practice to use the name of the related table as the function name. 
        // we can also use any other name but it should be meaningful.  
        return view('booking.bookingList', compact('booking'));
    }
    public function manyToOne()
    {
        $booking = Booking::with('user')->get();
        return view('booking.bookingListOneToMany', compact('booking'));
    }
}
