<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
class HomeController extends Controller
{
    public function show()
    {
        echo env('APP_URL');
        echo "<br>";
        echo "<br>";
        echo "I am at show method of HomeController";
        die;
        return to_route('hm');
    }
    function test()
    {
        echo "test working here";
    }
    function add()
    {
        echo "Add working here";
    }
    function myname($name)
    {
        echo "My name is " . $name;
    }
    function get_groupmiddleware()
    {
        echo "Group middleware working here";
    }
    function getdata()
    {
        // $data = DB::table('users')->get();
        $data = DB::select('select * from users');

        return view('users', compact('data'));
    }
    function getdatas()
    { 
        $dataa = DB::table('users')->insert([
            "name" => "welcome",
            "email" => "welcome2220@gmail.com",
            "password" => "324324234"
        ]);
        if ($dataa) {
            return "Data inserted";
        } else {
            return "Data not inserted";
        }
        $dataq = DB::table('users')->where('id', 1)->get();
        $dataq = DB::table('users')->where('id', 7)->delete();
        $dataq = DB::table('users')->where('id', 3)->update([
                     "name" => "welcomeback",
            "email" => "welcomeback213@gmail.com",
            "password" => "324324234"
        ]);
        $data = DB::table('users')->get();
        return response()->json([
            'data' => $data,
            'message' => 'I am at getdatas method of HomeController'
        ]);
    }
}
