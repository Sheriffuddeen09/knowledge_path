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
        $validator = Validator::make($request->all(), [
            'choice' => 'required|in:sell_online_content,create_free_content,arabic_teacher',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        // Save the choice to user table
        $user->admin_choice = $request->choice;
        $user->save();

        // Determine redirect
        if (in_array($request->choice, ['sell_online_content', 'create_free_content'])) {
            $redirect = route('admin.dashboard');
        } else { // arabic_teacher
            $redirect = route('admin.teacher_form'); // fill form next
        }

        return response()->json([
            'status' => true,
            'message' => 'Choice saved successfully',
            'redirect' => $redirect,
        ]);
    }
}
