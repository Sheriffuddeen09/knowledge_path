<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class MaxWords implements Rule
{
    protected $max;

    public function __construct($max)
    {
        $this->max = $max;
    }

    public function passes($attribute, $value)
    {
        $wordCount = str_word_count($value);
        return $wordCount <= $this->max;
    }

    public function message()
    {
        return "The :attribute field must not be greater than {$this->max} words.";
    }
}
