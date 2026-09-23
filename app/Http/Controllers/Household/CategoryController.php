<?php

namespace App\Http\Controllers\Household;

use App\Actions\Categories\CreateCategory;
use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\StoreCategoryRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CategoryController extends Controller
{
    use ResolvesHousehold;

    /**
     * Create a category and return to the caller's page. Unlike the Plan page's
     * category action (which redirects into the plan), this lets any screen — e.g.
     * the import review — create a category inline without navigating away.
     */
    public function store(StoreCategoryRequest $request, CreateCategory $action): RedirectResponse
    {
        $action->handle($request->validated(), $this->household($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return back();
    }
}
