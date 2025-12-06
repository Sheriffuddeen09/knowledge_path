<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;

class AdminController extends Controller
{
    public function show()
    {
        $admin = User::where('role', 'admin')->first(); // get first admin
        return response()->json($admin);
    }
}
