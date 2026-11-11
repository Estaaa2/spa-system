<?php

namespace Tests\Feature\Validation;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\Treatment;
use App\Models\User;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Modal validation phase: server-side upper bounds (config/limits.php) that
 * stop oversized numbers from reaching MySQL as "out of range" 500 errors,
 * the treatment price range moved from browser-only to the server, supplier
 * phone (mobile or landline), and clearing a supplier product term.
 */
class ModalValidationLimitsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->owner = User::where('email', 'owner@levictas.test')->firstOrFail();
        $this->actingAs($this->owner);
    }

    public function test_expense_amount_is_capped(): void
    {
        $this->post(route('billing.expense.store'), ['title' => 'Towels', 'amount' => '99999999999'])
            ->assertSessionHasErrors('amount');

        $this->post(route('billing.expense.store'), ['title' => 'Towels', 'amount' => '1500.50'])
            ->assertSessionHasNoErrors();
    }

    public function test_treatment_price_range_is_enforced_on_the_server(): void
    {
        $base = ['name' => 'Range Test', 'duration' => 60, 'service_type' => 'in_branch_only'];
        $limits = config('limits.treatment_price');

        $this->post(route('treatments.store'), $base + ['price' => $limits['min'] - 1])
            ->assertSessionHasErrors('price');
        $this->post(route('treatments.store'), $base + ['price' => $limits['max'] + 1])
            ->assertSessionHasErrors('price');

        // The old browser-only range was 300-1,400; prices outside it are now allowed.
        $this->post(route('treatments.store'), $base + ['price' => 200])
            ->assertSessionHasNoErrors();
        $this->post(route('treatments.store'), ['name' => 'Range Test 2'] + $base + ['price' => 5200])
            ->assertSessionHasNoErrors();
    }

    public function test_promo_discount_is_capped_by_type_on_store_and_update(): void
    {
        $treatment = Treatment::withoutGlobalScopes()->where('spa_id', $this->owner->spa_id)->firstOrFail();
        $base = [
            'name' => 'Promo',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'treatment_ids' => [$treatment->id],
        ];

        $this->post(route('promos.store'), $base + ['discount_type' => 'percent', 'discount_value' => 101])
            ->assertSessionHasErrors('discount_value');
        $this->post(route('promos.store'), $base + ['discount_type' => 'fixed', 'discount_value' => config('limits.promo_fixed_discount') + 1])
            ->assertSessionHasErrors('discount_value');
        $this->post(route('promos.store'), $base + ['discount_type' => 'fixed', 'discount_value' => 0])
            ->assertSessionHasErrors('discount_value');
        $this->post(route('promos.store'), $base + ['discount_type' => 'percent', 'discount_value' => 20])
            ->assertSessionHasNoErrors();

        // Update never checked the 100% ceiling before.
        $promo = \App\Models\Promo::withoutGlobalScopes()->where('name', 'Promo')->latest('id')->firstOrFail();
        $this->put(route('promos.update', $promo), $base + ['discount_type' => 'percent', 'discount_value' => 150])
            ->assertSessionHasErrors('discount_value');
    }

    public function test_fixed_promo_must_be_lower_than_every_selected_item(): void
    {
        $treatment = Treatment::withoutGlobalScopes()->where('spa_id', $this->owner->spa_id)->firstOrFail();
        $price = (float) $treatment->price;
        $base = [
            'name' => 'Fixed Promo', 'discount_type' => 'fixed',
            'start_date' => now()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
            'treatment_ids' => [$treatment->id],
        ];

        // Equal to the price would make the treatment free.
        $this->post(route('promos.store'), $base + ['discount_value' => $price])
            ->assertSessionHasErrors(['discount_value' => 'A fixed discount must be lower than the price of every selected item. "'
                . $treatment->name . '" costs ₱' . number_format($price, 2) . '.']);

        $this->post(route('promos.store'), $base + ['discount_value' => $price - 1])
            ->assertSessionHasNoErrors();

        // Update checks it too, and still needs at least one item.
        $promo = \App\Models\Promo::withoutGlobalScopes()->where('name', 'Fixed Promo')->firstOrFail();
        $this->put(route('promos.update', $promo), $base + ['discount_value' => $price + 100])
            ->assertSessionHasErrors('discount_value');
        $this->put(route('promos.update', $promo), ['treatment_ids' => []] + $base + ['discount_value' => 10])
            ->assertSessionHasErrors('treatment_ids');
    }

    public function test_product_prices_are_capped(): void
    {
        $this->post(route('inventory.products.store'), [
            'name' => 'Oil', 'inventory_type' => 'backbar', 'usage_unit' => 'ml',
            'conversion_factor' => 1, 'opening_stock' => 0, 'reorder_level' => 0,
            'retail_price' => '99999999999', 'acquisition_cost' => '99999999999',
        ])->assertSessionHasErrors(['retail_price', 'acquisition_cost']);
    }

    public function test_supplier_phone_accepts_mobile_or_landline_only(): void
    {
        foreach (['1651954191561541951465654', '+639171234567', '0917123456', '12345678901'] as $bad) {
            $this->post(route('procurement.suppliers.store'), ['name' => 'S ' . $bad, 'phone' => $bad])
                ->assertSessionHasErrorsIn('createSupplier', ['phone']);
        }

        foreach (['09171234567', '0461234567', '0281234567'] as $i => $good) {
            $this->post(route('procurement.suppliers.store'), ['name' => 'Good ' . $i, 'phone' => $good])
                ->assertSessionHasNoErrors();
        }
    }

    public function test_supplier_product_terms_are_capped_and_can_be_cleared(): void
    {
        $this->post(route('procurement.suppliers.store'), ['name' => 'Terms Supplier'])->assertSessionHasNoErrors();
        $supplier = Supplier::where('name', 'Terms Supplier')->firstOrFail();
        $product = Product::create([
            'spa_id' => $this->owner->spa_id, 'name' => 'Lotion',
            'inventory_type' => 'backbar', 'usage_unit' => 'ml', 'is_active' => true,
        ]);

        $this->post(route('procurement.suppliers.products.store', $supplier), [
            'product_id' => $product->id, 'unit_cost' => '99999999999',
            'lead_time_days' => 9999, 'minimum_order_quantity' => '0.0001',
        ])->assertSessionHasErrorsIn('supplierProduct', ['unit_cost', 'lead_time_days', 'minimum_order_quantity']);

        $this->post(route('procurement.suppliers.products.store', $supplier), [
            'product_id' => $product->id, 'unit_cost' => '250.50',
            'lead_time_days' => 7, 'minimum_order_quantity' => '10',
        ])->assertSessionHasNoErrors();

        $link = SupplierProduct::where('supplier_id', $supplier->id)->where('product_id', $product->id)->firstOrFail();

        // Blank fields now clear the saved values instead of silently keeping them.
        $this->put(route('procurement.suppliers.products.update', [$supplier, $link]), [
            'unit_cost' => '', 'lead_time_days' => '', 'minimum_order_quantity' => '', 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $link->refresh();
        $this->assertNull($link->unit_cost);
        $this->assertNull($link->lead_time_days);
        $this->assertNull($link->minimum_order_quantity);
    }
}
