<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBucketRequest;
use App\Models\Bucket;
use App\Services\Bucket\BucketService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class BucketController extends Controller
{
    public function index(): Response
    {
        return inertia('household/buckets/Index');
    }

    public function store(StoreBucketRequest $request, BucketService $service): RedirectResponse
    {
        $service->create($request->validated(), $request->user()->id);

        return to_route('household.buckets.index');
    }

    public function destroy(Bucket $bucket, BucketService $service): RedirectResponse
    {
        abort_unless($service->archive($bucket), 403);

        return to_route('household.buckets.index');
    }
}
