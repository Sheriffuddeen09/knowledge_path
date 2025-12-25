<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coursetitle extends Model
{
    protected $fillable = ['name','slug'];
    public function teacherForms()
{
    return $this->hasMany(TeacherForm::class);
}

}
