<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Coursetitle;


class CoursetitleController extends Controller
{

    public function index()
    {
        $categories = Coursetitle::pluck('name'); // returns array of category names
        return response()->json($categories);
    }
}
