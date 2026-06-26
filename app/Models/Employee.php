<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    //
    // protected $fillable = ['name', 'email', 'mobile'];
    protected function setNameAttribute($value){
        $this->attributes['name'] = ucfirst($value);
    } 
    protected function getmobileAttribute($value){
        return "+91" . $value;
    }  
}
