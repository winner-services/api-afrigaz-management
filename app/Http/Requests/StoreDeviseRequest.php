<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeviseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'designation'        => 'required|string|max:100|unique:devises,designation',
            'currency_type'      => 'required|in:devise_principale,devise_secondaire',
            'conversion_amount'  => 'required|numeric|min:0',
            'symbol'             => 'required|string|max:10'
        ];
    }
}
