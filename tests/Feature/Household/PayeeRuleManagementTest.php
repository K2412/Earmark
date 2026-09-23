<?php

use App\Models\Account;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\PayeeRule;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payee\PayeeRuleService;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $attributes
 */
function ruleTransaction(User $user, array $attributes = []): Transaction
{
    $household = $user->household();

    return Transaction::factory()->create(array_merge([
        'household_id' => $household->id,
        'account_id' => Account::factory()->create(['household_id' => $household->id])->id,
        'category_id' => null,
        'bucket_id' => null,
        'created_by_user_id' => $user->id,
    ], $attributes));
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function rulePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Groceries',
        'pattern' => 'loblaws',
        'enabled' => true,
        'category_id' => null,
        'bucket_id' => null,
        'rename_to' => null,
        'hide_from_reports' => false,
        'mark_for_review' => false,
        'auto_apply' => true,
    ], $overrides);
}

test('the rules page renders', function () {
    $user = User::factory()->create();
    PayeeRule::factory()->create(['household_id' => $user->household()->id]);

    $this->actingAs($user)
        ->get(route('household.rules.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('household/Rules')
            ->has('rules', 1)
            ->has('categories')
            ->has('buckets')
        );
});

test('a rule can be created, updated, and deleted', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('household.rules.store'), rulePayload(['rename_to' => 'Loblaws']))
        ->assertRedirect(route('household.rules.index'))
        ->assertSessionHasNoErrors();

    $rule = PayeeRule::query()->where('household_id', $user->household()->id)->sole();
    expect($rule->rename_to)->toBe('Loblaws');

    $this->actingAs($user)
        ->patch(route('household.rules.update', $rule), rulePayload(['name' => 'Renamed', 'enabled' => false]))
        ->assertSessionHasNoErrors();
    expect($rule->refresh()->enabled)->toBeFalse();

    $this->actingAs($user)
        ->delete(route('household.rules.destroy', $rule))
        ->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('payee_rules', ['id' => $rule->id]);
});

test('a disabled rule does not apply to future suggestions', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $category = Category::factory()->create(['household_id' => $household->id]);
    PayeeRule::factory()->create([
        'household_id' => $household->id,
        'pattern' => 'loblaws',
        'category_id' => $category->id,
        'enabled' => false,
    ]);

    $match = app(PayeeRuleService::class)->suggest('LOBLAWS #5025', $household);

    expect($match['rule'])->toBeNull();
});

test('rule preview lists which rows would change without saving', function () {
    $user = User::factory()->create();
    ruleTransaction($user, ['payee' => 'LOBLAWS #5025']);
    ruleTransaction($user, ['payee' => 'Shell']);

    $this->actingAs($user)
        ->postJson(route('household.rules.preview'), [
            'pattern' => 'loblaws',
            'rename_to' => 'Loblaws',
        ])
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('rows.0.payee_before', 'LOBLAWS #5025')
        ->assertJsonPath('rows.0.payee_after', 'Loblaws');

    // Preview must not mutate anything.
    $this->assertDatabaseHas('transactions', ['payee' => 'LOBLAWS #5025']);
});

test('applying a rule updates existing matching transactions and records it', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $category = Category::factory()->create(['household_id' => $household->id]);
    $bucket = Bucket::factory()->create(['household_id' => $household->id]);
    $rule = PayeeRule::factory()->create([
        'household_id' => $household->id,
        'pattern' => 'loblaws',
        'rename_to' => 'Loblaws',
        'category_id' => $category->id,
        'bucket_id' => $bucket->id,
        'hide_from_reports' => true,
        'mark_for_review' => true,
    ]);

    $target = ruleTransaction($user, ['payee' => 'LOBLAWS #5025', 'reviewed' => true]);
    $untouched = ruleTransaction($user, ['payee' => 'Shell']);

    $this->actingAs($user)
        ->from(route('household.rules.index'))
        ->post(route('household.rules.apply', $rule))
        ->assertRedirect(route('household.rules.index'));

    $target->refresh();
    expect($target->payee)->toBe('Loblaws')
        ->and($target->category_id)->toBe($category->id)
        ->and($target->bucket_id)->toBe($bucket->id)
        ->and($target->excluded_from_reports)->toBeTrue()
        ->and($target->reviewed)->toBeFalse();

    // Non-matching rows and the transaction count are untouched (no delete/split).
    expect($untouched->refresh()->payee)->toBe('Shell')
        ->and(Transaction::count())->toBe(2);
    $this->assertDatabaseHas('transaction_activities', ['action' => 'rule_applied']);
});

test('rules can be reordered', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $first = PayeeRule::factory()->create(['household_id' => $household->id, 'priority' => 0]);
    $second = PayeeRule::factory()->create(['household_id' => $household->id, 'priority' => 1]);

    $this->actingAs($user)
        ->post(route('household.rules.reorder'), ['ids' => [$second->id, $first->id]])
        ->assertSessionHasNoErrors();

    expect($first->refresh()->priority)->toBe(1)
        ->and($second->refresh()->priority)->toBe(0);
});

test('a user cannot update, delete, or apply another household rule', function () {
    $user = User::factory()->create();
    $other = PayeeRule::factory()->create();

    $this->actingAs($user)->patch(route('household.rules.update', $other), rulePayload())->assertForbidden();
    $this->actingAs($user)->delete(route('household.rules.destroy', $other))->assertNotFound();
    $this->actingAs($user)->post(route('household.rules.apply', $other))->assertNotFound();
});
