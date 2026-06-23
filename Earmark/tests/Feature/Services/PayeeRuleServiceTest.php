<?php

use App\Models\Bucket;
use App\Models\Category;
use App\Models\PayeeRule;
use App\Services\Payee\PayeeRuleService;

test('suggest returns null result when no rules exist', function () {
    $result = (new PayeeRuleService)->suggest('Loblaws #5025');

    expect($result['rule'])->toBeNull()
        ->and($result['category_id'])->toBeNull()
        ->and($result['bucket_id'])->toBeNull();
});

test('suggest matches by case-insensitive substring', function () {
    $category = Category::factory()->create();
    $rule = PayeeRule::factory()->create([
        'pattern' => 'loblaws',
        'category_id' => $category->id,
    ]);

    $result = (new PayeeRuleService)->suggest('LOBLAWS #5025 TORONTO');

    expect($result['rule']->id)->toBe($rule->id)
        ->and($result['category_id'])->toBe($category->id);
});

test('suggest respects priority order — lower priority number wins', function () {
    $category1 = Category::factory()->create();
    $category2 = Category::factory()->create();

    $low = PayeeRule::factory()->create([
        'pattern' => 'shell',
        'category_id' => $category1->id,
        'priority' => 200,
    ]);

    $high = PayeeRule::factory()->create([
        'pattern' => 'shell',
        'category_id' => $category2->id,
        'priority' => 50,
    ]);

    $result = (new PayeeRuleService)->suggest('Shell Gas Station');

    expect($result['rule']->id)->toBe($high->id)
        ->and($result['category_id'])->toBe($category2->id);
});

test('suggest returns first non-matching when pattern not found', function () {
    $category = Category::factory()->create();
    PayeeRule::factory()->create([
        'pattern' => 'esso',
        'category_id' => $category->id,
    ]);

    $result = (new PayeeRuleService)->suggest('Shell Gas Station');

    expect($result['rule'])->toBeNull();
});

test('autoApply skips matches where auto_apply is false', function () {
    $category = Category::factory()->create();
    PayeeRule::factory()->create([
        'pattern' => 'loblaws',
        'category_id' => $category->id,
        'auto_apply' => false,
    ]);

    $service = new PayeeRuleService;

    $suggestResult = $service->suggest('Loblaws');
    $autoResult = $service->autoApply('Loblaws');

    expect($suggestResult['rule'])->not->toBeNull()
        ->and($autoResult['rule'])->toBeNull();
});

test('suggest returns null result for empty payee', function () {
    Category::factory()->create();
    PayeeRule::factory()->create(['pattern' => 'anything']);

    $result = (new PayeeRuleService)->suggest('');

    expect($result['rule'])->toBeNull();
});

test('rule with bucket assigns both category and bucket', function () {
    $category = Category::factory()->create();
    $bucket = Bucket::factory()->create();

    PayeeRule::factory()->create([
        'pattern' => 'rogers',
        'category_id' => $category->id,
        'bucket_id' => $bucket->id,
    ]);

    $result = (new PayeeRuleService)->suggest('Rogers Wireless');

    expect($result['category_id'])->toBe($category->id)
        ->and($result['bucket_id'])->toBe($bucket->id);
});
