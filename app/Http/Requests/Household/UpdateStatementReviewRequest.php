<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use App\Models\StatementUpload;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatementReviewRequest extends FormRequest
{
    use AuthorizesHousehold;

    public function authorize(): bool
    {
        $upload = $this->route('statementUpload');

        return $upload instanceof StatementUpload
            && ($this->user()?->can('view', $this->household()) ?? false)
            && $upload->household_id === $this->household()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $householdId = $this->household()->id;
        $upload = $this->route('statementUpload');
        $uploadId = $upload instanceof StatementUpload ? $upload->id : null;

        $inHousehold = fn (string $table) => Rule::exists($table, 'id')->where('household_id', $householdId);

        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.id' => ['required', 'string', Rule::exists('staged_transactions', 'id')->where('statement_upload_id', $uploadId)],
            'rows.*.date' => ['required', 'date_format:Y-m-d'],
            'rows.*.payee' => ['required', 'string', 'max:255'],
            'rows.*.amount' => ['required', 'integer'],
            'rows.*.final_category_id' => ['nullable', 'string', $inHousehold('categories')],
            'rows.*.final_bucket_id' => ['nullable', 'string', $inHousehold('buckets')],
            'rows.*.accept' => ['required', 'boolean'],
            'rows.*.status' => ['required', Rule::in(['pending', 'rejected'])],
            'rows.*.splits' => ['nullable', 'array'],
            'rows.*.splits.*.category_id' => ['nullable', 'string', $inHousehold('categories')],
            'rows.*.splits.*.bucket_id' => ['required', 'string', $inHousehold('buckets')],
            'rows.*.splits.*.amount' => ['required', 'integer'],
            'rows.*.splits.*.memo' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('rows', []) as $index => $row) {
                $splits = $row['splits'] ?? [];

                if ($splits === []) {
                    continue;
                }

                $total = array_sum(array_map(static fn ($split): int => (int) ($split['amount'] ?? 0), $splits));

                if ($total !== (int) ($row['amount'] ?? 0)) {
                    $validator->errors()->add(
                        "rows.{$index}.splits",
                        'Splits must sum exactly to the transaction amount.',
                    );
                }
            }
        });
    }
}
