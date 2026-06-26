<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PaymentGateway;

class PaymentController extends Controller
{
    //
     public function pay(PaymentGateway $paymentGateway)
    {
        return $paymentGateway->charge(500);
    }
}
