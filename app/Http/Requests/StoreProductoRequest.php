<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'integer', 'unique:productos,sku'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'cantidad' => ['required', 'numeric', 'min:1', 'max:999999'],
            'precio' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'nombre' => trim($this->nombre),
            'descripcion' => $this->descripcion ? trim($this->descripcion) : null,
        ]);
    }
}
