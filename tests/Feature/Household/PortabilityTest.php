<?php

use App\Models\Account;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Portability\PortabilityService;

function seededHousehold(User $user): array
{
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id, 'name' => 'Chequing']);
    $category = Category::factory()->create(['household_id' => $household->id]);
    $bucket = Bucket::factory()->create(['household_id' => $household->id]);
    $transaction = Transaction::factory()->create([
        'household_id' => $household->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'bucket_id' => $bucket->id,
        'amount' => -7250,
        'created_by_user_id' => $user->id,
    ]);
    Goal::factory()->create(['household_id' => $household->id, 'created_by_user_id' => $user->id]);

    return [$household, $account, $transaction];
}

test('export produces a versioned manifest with counts and data', function () {
    $user = User::factory()->create();
    seededHousehold($user);

    $bundle = app(PortabilityService::class)->export($user->household());

    expect($bundle['manifest']['schema_version'])->toBe(PortabilityService::SCHEMA_VERSION)
        ->and($bundle['manifest']['counts']['accounts'])->toBe(1)
        ->and($bundle['manifest']['counts']['transactions'])->toBe(1)
        ->and($bundle['data']['transactions'][0]['amount'])->toBe(-7250);
});

test('the export endpoint returns a downloadable JSON backup', function () {
    $user = User::factory()->create();
    seededHousehold($user);

    $response = $this->actingAs($user)->get(route('household.portability.export'));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('earmark-backup.json');
    $response->assertJsonPath('manifest.schema_version', PortabilityService::SCHEMA_VERSION);
});

test('a clean household restores a backup and reproduces counts and balances', function () {
    $source = User::factory()->create();
    [$sourceHousehold] = seededHousehold($source);
    $bundle = app(PortabilityService::class)->export($sourceHousehold);

    $target = User::factory()->create();
    $targetHousehold = $target->household();

    app(PortabilityService::class)->restore($targetHousehold, $bundle, $target);

    expect(Account::query()->where('household_id', $targetHousehold->id)->count())->toBe(1)
        ->and(Goal::query()->where('household_id', $targetHousehold->id)->count())->toBe(1);

    $restored = Transaction::query()->where('household_id', $targetHousehold->id)->sole();
    expect($restored->amount)->toBe(-7250)
        // FK remapped to the target household's own account.
        ->and(Account::query()->whereKey($restored->account_id)->value('household_id'))->toBe($targetHousehold->id);
});

test('deletion wipes all household financial data', function () {
    $user = User::factory()->create();
    seededHousehold($user);

    $this->actingAs($user)->delete(route('household.portability.destroy'))->assertRedirect();

    expect(Account::query()->where('household_id', $user->household()->id)->count())->toBe(0)
        ->and(Transaction::query()->where('household_id', $user->household()->id)->count())->toBe(0);
});

test('a member without delete permission cannot wipe the household', function () {
    $user = User::factory()->create();
    seededHousehold($user);
    $user->householdMemberships()->update(['role' => 'member']);

    $this->actingAs($user)->delete(route('household.portability.destroy'))->assertForbidden();
    expect(Account::query()->where('household_id', $user->household()->id)->count())->toBe(1);
});

test('an advisor cannot restore or delete', function () {
    $user = User::factory()->create();
    seededHousehold($user);
    $user->householdMemberships()->update(['role' => 'advisor']);

    $this->actingAs($user)->post(route('household.portability.restore'), ['bundle' => ['data' => []]])->assertForbidden();
    $this->actingAs($user)->delete(route('household.portability.destroy'))->assertForbidden();
});
