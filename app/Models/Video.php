<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Video extends Model {

    protected $appends = ['video_url'];

    public function getVideoUrlAttribute()
    {
        return asset('storage/' . $this->video_path);
    }

    protected $fillable = [
        'user_id',
        'category_id',
        'title',         // <-- add this
        'description',
        'video_path',
        'thumbnail',
        'is_public',
        'is_permissible' // also make sure this is included if used
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function comments() { return $this->hasMany(Comment::class)->whereNull('parent_id')->with('replies.user','user'); }
    public function reactions() { return $this->morphMany(Reaction::class, 'reactionable'); }
    public function savedBy() { return $this->belongsToMany(User::class, 'libraries'); }
}

