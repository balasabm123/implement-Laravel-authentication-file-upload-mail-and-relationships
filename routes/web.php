<?php

// feat: implement Laravel CRUD, authentication, file upload, mail, and relationships
// Laravel+react : https://youtu.be/1Vj73iP_7vk
// Laravel 12 tutorial : https://youtu.be/0M84Nk7iWkA?list=PLLQuc_7jk__W2rpfMsXiOiRn_THCD2R0U
// https://chatgpt.com/s/t_6a4330a73a7081919d08fc16bfa6bcb9 -JWT token and laravel 12 
use App\Http\Controllers\bookingController;
 use App\Http\Controllers\EmployeeController;
 use App\Http\Controllers\extendFiles;
 use App\Http\Controllers\FileUploadController;
 use App\Http\Controllers\HomeController;
use App\Http\Controllers\HttpContoller;
 use App\Http\Controllers\SessionUser;
 use App\Http\Controllers\StudentController;
use App\Http\Controllers\trevelController;
 use App\Http\Controllers\UserController;
use App\Http\Controllers\UsersController; 
use App\Providers\PaymentGateway;
use Faker\Provider\ar_EG\Payment;
use Illuminate\Support\Facades\Route;
 use App\Http\Middleware\AgeCheck;
Route::get('/', function () {
    return view('home');
});

Route::get('/about/{name}', function($name){
    return view('about', ['name' => $name]);
});

Route::get('/users/{name}',[UserController::class, 'users']);

Route::get('/admin/login',[UserController::class, 'login']);

Route::get('/user-form', function(){
    return view('user-form');
});

Route::post('/addUser',[UsersController::class, 'addUser']);

Route::get('/register', function(){
    return view('register');
});

Route::view('/hm/test', 'homee')->name('hm');

//name routes is used to generate url in blade file and controller file. we can change the url in future without changing the blade and controller file. we just need to change the url in routes file.

Route::get('show', [HomeController::class, 'show'])->name('hm'); 
// Route::get('/pay', [PaymentGateway::class, 'pay']); 
// Route::get('/test',[HomeController::class,'test']);
// Route::get('/add',[HomeController::class,'add']);

Route::controller(HomeController::class)->group(function(){
Route::get('/test','test');
Route::get('/add','add');
Route::get('/myname/{name}','myname');
});

Route::get('testhere',[HomeController::class,'get_groupmiddleware'])->middleware('check1'); 

Route::get('testage', [HomeController::class, 'getdata']) ->middleware(AgeCheck::class);

Route::get('students',[StudentController::class, 'getStudetsdata']); 

Route::get('/api', [HttpContoller::class, 'showhttp']);
Route::get('getdata', [HomeController::class, 'getdatas']);

Route::view('login','liginSession')->name('loginsession');
Route::view('sessionProfile','sessionProfile');
Route::view('lg-in','login')->name('lg-in');
Route::post('login',[SessionUser::class,'login'])->name('login');
 
Route::post('logout',[SessionUser::class,'logout']);

Route::middleware('SetLang')->group(function () {

    Route::view('fileupload', 'fileupload')->name('fileuplaodandLangaugae');

    Route::post('upload', [FileUploadController::class, 'upload']);

    Route::get('language/{lang}', function ($lang) {
        session()->put('lang', $lang);
        return redirect('fileupload');
    });

}); 

Route::get('/employee', [EmployeeController::class, 'employeeShow']);
Route::post('/addEmployee',[EmployeeController::class, 'addEmployee']);
Route::get('/employeeList',[EmployeeController::class, 'employeeList'])->name('employeeList');

Route::get('employee/delete/{id}', [EmployeeController::class, 'deleteEmployee']);
Route::get('employee/edit/{id}', [EmployeeController::class, 'editEmployee']);
Route::post('employee/updateEmployee/{id}', [EmployeeController::class, 'updateEmployee']);

Route::get('search',[EmployeeController::class,'searchlist']);
Route::post('employee/bulkDelete', [EmployeeController::class, 'bulkDelete']);

Route::view('aboutpage','aboutpage')->name('layoutWithComponents');
Route::view('homepage','homepage')->name('layoutWithComponents2');

Route::view('logged-in','loginn')->name('loginn');

Route::view('contactus','contactUs')->name('contactus');

Route::get('ExploreWorld',[extendFiles::class,'commonFile'])->name('ExploreWorld'); 
Route::post('prebook',[extendFiles::class,'search'])->name('prebook');  

Route::get('bookingList',[bookingController::class,'bookingList'])->name('bookingList');
Route::get('manyToOne',[bookingController::class,'manyToOne'])->name('manyToOne');
Route::post('send_email',[trevelController::class,'send_email'])->name('send_email');
Route::view('email','emailForm')->name('email');