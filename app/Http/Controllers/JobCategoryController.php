<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JobCategory;

class JobCategoryController extends Controller
{
public function index()
{
    return response()->json([
        'categories' => JobCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
    ]);
}
}