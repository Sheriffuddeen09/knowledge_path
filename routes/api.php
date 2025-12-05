<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\AdminChoiceController;
use App\Http\Controllers\TeacherFormController;
use App\Models\User;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\EnsureTeacherChoice;


Route::post('/send-otp', [OtpController::class, 'sendOtp']);
Route::post('/verify-otp', [OtpController::class, 'verifyOtp']);

Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [LoginController::class, 'logout']);
Route::post('/check', [RegisterController::class, 'checkBeforeNext']);

Route::post('/check-email', function (Illuminate\Http\Request $request) {
    $exists = \App\Models\User::where('email', $request->email)->exists();

    return response()->json(['exists' => $exists]);
});
Route::post('/check-phone', function (Illuminate\Http\Request $request) {
    $exists = \App\Models\User::where('phone', $request->phone)->exists();

    return response()->json(['exists' => $exists]);
});


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->get('/user-status', function (Request $request) {
    $user = $request->user();

    if ($user) {
        return response()->json([
            'status' => 'logged_in',
            'user' => $user,
            'admin_choice' => $user->admin_choice,
            'teacher_profile_completed' => $user->teacher_profile_completed
        ]);
    }

    return response()->json([
        'status' => 'guest'
    ]);
});

Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink']);
Route::post('/reset-password', [ResetPasswordController::class, 'resetPassword']);

// routes/api.php
Route::middleware('auth:sanctum')->get('/dashboard/notifications', [DashboardController::class, 'notifications']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/student/dashboard', function () {
        return response()->json(['message' => 'Student dashboard']);
    })->name('student.dashboard');

    Route::post('/admin/choose-choice', [AdminChoiceController::class, 'store'])
        ->name('admin.choose_choice');

        Route::middleware([
        EnsureTeacherChoice::class
    ])->group(function () {

        Route::get('/admin/teacher-form', [TeacherFormController::class, 'index']);
        Route::post('/admin/teacher/save', [TeacherFormController::class, 'submitForm']);

        Route::get('/teacher-form', [TeacherFormController::class, 'getTeacherForm'])
        ->middleware('auth:sanctum');

        Route::get('/teacher', [TeacherFormController::class, 'allTeachers'])
        ->middleware('auth:sanctum');

    });

    Route::get('/admin/dashboard', function () {
        return response()->json(['message' => 'Admin dashboard']);
    })->name('admin.dashboard');
});
