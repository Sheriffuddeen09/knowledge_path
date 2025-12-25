<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Models\Video;
use App\Http\Controllers\VideoStreamController;

Route::get('/video/stream/{video}', [VideoStreamController::class, 'stream'])
    ->name('video.stream')
    ->middleware('signed');


Route::get('/reset-password/{token}', function ($token) {
    $email = request('email');
    return redirect("http://localhost:3000/reset-password?token=$token&email=$email");
})->name('password.reset');


Route::get('/video', function () {
    return Video::all();
});


Route::get('/users', function () {
    return response()->json(['message' => 'Web working']);
});


