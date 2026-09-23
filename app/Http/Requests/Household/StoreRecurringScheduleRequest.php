<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use App\Models\RecurringSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringScheduleRequest extends FormRequest
{
    use AuthorizesHousehold;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer'],
            'frequency' => ['required', Rule::in(RecurringSchedule::FREQUENCIES)],
            'next_due_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(['confirmed', 'paused', 'dismissed'])],
            'detected' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
