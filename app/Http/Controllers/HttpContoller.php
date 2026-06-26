<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class HttpContoller extends Controller
{
    public function showhttp()
    {

        $response = Http::get('https://jsonplaceholder.typicode.com/posts');
        
        return response()->json([
            'data' => $response->json(),
            'message' => 'I am at show method of HttpController'
        ]);
    }
}
