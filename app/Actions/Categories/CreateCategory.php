<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\Household;
use App\Services\Category\CategoryService;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateCategory
{
    use AsAction;

    public function __construct(private CategoryService $categories) {}

    /**
     * @param  array{name: string, type: string}  $data
     */
    public function handle(array $data, Household $household): Category
    {
        return $this->categories->create($data, $household);
    }
}
