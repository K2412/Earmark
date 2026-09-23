<?php

namespace App\Http\Controllers\Household;

use App\Concerns\ResolvesHousehold;
use App\Enums\HouseholdPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\RestoreBackupRequest;
use App\Services\Portability\PortabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortabilityController extends Controller
{
    use ResolvesHousehold;

    public function __construct(private PortabilityService $portability) {}

    public function index(Request $request): Response
    {
        $household = $this->household($request);
        $bundle = $this->portability->export($household);

        return Inertia::render('household/Portability', [
            'counts' => $bundle['manifest']['counts'],
            'schemaVersion' => PortabilityService::SCHEMA_VERSION,
            'canDelete' => $request->user()->hasHouseholdPermission($household, HouseholdPermission::DeleteHousehold),
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        $household = $this->household($request);
        $bundle = $this->portability->export($household, now()->toIso8601String());

        return response()->json($bundle, 200, [
            'Content-Disposition' => 'attachment; filename="earmark-backup.json"',
        ], JSON_PRETTY_PRINT);
    }

    public function restore(RestoreBackupRequest $request): RedirectResponse
    {
        $household = $this->household($request);

        $counts = $this->portability->restore($household, $request->validated('bundle'), $request->user());
        $total = array_sum($counts);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Restored :count record(s).', ['count' => $total])]);

        return to_route('household.portability.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $household = $this->household($request);

        abort_unless(
            $request->user()->hasHouseholdPermission($household, HouseholdPermission::DeleteHousehold),
            403,
        );

        $this->portability->wipe($household);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('All household financial data deleted.')]);

        return to_route('household.portability.index');
    }
}
