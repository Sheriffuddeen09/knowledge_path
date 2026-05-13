<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TwoStepController extends Controller
{
    public function status(Request $request)
    {
        return response()->json([
            'enabled' =>
                (bool) $request->user()->two_step_enabled
        ]);
    }

    public function setup(Request $request)
    {
        $request->validate([
            'pin' => 'required|digits:6'
        ]);

        $user = $request->user();

        $user->two_step_enabled = true;

        $user->two_step_pin =
            Hash::make($request->pin);

        $user->save();

        return response()->json([
            'message' =>
                'Two-step enabled successfully'
        ]);
    }

    public function change(Request $request)
    {
        $request->validate([
            'pin' => 'required|digits:6'
        ]);

        $user = $request->user();

        $user->two_step_pin =
            Hash::make($request->pin);

        $user->save();

        return response()->json([
            'message' =>
                'PIN changed successfully'
        ]);
    }

    public function remove(Request $request)
    {
        $user = $request->user();

        $user->two_step_enabled = false;

        $user->two_step_pin = null;

        $user->save();

        return response()->json([
            'message' =>
                'Two-step turned off'
        ]);
    }
}