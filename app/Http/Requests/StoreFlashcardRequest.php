<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFlashcardRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership da categoria é validado no FlashcardService (precisa
        // do usuário autenticado, que o FormRequest não resolve sozinho
        // de forma limpa aqui sem acoplar a uma query).
        return true;
    }

    public function rules(): array
    {
        return [
            'categoryId' => ['required', 'integer'],
            'question' => ['required', 'string', 'max:2000'],
            'type' => ['required', 'string', 'in:summary,multiple-choice,open-ended,audio'],
            'content' => ['nullable', 'string', 'required_if:type,summary'],
            'answer' => ['nullable', 'string', 'required_if:type,open-ended'],
            'options' => ['nullable', 'array', 'required_if:type,multiple-choice'],
            'options.*.text' => ['required_with:options', 'string'],
            'options.*.isCorrect' => ['required_with:options', 'boolean'],
            'translation' => ['nullable', 'string'],
            'audioUrl' => ['nullable', 'string', 'required_if:type,audio'],
        ];
    }
}
