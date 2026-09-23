<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;

class RestoreBackupRequest extends FormRequest
{
    use AuthorizesHousehold;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bundle' => ['required', 'array'],
            'bundle.data' => ['required', 'array'],
            'bundle.manifest' => ['nullable', 'array'],
        ];
    }
}
