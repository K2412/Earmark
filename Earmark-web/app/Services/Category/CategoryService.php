<?php

namespace App\Services\Category;

use App\Models\Category;

class CategoryService
{
    /**
     * @param  array{name: string, type: string}  $data
     */
    public function create(array $data): Category
    {
        return Category::query()->create($data);
    }
}
