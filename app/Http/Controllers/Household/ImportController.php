<?php

namespace App\Http\Controllers\Household;

use App\Actions\Import\PromoteStatementImport;
use App\Actions\Import\StageStatementImport;
use App\Actions\Import\UpdateStatementReview;
use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\StoreStatementImportRequest;
use App\Http\Requests\Household\UpdateStatementReviewRequest;
use App\Models\StatementUpload;
use App\Services\Import\CsvImportNormalizer;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    use ResolvesHousehold;

    public function __construct(private CsvImportNormalizer $normalizer) {}

    public function index(Request $request): Response
    {
        $household = $this->household($request);

        return Inertia::render('household/Import', [
            'accounts' => $household->accounts()->where('archived', false)->orderBy('name')->get(['id', 'name']),
            'dateFormats' => collect(array_keys(CsvImportNormalizer::DATE_FORMATS))
                ->map(fn (string $label): array => ['value' => $label, 'label' => $label])
                ->values(),
        ]);
    }

    public function store(StoreStatementImportRequest $request, StageStatementImport $action): RedirectResponse
    {
        $household = $this->household($request);
        $validated = $request->validated();

        $result = $this->normalizer->normalize($validated['rows'], $validated['mapping']);

        $upload = $action->handle([
            'account_id' => $validated['account_id'],
            'source' => $validated['source'],
            'original_filename' => $validated['file']['name'],
            'file_sha256' => $validated['file']['sha256'],
            'file_size_bytes' => $validated['file']['size'],
            'parser_version' => CsvImportNormalizer::PARSER_VERSION,
        ], $result->drafts, $request->user(), $household);

        $duplicates = $upload->stagedTransactions()->where('is_possible_duplicate', true)->count();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':file — :staged staged, :duplicates possible duplicate(s), :skipped skipped.', [
                'file' => $upload->original_filename,
                'staged' => $upload->parsed_transaction_count,
                'duplicates' => $duplicates,
                'skipped' => count($result->skipped),
            ]),
        ]);

        return to_route('household.import.review', $upload);
    }

    public function review(Request $request, StatementUpload $statementUpload): Response
    {
        $household = $this->household($request);
        abort_unless($statementUpload->household_id === $household->id, 404);

        $rows = $statementUpload->stagedTransactions()
            ->with('splits')
            ->orderBy('date')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($row): array => [
                'id' => $row->id,
                'date' => $row->date->toDateString(),
                'payee' => $row->payee,
                'raw_payee' => $row->raw_payee,
                'amount' => $row->amount,
                'amount_formatted' => Money::format($row->amount),
                'status' => $row->status,
                'accept' => $row->accept,
                'is_possible_duplicate' => $row->is_possible_duplicate,
                'duplicate_reason' => $row->duplicate_reason,
                'is_split' => $row->is_split,
                'final_category_id' => $row->final_category_id,
                'final_bucket_id' => $row->final_bucket_id,
                'splits' => $row->splits->map(fn ($split): array => [
                    'category_id' => $split->category_id,
                    'bucket_id' => $split->bucket_id,
                    'amount' => $split->amount,
                    'memo' => $split->memo,
                ])->values(),
            ]);

        return Inertia::render('household/ImportReview', [
            'upload' => [
                'id' => $statementUpload->id,
                'filename' => $statementUpload->original_filename,
                'status' => $statementUpload->status,
                'parsed_count' => $statementUpload->parsed_transaction_count,
                'imported_count' => $statementUpload->imported_transaction_count,
            ],
            'rows' => $rows,
            'categories' => $household->categories()->where('archived', false)->orderBy('name')->get(['id', 'name']),
            'buckets' => $household->buckets()->where('archived', false)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateStaged(UpdateStatementReviewRequest $request, StatementUpload $statementUpload, UpdateStatementReview $action): RedirectResponse
    {
        $action->handle($statementUpload, $request->validated('rows'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Corrections saved.')]);

        return to_route('household.import.review', $statementUpload);
    }

    public function promote(Request $request, StatementUpload $statementUpload, PromoteStatementImport $action): RedirectResponse
    {
        $household = $this->household($request);
        abort_unless($statementUpload->household_id === $household->id, 404);

        $counts = $action->handle($statementUpload, $request->user(), $household);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':promoted promoted, :rejected rejected, :failed failed, :pending still pending.', $counts),
        ]);

        return to_route('household.import.review', $statementUpload);
    }
}
