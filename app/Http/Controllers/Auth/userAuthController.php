<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class userAuthController extends Controller
{
    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return ['result' => "User not found; Either username or password incorrect ", 'success' => false];
        }

        $success['Token'] = $user->createToken('myapp')->plainTextToken;
        $user['name'] = $user->name;
        return ['success' => true, 'result' => $success, "msg" => "User Registered Successfully"];
    }
    public function signUp(Request $req)
    {
        $input = $req->All();
        $input['password'] = bcrypt($input['password']);
        $user = User::create($input);
        $success['Token'] = $user->createToken('myapp')->plainTextToken;
        $user['name'] = $user->name;
        return ['success' => true, 'result' => $success, "msg" => "User Registered Successfully"];
    }
}
