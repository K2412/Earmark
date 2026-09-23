<?php

namespace App\Http\Requests\Household;

use App\Http\Controllers\Household\DashboardController;
use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOverviewCardsRequest extends FormRequest
{
    use AuthorizesHousehold;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cards' => ['present', 'array'],
            'cards.*' => [Rule::in(DashboardController::CARDS)],
        ];
    }
}
