<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /**
     * Determine if the user can update the comment.
     */
    public function update(User $user, Comment $comment)
    {
        // Admins can edit any comment, users can edit their own
        return $user->is_admin || $user->id === $comment->user_id;
    }

    /**
     * Determine if the user can delete the comment.
     */
    public function delete(User $user, Comment $comment)
    {
        // Admins can delete any comment, users can delete their own
        return $user->is_admin || $user->id === $comment->user_id;
    }
}
