<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFlashcardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categoryId' => ['sometimes', 'integer'],
            'question' => ['sometimes', 'string', 'max:2000'],
            'type' => ['sometimes', 'string', 'in:summary,multiple-choice,open-ended,audio'],
            'content' => ['nullable', 'string'],
            'answer' => ['nullable', 'string'],
            'options' => ['nullable', 'array'],
            'options.*.text' => ['required_with:options', 'string'],
            'options.*.isCorrect' => ['required_with:options', 'boolean'],
            'translation' => ['nullable', 'string'],
            'audioUrl' => ['nullable', 'string'],
        ];
    }
}
