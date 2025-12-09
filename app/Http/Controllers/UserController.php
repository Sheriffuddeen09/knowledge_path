<?php 

class UserController extends Controller
{
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
