<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [

            'cv' => [
                'nullable',
                'file',
                'mimes:pdf,doc,docx',
                'max:5120',
            ],

            'additional_text' => [
                'nullable',
                'string',
                'max:5000',
            ],

            /*
             * These are NOT required from the applicant.
             * They come from the JobPost.
             */
            'qualification' => [
                'nullable',
                'string',
                'max:500',
            ],

            'experience' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'year_experience' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'payment' => [
                'nullable',
                'numeric',
                'min:0',
            ],

        ];
    }
}