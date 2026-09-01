<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome_categoria' => ['required', 'string', 'max:255'],
            'icon' => ['required', 'string', 'max:50'],
            'color' => ['required', 'string', 'max:50'],
        ];
    }
}
