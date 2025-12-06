<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Video;

class VideoPolicy
{
    /**
     * Determine whether the user can create a video.
     */
    public function create(User $user)
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can update the video.
     */
    public function update(User $user, Video $video)
    {
        return $user->id === $video->user_id || $user->role === 'admin';
    }

    /**
     * Determine whether the user can delete the video.
     */
    public function delete(User $user, Video $video)
    {
        return $user->id === $video->user_id || $user->role === 'admin';
    }
}
