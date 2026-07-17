<?php

use App\Enums\OrderStatus;
use App\Models\AprioriRule;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('apriori.min_transactions', 2);
});

test('apriori:mine command generates rules from order data', function () {
    $category = Category::factory()->create();
    $product1 = Product::factory()->create(['category_id' => $category->id, 'name' => 'Ayam Geprek', 'stock' => 10]);
    $product2 = Product::factory()->create(['category_id' => $category->id, 'name' => 'Sambal Bawang', 'stock' => 10]);
    $product3 = Product::factory()->create(['category_id' => $category->id, 'name' => 'Nasi Goreng', 'stock' => 10]);
    $product4 = Product::factory()->create(['category_id' => $category->id, 'name' => 'Dimsum Ayam', 'stock' => 10]);

    $createOrder = function (array $productIds) {
        $order = Order::factory()->create(['status' => OrderStatus::Settlement->value]);
        foreach ($productIds as $productId) {
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $productId,
                'quantity' => 1,
            ]);
        }

        return $order;
    };

    // Lift > 1 needs P(A∩B)/P(A) > P(B).
    // 10 baskets [P1,P2] + 2 baskets other products → P1,P2 only appear together.
    // confidence = 1.0, P(consequent) = 10/12 ≈ 0.833, Lift = 1.2
    foreach (range(1, 10) as $i) {
        $createOrder([$product1->id, $product2->id]);
    }
    $createOrder([$product3->id, $product4->id]);
    $createOrder([$product3->id, $product4->id]);

    expect(AprioriRule::count())->toBe(0);

    $this->artisan('apriori:mine', [
        '--force' => true,
        '--minsupport' => '0.01',
        '--minconfidence' => '0.5',
    ])
        ->assertSuccessful();

    $ruleCount = AprioriRule::count();
    expect($ruleCount)->toBeGreaterThan(0);

    $rule = AprioriRule::first();
    expect($rule)->not->toBeNull();
    expect($rule->antecedent)->toBeArray()->not->toBeEmpty();
    expect($rule->consequent)->toBeArray()->not->toBeEmpty();
    expect($rule->support)->toBeNumeric();
    expect($rule->confidence)->toBeNumeric();
    expect($rule->lift)->toBeNumeric();

    // All generated rules must have Lift > 1 (filtered in AprioriService)
    $allRules = AprioriRule::all();
    foreach ($allRules as $r) {
        expect((float) $r->lift)->toBeGreaterThan(1.0);
    }
});

test('apriori:mine command fails when fewer than 50 transactions and no force flag', function () {
    config()->set('apriori.min_transactions', 50);

    $category = Category::factory()->create();
    $product1 = Product::factory()->create(['category_id' => $category->id]);
    $product2 = Product::factory()->create(['category_id' => $category->id]);

    $order = Order::factory()->create(['status' => OrderStatus::Settlement]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product1->id]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product2->id]);

    $this->artisan('apriori:mine')
        ->assertFailed();
});

test('apriori:mine command skips cancelled and expired orders', function () {
    $category = Category::factory()->create();
    $product = Product::factory(5)->create(['category_id' => $category->id]);

    // 10 valid orders: P0+P1 together → these generate the rules
    foreach (range(1, 10) as $i) {
        $validOrder = Order::factory()->create(['status' => OrderStatus::Settlement]);
        OrderItem::factory()->create(['order_id' => $validOrder->id, 'product_id' => $product[0]->id, 'quantity' => 1]);
        OrderItem::factory()->create(['order_id' => $validOrder->id, 'product_id' => $product[1]->id, 'quantity' => 1]);
    }

    // 2 valid orders with other products → reduces P(consequent) → Lift > 1
    foreach (range(1, 2) as $i) {
        $otherOrder = Order::factory()->create(['status' => OrderStatus::Settlement]);
        OrderItem::factory()->create(['order_id' => $otherOrder->id, 'product_id' => $product[2]->id, 'quantity' => 1]);
        OrderItem::factory()->create(['order_id' => $otherOrder->id, 'product_id' => $product[3]->id, 'quantity' => 1]);
    }

    // Cancelled orders with P4 — should NOT be included in analysis
    $cancelledOrder = Order::factory()->create(['status' => OrderStatus::Cancel]);
    OrderItem::factory()->create(['order_id' => $cancelledOrder->id, 'product_id' => $product[1]->id, 'quantity' => 1]);
    OrderItem::factory()->create(['order_id' => $cancelledOrder->id, 'product_id' => $product[4]->id, 'quantity' => 1]);

    // Failed order with P4 — should NOT be included
    $failedOrder = Order::factory()->create(['status' => OrderStatus::Failed]);
    OrderItem::factory()->create(['order_id' => $failedOrder->id, 'product_id' => $product[0]->id, 'quantity' => 1]);
    OrderItem::factory()->create(['order_id' => $failedOrder->id, 'product_id' => $product[4]->id, 'quantity' => 1]);

    $this->artisan('apriori:mine', [
        '--force' => true,
        '--minsupport' => '0.01',
        '--minconfidence' => '0.5',
    ])
        ->assertSuccessful();

    // Rules should exist (10 valid transactions with P0+P1 → Lift > 1)
    expect(AprioriRule::count())->toBeGreaterThan(0);

    // P4 (only in cancelled/failed) should not appear in any rule
    $allRules = AprioriRule::all();
    foreach ($allRules as $rule) {
        $allProducts = array_merge($rule->antecedent ?? [], $rule->consequent ?? []);
        expect($allProducts)->not->toContain($product[4]->name);
    }
});
