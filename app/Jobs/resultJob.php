<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Mail\travelemail;
use Illuminate\Support\Facades\Mail;

class resultJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public $email,
        public $name 
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    { 
           Mail::to($this->email)->send(new travelemail($this->name,$this->email));
    }
}
