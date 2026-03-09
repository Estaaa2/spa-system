<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailController extends Controller
{
    public function sendWelcomeMail()
    {
        $data = [
            'name'    => 'John Doe',
            'message' => 'Thanks for registering!'
        ];

        Mail::to('recipient@example.com')->send(new WelcomeMail($data));

        return back()->with('success', 'Email sent successfully!');
    }
}
