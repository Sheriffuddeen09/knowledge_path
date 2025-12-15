<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileVisibility extends Model
{
    protected $fillable = [
        'user_id',
        'profile_visible',
        'show_email',
        'show_dob',
        'show_first_name',
        'show_last_name',
        'show_phone',
        'show_location',
        'show_gender',
        'show_role',
        'show_password',
    ];
}
