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
use App\Models\Coursetitle;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\VideoReactionController;
use App\Http\Controllers\CommentReactionController;
use App\Http\Controllers\Api\ReplyController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileVisibilityController;
use App\Http\Controllers\LiveClassController;
use App\Http\Controllers\StudentFriendController;
use App\Http\Controllers\AdminFriendController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatBlockController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StudentNotificationController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\ChatReportController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AssignmentResultController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamResultController;
use App\Http\Controllers\StudentBadgeController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostReactionController;
use App\Http\Controllers\PostCommentController;
use App\Http\Controllers\PostReportController;
use App\Http\Controllers\CommentReportController;

///report
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/posts', [PostController::class, 'store']);
    Route::get('/posts-get', [PostController::class, 'index']);
    Route::post('/posts-get/{post}', [PostController::class, 'show']);

    // Post Reactions reply
    Route::post('/post/{id}/reaction', [PostReactionController::class, 'store']);
    Route::delete('/post/{id}/reaction', [PostReactionController::class, 'destroy']);
    Route::get('/post/{id}/reactions', [PostReactionController::class, 'index']);

    // Comment 
    Route::get('/posts/{post}/comments', [PostCommentController::class, 'index']);
    Route::post('/posts/{post}/comments', [PostCommentController::class, 'store']);
    Route::post('/posts/{comment}/reaction', [PostCommentController::class, 'react']);
    Route::get('/posts/{comment}/reactions', [PostCommentController::class, 'reactions']);
    Route::put('/posts/{comment}/comment', [PostCommentController::class, 'update']);
    Route::delete('/posts/{comment}/comment', [PostCommentController::class, 'destroy']);
    
    Route::middleware('auth:sanctum')->get('/admin/reports', [VideoController::class, 'reportedVideos']);
    Route::post('/videos/{video}/share', [ShareController::class,'share']);

    //Report
    Route::post('/posts/report', [PostReportController::class, 'store']);
    Route::get('/posts/reports', [PostReportController::class, 'index']);

    Route::post('/comment/report', [CommentReportController::class, 'store']);
    Route::get('/comment/reports', [CommentReportController::class, 'index']);
});
    



Route::middleware('auth:sanctum')->group(function () {
    Route::get('/student-friend/relation/{profileId}', [StudentFriendController::class, 'relation']);
    Route::get('/student/profile/{id}', [StudentFriendController::class, 'show']);
    Route::get('/student/profile/accepted/{id}', [StudentFriendController::class, 'showAccepted']);
    Route::get('/student/me', [StudentFriendController::class, 'acceptedIndex']);
    Route::get('/student-friend', [StudentFriendController::class, 'studentsToAdd']);
    Route::post('/student-friend/request', [StudentFriendController::class, 'sendRequest']);
    Route::get('/student-friend/my-requests', [StudentFriendController::class, 'myRequests']);
    Route::get('/student-friend/all-requests', [StudentFriendController::class, 'allRequests']);
    Route::post('/student-friend/respond/{id}', [StudentFriendController::class, 'respond']);
    Route::get('/friend-notifications/requests', [StudentNotificationController::class, 'requestCount']);
    Route::delete('/requests/remove-temporary/{id}', [StudentFriendController::class, 'removeTemporarily']);
  
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/analytics/students', [AnalyticsController::class, 'studentAnalytics']); 
    Route::get('/analytics/accuracy', [AnalyticsController::class, 'accuracy']); 
    Route::get('/analytics/performance', [AnalyticsController::class, 'performanceSplit']);
    Route::get('/analytics/accuracyId', [AnalyticsController::class, 'accuracyId']); 
    Route::get('/analytics/performanceId', [AnalyticsController::class, 'performanceSplitId']);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin-friend/relation/{profileId}', [AdminFriendController::class, 'relation']);
    Route::get('/admin/profile/{id}', [AdminFriendController::class, 'show']);
    Route::get('/admin/profile/accepted/{id}', [AdminFriendController::class, 'showAccepted']);
    Route::get('/admin/me', [AdminFriendController::class, 'showAcceptedIndex']);
    Route::get('/admin-friend', [AdminFriendController::class, 'adminsToAdd']);
    Route::post('/admin-friend/request', [AdminFriendController::class, 'sendRequest']);
    Route::get('/admin-friend/my-requests', [AdminFriendController::class, 'myRequests']);
    Route::get('/admin-friend/all-requests', [AdminFriendController::class, 'allRequests']);
    Route::post('/admin-friend/respond/{id}', [AdminFriendController::class, 'respond']);
    Route::get('/friend-notification/requests', [AdminNotificationController::class, 'requestCount']);
    Route::delete('/requests/remove-temporary/{id}', [AdminFriendController::class, 'removeTemporarily']);
  
});

// 


Route::middleware('auth:sanctum')->get(
    '/student/badges',
    [StudentBadgeController::class, 'badges']
);



