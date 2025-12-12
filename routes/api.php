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
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ReactionController;
use App\Http\Controllers\Api\ShareController;
use App\Models\Category;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\VideoReactionController;
use App\Http\Controllers\CommentReactionController;
use App\Http\Controllers\Api\ReplyController;


Route::post('replies/{reply}/react', [ReplyController::class, 'react']);

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});

Route::get('/videos/{id}/reactions', [VideoReactionController::class, 'index']); // public

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/comments/{comment}/reaction', [CommentReactionController::class, 'toggle']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/videos/{id}/reaction', [VideoReactionController::class, 'store']);
    Route::delete('/videos/{id}/reaction', [VideoReactionController::class, 'destroy']);
});

Route::get('/admin', [AdminController::class, 'show']);

Route::get('/categories', function () {
    return Category::all();
});

Route::middleware('auth:sanctum')->group(function(){
    Route::get('/videos', [VideoController::class,'index']);
    Route::post('/videos', [VideoController::class,'store']);
    Route::get('/videos/{video}', [VideoController::class,'show']);
    Route::put('/videos/{video}', [VideoController::class,'update']);
    Route::delete('/videos/{video}', [VideoController::class,'destroy']);
    Route::get('/videos/{video}/download', [VideoController::class,'download']);
    Route::post('/videos/{video}/save', [VideoController::class,'saveToLibrary']);
    Route::delete('/videos/{video}/save', [VideoController::class,'removeFromLibrary']);

    Route::get('/videos/{video}/comments', [CommentController::class, 'index']);
    Route::post('/videos/{video}/comments', [CommentController::class, 'store']);

    Route::put('/comments/{comment}', [CommentController::class,'update']);
    Route::delete('/comments/{comment}', [CommentController::class,'destroy']);
    

    Route::post('/videos/{video}/share', [ShareController::class,'share']);
});


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
