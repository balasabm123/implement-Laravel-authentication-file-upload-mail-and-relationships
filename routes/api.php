<?php

use App\Http\Controllers\Api\employeeController;
use App\Http\Controllers\Auth\userAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/testapi', function(){
    return ['name'=>"test",'desc'=>"Hello welcome to API development"];
});


Route::post('signUp',[userAuthController::class,'signUp']);
Route::post('login',[userAuthController::class,'login']);


Route::group(['middleware'=>"auth:sanctum"],function(){ 
    Route::get('/employee',[employeeController::class,'index']);
    Route::post('/store',[employeeController::class,'store']);
    Route::delete('/destroy/{id}',[employeeController::class,'destroy']);
    Route::get('/show/{id}',[employeeController::class,'show']);
    Route::put('/update/{id}',[employeeController::class,'update']);
});
