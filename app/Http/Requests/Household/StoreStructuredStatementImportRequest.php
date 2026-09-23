<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an OFX/QFX/QBO/QIF upload (#1269). Unlike CSV, these self-describing
 * formats are parsed server-side from the raw file content, so the request carries
 * the content itself. The file fingerprint is derived from that content and checked
 * for a prior import so re-uploading the same file is rejected with a clear message.
 */
class StoreStructuredStatementImportRequest extends FormRequest
{
    use AuthorizesHousehold;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'file_sha256' => hash('sha256', (string) $this->input('content', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $householdId = $this->household()->id;

        return [
            'account_id' => ['required', 'string', Rule::exists('accounts', 'id')->where('household_id', $householdId)],
            'source' => ['required', Rule::in(['ofx', 'qfx', 'qbo', 'qif'])],
            'filename' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000000'],
            'file_sha256' => [
                Rule::unique('statement_uploads', 'file_sha256')->where('household_id', $householdId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file_sha256.unique' => 'This file was already imported. Re-importing it would create duplicate transactions.',
        ];
    }
}
