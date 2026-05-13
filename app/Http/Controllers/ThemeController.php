<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ThemeController extends Controller
{
    public function getTheme()
    {
        $user = Auth::user();

        return response()->json([
            'theme_mode' =>
                $user->theme_mode ?? 'light',

            'theme_color' =>
                $user->theme_color ?? 'blue',

            'text_color' =>
                $user->text_color ?? 'auto',
        ]);
    }

    public function updateTheme(Request $request)
    {
        $request->validate([
            'theme_mode' => 'required',
            'theme_color' => 'required',
            'text_color' => 'required',
        ]);

        $user = Auth::user();

        $user->theme_mode =
            $request->theme_mode;

        $user->theme_color =
            $request->theme_color;

        $user->text_color =
            $request->text_color;

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Theme updated',
        ]);
    }
}
