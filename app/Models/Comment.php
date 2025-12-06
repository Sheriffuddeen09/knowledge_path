<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model {
    protected $fillable = ['video_id','user_id','parent_id','body'];
    public function user() { return $this->belongsTo(User::class); }
    public function replies() { return $this->hasMany(Comment::class, 'parent_id')->with('replies.user','user'); }
    public function reactions() { return $this->morphMany(Reaction::class, 'reactionable'); }
    public function video() { return $this->belongsTo(Video::class); }
    

}
