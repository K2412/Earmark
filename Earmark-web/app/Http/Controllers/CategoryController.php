<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Services\Category\CategoryService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        return inertia('household/categories/Index');
    }

    public function store(StoreCategoryRequest $request, CategoryService $service): RedirectResponse
    {
        $service->create($request->validated());

        return to_route('household.categories.index');
    }
}
