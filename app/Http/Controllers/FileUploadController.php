<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FileUploadController extends Controller
{
    /*public function upload(Request $request){
        $file = $request->file('file');
        $filename = time().'_'.$file->getClientOriginalName();
        $file->move(public_path('uploads'), $filename);
        return back()->with('success', 'File uploaded successfully');
    }*/
    function upload(Request $request){
        $path =$request->file('file')->store('public'); 
        $filenamearray=explode('/',$path);
        $filename=$filenamearray[1];
        return view('displayiamge', ['filename' => $filename]);

    }    
}
