<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class AdminChoiceController extends Controller
{
    /**
     * Store the admin choice (sell online content, create free content, arabic teacher)
     */

   public function store(Request $request)
{
    $request->validate([
        'choice' => 'required|in:sell_online_content,create_free_content,arabic_teacher',
    ]);

    $user = $request->user(); // logged-in admin

    // 🔥 If admin already selected once, block them
    if ($user->admin_choice !== null) {
        return response()->json([
            'message' => 'You have already chosen a choice.',
            'redirect' => '/admin/dashboard'
        ], 200);
    }

    // 🔥 First time saving
    $user->admin_choice = $request->choice;
    $user->save();

    return response()->json([
        'message' => 'Choice saved successfully.',
        'redirect' => $request->choice === 'arabic_teacher'
            ? '/admin/teacher-form'
            : '/admin/dashboard'
    ], 200);
}

   }
