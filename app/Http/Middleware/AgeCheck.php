<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AgeCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // echo "From age check middleware"."<br>";
        // echo "Age is : ".$request->age."<br>";
        if($request->age < 18)
        {
            echo "Under age 18 not allowed...!!!!!!!!!"; die;
        }
        return $next($request);
    }
}
