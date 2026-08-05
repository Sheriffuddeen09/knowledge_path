<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Advertisement extends Model
{
use HasFactory;
protected $fillable = [ "user_id", "type", "title", "description",
 "link", "media", "media_type", "audience", "required_badges", 
 "status", "visibility_unlock", "approved_at"
];
protected $casts = [
"approved_at"=>"datetime"
];
public function user()
{
return $this->belongsTo(User::class);
}
}
