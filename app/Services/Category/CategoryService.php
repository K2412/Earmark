<?php

namespace App\Services\Category;

use App\Models\Category;
use App\Models\Household;
use Illuminate\Support\Collection;

class CategoryService
{
    /**
     * @return Collection<int, Category>
     */
    public function listForHousehold(Household $household): Collection
    {
        return $household->categories()->where('archived', false)->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * @param  array{name: string, type: string}  $data
     */
    public function create(array $data, Household $household): Category
    {
        return Category::query()->create([
            ...$data,
            'household_id' => $household->id,
        ]);
    }
}
