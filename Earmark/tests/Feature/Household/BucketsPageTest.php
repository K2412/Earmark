<?php

use App\Models\Bucket;
use App\Models\User;
use Database\Seeders\BucketSeeder;
use Livewire\Livewire;

test('buckets index renders', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('household.buckets.index'))
        ->assertOk()
        ->assertSeeText('Buckets');
});

test('buckets index lists existing buckets and shows system badge for protected buckets', function () {
    $user = User::factory()->create();
    (new BucketSeeder)->run();
    Bucket::factory()->create(['name' => 'Rent', 'kind' => 'ongoing']);

    $this->actingAs($user)
        ->get(route('household.buckets.index'))
        ->assertSeeText('Unassigned Funds')
        ->assertSeeText('System')
        ->assertSeeText('Rent');
});

test('create bucket via Livewire action persists and creates an obligation version', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::household.buckets.index')
        ->set('form.name', 'Groceries')
        ->set('form.kind', 'ongoing')
        ->set('form.monthly_obligation', 80000)
        ->set('form.target_date', '2027-01-01')
        ->call('save')
        ->assertHasNoErrors();

    $bucket = Bucket::firstWhere('name', 'Groceries');
    expect($bucket)->not->toBeNull()
        ->and($bucket->monthly_obligation)->toBe(80000)
        ->and($bucket->obligationVersions)->toHaveCount(1)
        ->and($bucket->obligationVersions->first()->created_by_user_id)->toBe($user->id);
});

test('destroying a non-system bucket succeeds', function () {
    $user = User::factory()->create();
    $bucket = Bucket::factory()->create(['kind' => 'ongoing']);

    Livewire::actingAs($user)
        ->test('pages::household.buckets.index')
        ->call('destroyBucket', $bucket->id)
        ->assertHasNoErrors();

    expect(Bucket::find($bucket->id))->toBeNull();
});

test('destroying a system bucket (Unassigned Funds) is rejected without throwing', function () {
    $user = User::factory()->create();
    (new BucketSeeder)->run();
    $unassigned = Bucket::firstWhere('name', Bucket::UNASSIGNED_FUNDS);

    Livewire::actingAs($user)
        ->test('pages::household.buckets.index')
        ->call('destroyBucket', $unassigned->id)
        ->assertHasErrors(['protected']);

    expect(Bucket::find($unassigned->id))->not->toBeNull();
});

test('guests are redirected from the buckets page', function () {
    $this->get(route('household.buckets.index'))
        ->assertRedirect(route('login'));
});
