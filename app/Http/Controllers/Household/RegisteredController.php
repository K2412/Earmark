<?php

namespace App\Http\Controllers\Household;

use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\StoreRegisteredEventRequest;
use App\Models\Account;
use App\Models\RegisteredAccountEvent;
use App\Services\Canadian\CanadianProgrammeService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredController extends Controller
{
    use ResolvesHousehold;

    private const REGISTERED_TYPES = ['tfsa', 'rrsp', 'fhsa', 'resp', 'taxable_investment'];

    /**
     * Rough current-year annual limits (cents). These are estimates only until the
     * versioned assumptions catalogue (#1258) supplies effective-dated values.
     */
    private const ESTIMATED_LIMITS = [
        'tfsa' => 700000,
        'rrsp' => 3210000,
        'fhsa' => 800000,
        'resp' => 0,
        'taxable_investment' => 0,
    ];

    public function __construct(private CanadianProgrammeService $programme) {}

    public function index(Request $request): Response
    {
        $household = $this->household($request);
        $year = (int) now()->year;

        $accounts = $household->accounts()
            ->whereIn('type', self::REGISTERED_TYPES)
            ->where('archived', false)
            ->with('owner:id,name')
            ->orderBy('name')
            ->get()
            ->map(function (Account $account) use ($year): array {
                $limit = self::ESTIMATED_LIMITS[$account->type] ?? 0;
                $room = $this->programme->room($account, $limit, $year);

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'owner' => $account->owner?->name ?? 'Joint',
                    'contributions' => Money::format($this->programme->contributions($account, $year)),
                    'room' => [
                        'amount' => Money::format($room['amount']),
                        'authoritative' => $room['authoritative'],
                        'as_of' => $room['as_of'],
                    ],
                    'hbp' => $account->type === 'rrsp' ? [
                        'withdrawn' => Money::format($this->programme->hbpWithdrawn($account)),
                        'repaid' => Money::format($this->programme->hbpRepaid($account)),
                        'outstanding' => Money::format($this->programme->hbpOutstanding($account)),
                        'required_annual' => Money::format($this->programme->hbpRequiredAnnualRepayment($account)),
                    ] : null,
                    'events' => RegisteredAccountEvent::query()
                        ->where('account_id', $account->id)
                        ->orderByDesc('occurred_on')
                        ->limit(20)
                        ->get()
                        ->map(fn (RegisteredAccountEvent $event): array => [
                            'id' => $event->id,
                            'type' => $event->type,
                            'amount' => Money::format($event->amount),
                            'occurred_on' => $event->occurred_on->toDateString(),
                            'plan_year' => $event->plan_year,
                        ]),
                ];
            });

        return Inertia::render('household/Registered', [
            'accounts' => $accounts,
            'types' => RegisteredAccountEvent::TYPES,
            'members' => $household->members()->get(['users.id', 'users.name'])
                ->map(fn ($member): array => ['id' => $member->id, 'name' => $member->name]),
            'year' => $year,
            'defaults' => ['occurred_on' => now()->toDateString()],
        ]);
    }

    public function store(StoreRegisteredEventRequest $request): RedirectResponse
    {
        $household = $this->household($request);

        RegisteredAccountEvent::query()->create([
            ...$request->validated(),
            'household_id' => $household->id,
            'created_by_user_id' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event recorded.')]);

        return back();
    }
}
