<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use App\Models\RecurringSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecurringScheduleRequest extends FormRequest
{
    use AuthorizesHousehold;

    public function authorize(): bool
    {
        $schedule = $this->route('recurringSchedule');

        return $schedule instanceof RecurringSchedule
            && ($this->user()?->can('view', $this->household()) ?? false)
            && $schedule->household_id === $this->household()->id;
    }

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
            'status' => ['required', Rule::in(['confirmed', 'paused', 'dismissed'])],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
