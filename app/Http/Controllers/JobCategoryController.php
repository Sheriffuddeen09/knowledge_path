<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;


class JobCategoryController extends Controller
{
//  public function index()
//  {
//  return JobCategory::orderBy('name')->get();
//  }
// }
public function index()
{
 return Job::with([
 'creator.jobProfile',
 'category'
 ])
 ->where('active',true)
 ->latest()
 ->get();
}
}