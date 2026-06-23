<?php

namespace App\Rules;

use App\Models\Household;
use App\Models\HouseholdInvitation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueHouseholdInvitation implements ValidationRule
{
    public function __construct(protected Household $household)
    {
        //
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = strtolower($value);

        $isMember = $this->household->members()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();

        if ($isMember) {
            $fail(__('This user is already a member of the household.'));

            return;
        }

        $hasPendingInvitation = HouseholdInvitation::where('household_id', $this->household->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($hasPendingInvitation) {
            $fail(__('An invitation has already been sent to this email address.'));
        }
    }
}
