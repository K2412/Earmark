<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Services\Account\AccountService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        return inertia('household/accounts/Index');
    }

    public function store(StoreAccountRequest $request, AccountService $service): RedirectResponse
    {
        $service->create($request->validated());

        return to_route('household.accounts.index');
    }
}
