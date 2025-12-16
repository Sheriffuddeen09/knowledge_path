<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\VideoReaction;
use App\Models\VideoDownload;
use App\Models\VideoView;
use Carbon\Carbon;


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
        'is_permissible', // also make sure this is included if used
        'time_ago'
    ];


    public function getTimeAgoAttribute()
        {
            return $this->created_at
                ? $this->created_at->diffForHumans()
                : null;
        }

    public function user() { return $this->belongsTo(User::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function comments() { return $this->hasMany(Comment::class)->whereNull('parent_id')->with('replies.user','user'); }
    public function savedBy() { return $this->belongsToMany(User::class, 'libraries'); }
    public function reactions()
        {
            return $this->hasMany(VideoReaction::class);
        }


    public function savedByUsers()
        {
            return $this->belongsToMany(User::class, 'library');
        }
    public function downloads()
        {
            return $this->hasMany(VideoDownload::class);
        }
    public function views()
        {
            return $this->hasMany(VideoView::class);
        }
        


}

