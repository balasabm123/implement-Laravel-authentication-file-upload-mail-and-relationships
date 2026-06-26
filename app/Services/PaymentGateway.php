<?php

namespace App\Services;

class PaymentGateway
{
    public function charge($amount)
    {
        return "Paid ₹{$amount}";
    }
}