Route::middleware('auth:sanctum')->group(function () {

    // 🔹 Assignments
    Route::get('/assignments', [AssignmentController::class, 'index']);
    Route::post('/assignments', [AssignmentController::class, 'store']);
    Route::get('/assignments/{id}', [AssignmentController::class, 'show']);
    Route::middleware('auth:sanctum')->group(function () {
    Route::get('/teacher/assignments/{id}', [AssignmentController::class, 'preview']);
    Route::post('/assignments/{id}/block', [AssignmentController::class, 'block']);
    Route::post('/assignments/{id}/unblock', [AssignmentController::class, 'unblock']);});
    Route::get('/notifications/unread-count', [AssignmentController::class, 'unreadCount']);
    Route::get('/assignments/{id}/analytics', [AssignmentController::class, 'analytics']);
    Route::middleware('auth:sanctum')->post('/assignments/save-progress',[AssignmentController::class, 'saveProgress']);
    Route::post('/student/assignment/{token}/submit',[AssignmentController::class, 'submitByToken']);
    Route::post('/student/assignments/{token}/start',[AssignmentController::class, 'begin']);
    Route::post('/student/assignments/{token}/restart',[AssignmentController::class, 'restart']);
    Route::get('/student/assignments/{token}/resume',[AssignmentController::class, 'resume']);
    Route::get('/student/assignments/{token}', [AssignmentController::class, 'start']);
    Route::get('/student/library', [AssignmentController::class, 'library']);
    Route::post('/student/assignments/reschedule',[AssignmentController::class, 'reschedule'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    // Results
    Route::get('/assignment-results', [AssignmentResultController::class, 'index']);
    Route::get('/assignment-results/{result}', [AssignmentResultController::class, 'show']);
    Route::delete('/assignment-results/{assignment}', [AssignmentResultController::class, 'destroy']);
});



// Exam  allTeacher

Route::middleware('auth:sanctum')->group(function () {

    // 🔹 Assignments
    Route::get('/exams', [ExamController::class, 'index']);
    Route::post('/exams', [ExamController::class, 'store']);
    Route::get('/exams/{id}', [ExamController::class, 'show']);
    Route::middleware('auth:sanctum')->group(function () {
    Route::get('/teacher/exams/{id}', [ExamController::class, 'preview']);
    Route::post('/exams/{id}/block', [ExamController::class, 'block']);
    Route::post('/exams/{id}/unblock', [ExamController::class, 'unblock']);
});
    Route::get('/notifications/unread-count', [ExamController::class, 'unreadCount']);
    Route::get('/exams/{id}/analytics', [ExamController::class, 'analytics']);
    Route::middleware('auth:sanctum')->post('/exams/save-progress',[ExamController::class, 'saveProgress']);
    Route::post('/student/exam/{token}/submit',[ExamController::class, 'submitByToken']);
    Route::post('/student/exams/{token}/start',[ExamController::class, 'begin']);
    Route::post('/student/exams/{token}/restart',[ExamController::class, 'restart']);
    Route::get('/student/exams/{token}/resume',[ExamController::class, 'resume']);
    Route::get('/student/exams/{token}', [ExamController::class, 'start']);
    Route::get('/student/library/exam', [ExamController::class, 'library']);
    Route::post('/student/exams/reschedule',[ExamController::class, 'reschedule'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    // Results
    Route::get('/exam-results', [ExamResultController::class, 'index']);
    Route::get('/exam-results/{result}', [ExamResultController::class, 'show']);
    Route::delete('/exam-results/{result}', [ExamResultController::class, 'destroy']);
});






Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications/requests', [NotificationController::class, 'requestCount']);
    Route::get('/notifications/messages', [NotificationController::class, 'messageCount']);
    Route::post('/chats/{chat}/seen', [NotificationController::class, 'markAsRead']);
    Route::delete(
  '/live-class/request/{id}/clear-teacher',
    [LiveClassController::class, 'clearByTeacher']
    );

Route::delete(
  '/live-class/request/{id}/clear-student',
  [LiveClassController::class, 'clearRequestByStudent']
);

Route::middleware('auth:sanctum')->get('/live-class/teacher-requests-summary', [LiveClassController::class, 'requestsSummary']);

});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/live-class/request', [LiveClassController::class, 'sendRequest']);
    Route::get('/live-class/my-requests', [LiveClassController::class, 'myRequests']);
    Route::get('/live-class/all-requests', [LiveClassController::class, 'allRequests']);
    Route::post('/live-class/respond/{id}', [LiveClassController::class, 'respond']);
  
});


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/chats/{chat}/block', [ChatBlockController::class, 'block']);
    Route::delete('/chats/{chat}/unblock', [ChatBlockController::class, 'unblock']);
});


