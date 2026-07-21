<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Job extends Model
{
 protected $fillable = [
 'user_id',
 'job_category_id',
 'title',
 'job_type',
 'location',
 'payment',
 'objective',
 'description',
 'active'
 ];
 public function creator()
 {
 return $this->belongsTo(User::class,'user_id');
 }
 public function category()
 {
 return $this->belongsTo(JobCategory::class,'job_category_id');
 }
}
