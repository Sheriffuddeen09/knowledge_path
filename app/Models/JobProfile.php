<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobProfile extends Model
{
 protected $fillable = [
 'user_id',
 'type',
 'company_name',
 'company_logo',
 'company_type',
 'organisation_size',
 'company_location',
 'company_address',
 'location',
 'address',
 'full_name',
 'cv',
 'qualifications',
 'portfolio',
 'skills',
 'certification',
 'status',
 'decline_reason'
];

protected $casts = [
    'skills' => 'array',
];


 public function user()
 {
 return $this->belongsTo(User::class);
 }
}