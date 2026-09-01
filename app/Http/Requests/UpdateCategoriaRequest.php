<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome_categoria' => ['sometimes', 'string', 'max:255'],
            'icon' => ['sometimes', 'string', 'max:50'],
            'color' => ['sometimes', 'string', 'max:50'],
        ];
    }
}
