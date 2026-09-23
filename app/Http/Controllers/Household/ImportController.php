<?php

namespace App\Http\Controllers\Household;

use App\Actions\Import\StageStatementImport;
use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\StoreStatementImportRequest;
use App\Services\Import\CsvImportNormalizer;
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

        return to_route('household.import.index');
    }
}
