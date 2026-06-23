<?php

namespace App\Actions\Categories;

use App\Models\Category;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateCategory
{
    use AsAction;

    /**
     * @param  array{name: string, type: string}  $data
     */
    public function handle(array $data): Category
    {
        return Category::query()->create($data);
    }
}
