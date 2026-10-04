<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id'    => ['required', 'integer', Rule::exists('categories', 'id')],
            'brand_id'       => ['required', 'integer', Rule::exists('brands', 'id')],
            'name'           => ['required', 'string', 'max:255'],
            'sku'            => ['required', 'string', 'max:255', Rule::unique('products', 'sku')],
            'description'    => ['nullable', 'string'],
            'price'          => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'is_active'      => ['required', 'boolean'],
        ];
    }
}