<?php

namespace App\Http\Requests;

use App\Models\Place;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var Place|null $place */
        $place = $this->route('place');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('places', 'name')
                    ->where('region_id', $this->input('region_id'))
                    ->ignore($place),
            ],
            'region_id' => ['required', 'integer', Rule::exists('regions', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('validation.custom.place_duplicate'),
        ];
    }
}
