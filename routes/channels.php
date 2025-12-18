<?php 

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    return \App\Models\Chat::where('id', $chatId)
        ->where(function ($q) use ($user) {
            $q->where('teacher_id', $user->id)
              ->orWhere('student_id', $user->id);
        })->exists();
});

