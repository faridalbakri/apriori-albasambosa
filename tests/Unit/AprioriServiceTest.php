<?php

use App\Services\AprioriService;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the native PHP Apriori algorithm.
 *
 * Uses a small, hand-calculated dataset to verify correctness.
 */
beforeEach(function () {
    config()->set('apriori.min_transactions', 2);
});

/**
 * Small test dataset (5 transactions, 5 products):
 *
 * T1: [1, 2, 3]
 * T2: [1, 2]
 * T3: [1, 3]
 * T4: [2, 3]
 * T5: [4, 5]
 *
 * min_support = 0.4 (2/5 transactions)
 * Expected frequent itemsets (size 1): [1](3), [2](3), [3](3), [4](1=NO), [5](1=NO)
 * Expected frequent itemsets (size 2): [1,2](2), [1,3](2), [2,3](2)
 */
test('findFrequentItemsets returns correct itemsets for small dataset', function () {
    $baskets = [
        [1, 2, 3],
        [1, 2],
        [1, 3],
        [2, 3],
        [4, 5],
    ];

    $service = new AprioriService;
    $result = $service->findFrequentItemsets($baskets, 0.4, 5);

    // Extract itemsets as sorted key -> support pairs
    $itemsets = [];
    foreach ($result as $fi) {
        sort($fi['items'], SORT_NUMERIC);
        $itemsets[implode(',', $fi['items'])] = round($fi['support'], 2);
    }

    // Size-1 itemsets (support >= 0.4, i.e. >= 2 transactions)
    expect($itemsets)->toHaveKey('1');
    expect($itemsets['1'])->toBe(0.6); // 3/5
    expect($itemsets)->toHaveKey('2');
    expect($itemsets['2'])->toBe(0.6); // 3/5
    expect($itemsets)->toHaveKey('3');
    expect($itemsets['3'])->toBe(0.6); // 3/5
    expect($itemsets)->not->toHaveKey('4'); // 1/5 = 0.2 < 0.4
    expect($itemsets)->not->toHaveKey('5');

    // Size-2 itemsets
    expect($itemsets)->toHaveKey('1,2');
    expect($itemsets['1,2'])->toBe(0.4); // 2/5
    expect($itemsets)->toHaveKey('1,3');
    expect($itemsets['1,3'])->toBe(0.4);
    expect($itemsets)->toHaveKey('2,3');
    expect($itemsets['2,3'])->toBe(0.4);
});

test('mine returns correct rules with confidence and lift', function () {
    $baskets = [
        [1, 2, 3],
        [1, 2],
        [1, 3],
        [2, 3],
        [4, 5],
    ];

    $service = new AprioriService;
    $rules = $service->mine($baskets, 0.4, 0.6);

    expect($rules)->toBeArray();
    expect($rules)->not->toBeEmpty();

    // Rules should have required keys
    foreach ($rules as $rule) {
        expect($rule)->toHaveKeys(['antecedent', 'consequent', 'support', 'confidence', 'lift']);
        expect($rule['antecedent'])->toBeArray()->not->toBeEmpty();
        expect($rule['consequent'])->toBeArray()->not->toBeEmpty();
        expect($rule['confidence'])->toBeGreaterThanOrEqual(0.6);
        expect($rule['support'])->toBeGreaterThanOrEqual(0.4);
        expect($rule['lift'])->toBeGreaterThan(1.0); // Lift <= 1 filtered in generateRules()
    }

    // Verify a specific rule: [1] -> [2]
    // support({1,2}) = 2/5 = 0.4
    // support({1}) = 3/5 = 0.6
    // confidence = 0.4 / 0.6 = 0.667
    // support({2}) = 3/5 = 0.6
    // lift = 0.667 / 0.6 = 1.111
    $rule12 = collect($rules)->first(
        fn ($r) => $r['antecedent'] === [1] && $r['consequent'] === [2]
    );
    expect($rule12)->not->toBeNull();
    expect(round($rule12['confidence'], 4))->toBe(0.6667);
    expect(round($rule12['lift'], 4))->toBe(1.1111);
});

test('mine throws exception when fewer than min_transactions', function () {
    config()->set('apriori.min_transactions', 50);

    $baskets = [
        [1, 2],
        [1, 3],
    ];

    $service = new AprioriService;
    $service->mine($baskets);
})->throws(InvalidArgumentException::class);

test('mine returns empty array when no rules meet thresholds', function () {
    $baskets = [
        [1, 2],
        [1, 3],
        [1, 4],
        [2, 3],
        [2, 4],
        [3, 4],
    ];

    $service = new AprioriService;
    $rules = $service->mine($baskets, 0.1, 0.95);

    expect($rules)->toBeArray();
    expect(count($rules))->toBeLessThanOrEqual(1);
});
