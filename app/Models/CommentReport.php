<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommentReport extends Model
{
    protected $fillable = [
        'comment_id',
        'reporter_id',
        'reported_user_id',
        'reason',
        'details',
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function comment()
    {
        return $this->belongsTo(PostComment::class);
    }
}
