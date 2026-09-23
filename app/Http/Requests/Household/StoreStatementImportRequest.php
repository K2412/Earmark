<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use App\Services\Import\CsvImportNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStatementImportRequest extends FormRequest
{
    use AuthorizesHousehold;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $householdId = $this->household()->id;

        return [
            'account_id' => ['required', 'string', Rule::exists('accounts', 'id')->where('household_id', $householdId)],
            'source' => ['required', Rule::in(['csv'])],
            'file' => ['required', 'array'],
            'file.name' => ['required', 'string', 'max:255'],
            'file.size' => ['required', 'integer', 'min:1'],
            'file.sha256' => [
                'required', 'string', 'size:64', 'regex:/^[0-9a-f]{64}$/',
                Rule::unique('statement_uploads', 'file_sha256')->where('household_id', $householdId),
            ],
            'mapping' => ['required', 'array'],
            'mapping.date_format' => ['required', Rule::in(array_keys(CsvImportNormalizer::DATE_FORMATS))],
            'mapping.amount_mode' => ['required', Rule::in(['single', 'debit_credit'])],
            'mapping.sign' => ['nullable', 'required_if:mapping.amount_mode,single', Rule::in(['negative_is_outflow', 'positive_is_outflow'])],
            'rows' => ['required', 'array', 'min:1', 'max:5000'],
            'rows.*.date' => ['nullable', 'string', 'max:64'],
            'rows.*.payee' => ['nullable', 'string', 'max:255'],
            'rows.*.amount' => ['nullable', 'string', 'max:64'],
            'rows.*.debit' => ['nullable', 'string', 'max:64'],
            'rows.*.credit' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.sha256.unique' => 'This file was already imported. Re-importing it would create duplicate transactions.',
        ];
    }
}
