<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\SupportMail;

class SupportController extends Controller
{

    public function store(Request $request)
    {

        $validated = $request->validate([

            'fullName'=>[
                'required',
                'string',
                'max:255'
            ],

            'email'=>[
                'required',
                'email'
            ],

            'issue'=>[
                'required',
                'string'
            ],

            'message'=>[
                'required',
                'string',
                'min:10'
            ],

        ]);


        Mail::to(
            env("SUPPORT_EMAIL")
        )->send(

            new SupportMail(
                $validated
            )

        );


        return response()->json([

            "message"=>
            "Your support request has been submitted successfully."

        ]);

    }

}