// 
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/chats', [ChatController::class,'index']);
    Route::get('/chats/{chat}/messages', [ChatController::class,'messages']);
    Route::post('/messages', [ChatController::class,'send']);
    Route::put('/messages/{message}', [ChatController::class,'edit']);
    Route::delete('/messages/{message}', [ChatController::class,'delete']);
    Route::post('/messages/voice', [ChatController::class, 'sendVoice']);
    Route::post('/messages/{message}/seen', [ChatController::class, 'markSeen']);
    Route::post('/messages/typing', [ChatController::class, 'typing']);
    Route::post('/messages/react', [ChatController::class, 'react']);
    Route::post('/chat/report', [ChatReportController::class, 'store']);
    Route::get('/chat/reports', [ChatReportController::class, 'index']);
    Route::delete('/messages/{message}', [ChatController::class, 'destroy']);
    Route::delete('/messages/{message}/forward', [ChatController::class, 'forward']);
    Route::post('/messages/forward-multiple', [ChatController::class, 'forwardMultiple']);
    Route::post('/messages/react', [ChatController::class, 'toggle']);
    Route::put('/messages/{message}', [ChatController::class, 'edit']);
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/block', [BlockController::class, 'block']);
    Route::post('/unblock', [BlockController::class, 'unblock']);
    Route::get('/chat/is-blocked/{userId}', [ChatController::class, 'isBlocked']);
    Route::delete('/chats/{chat}/clear', [ChatController::class, 'clearChat']);
    Route::get('/messages/unread-count', [ChatController::class, 'unreadSendersCount']);


});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users/{user}/online-status', [UserController::class, 'onlineStatus']);
    Route::post('/users/online-status-bulk', [UserController::class, 'onlineStatusBulk']);
});

// routes/api.php
Route::middleware('auth:sanctum')->get('/users/{user}/status', function (\App\Models\User $user) {
    return response()->json([
        'online' => $user->isOnline(),
        'last_seen_at' => $user->last_seen_at,
    ]);
});





Route::middleware('auth:sanctum')->group(function () {

    // Own profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/visibility', [ProfileController::class, 'updateVisibility']);

    // View another user's profile
    Route::get('/profile/{id}', [ProfileController::class, 'show']);
});

Route::middleware('auth:sanctum')->get('/user/videos/count', [VideoController::class, 'userVideoCount']);


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/reports', [ReportController::class, 'store']);
    Route::get('/reports', [ReportController::class, 'index']);
});


Route::middleware('auth:sanctum')->group(function () {
Route::post('/replies/{reply}/react', [ReplyController::class, 'react']);
});

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->delete('/videos/{id}', [VideoController::class, 'destroy']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/comments/{comment}/reaction', [CommentReactionController::class, 'toggle']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/videos/{id}/reaction', [VideoReactionController::class, 'store']);
    Route::delete('/videos/{id}/reaction', [VideoReactionController::class, 'destroy']);
});
Route::get('/videos/{id}/reactions', [VideoReactionController::class, 'index']); // public

Route::get('/admin', [AdminController::class, 'show']);

Route::get('/categories', function () {
    return Category::all();
});

Route::get('/coursetitles', function () {
    return Coursetitle::all();
});


Route::middleware('auth:sanctum')->group(function(){
    Route::get('/videos', [VideoController::class,'index']);
    Route::post('/videos', [VideoController::class,'store']);
    Route::get('/videos/{video}', [VideoController::class,'show']);
    Route::put('/videos/{video}', [VideoController::class,'update']);
    Route::delete('/videos/{video}', [VideoController::class,'destroy']);
   // routes/api.php 

// Library
Route::middleware('auth:sanctum')->get('/library', [VideoController::class, 'savedVideos']);
Route::middleware('auth:sanctum')->delete('/library/{video}', [VideoController::class, 'removeFromLibrary']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/videos/{video}/save-to-library', [VideoController::class, 'saveToLibrary']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/downloaded-videos', [VideoController::class, 'downloadedVideos']);
    Route::get('/download-video/{id}', [VideoController::class, 'download']);

});
// Reports
Route::middleware('auth:sanctum')->get('/admin/reports', [VideoController::class, 'reportedVideos']);


    Route::get('/videos/{video}/comments', [CommentController::class, 'index']);
    Route::post('/videos/{video}/comments', [CommentController::class, 'store']);

    Route::put('/comments/{comment}', [CommentController::class,'update']);
    Route::delete('/comments/{comment}', [CommentController::class,'destroy']);
    

    Route::post('/videos/{video}/share', [ShareController::class,'share']);
});


Route::post('/send-otp', [OtpController::class, 'sendOtp']);
Route::post('/verify-otp', [OtpController::class, 'verifyOtp']);

Route::post('/register', [RegisterController::class, 'register']);
Route::post('/notification-badge', [RegisterController::class, 'myNotifications']);
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

        Route::put('/teacher-form', [TeacherFormController::class, 'update']);

       Route::middleware('auth:sanctum')->post(
                '/admin/teacher/save',
                [TeacherFormController::class, 'store']
            );

    });

    Route::get('/teacher', [TeacherFormController::class, 'allTeachers'])
        ->middleware('auth:sanctum');
        Route::get('/teacher-single', [TeacherFormController::class, 'singleTeachers'])
        ->middleware('auth:sanctum');
    Route::get('/teacher-single/{id}', [TeacherFormController::class, 'myTeacherProfile'])
    ->middleware('auth:sanctum');

    Route::get('/admin/dashboard', function () {
        return response()->json(['message' => 'Admin dashboard']);
    })->name('admin.dashboard');
});
