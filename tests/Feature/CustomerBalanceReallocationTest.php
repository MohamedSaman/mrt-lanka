<?php

namespace Tests\Feature;

use App\Models\BrandList;
use App\Models\CategoryList;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\ProductDetail;
use App\Models\ProductPrice;
use App\Models\ReturnsProduct;
use App\Models\Sale;
use App\Models\User;
use App\Services\CustomerBalanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerBalanceReallocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_return_on_paid_invoice_offsets_other_unpaid_invoices_chronologically(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'contact' => '0717894272',
            ]
        );

        $brand = BrandList::firstOrCreate(['brand_name' => 'Test Brand'], ['status' => 'active']);
        $cat = CategoryList::firstOrCreate(['category_name' => 'Test Category'], ['status' => 'active']);

        $product = ProductDetail::create([
            'code' => 'TEST-'.uniqid(),
            'name' => 'Test Tool',
            'brand_id' => $brand->id,
            'category_id' => $cat->id,
            'status' => 'active',
        ]);

        ProductPrice::create([
            'product_id' => $product->id,
            'supplier_price' => 500,
            'selling_price' => 1000,
            'retail_price' => 1000,
            'wholesale_price' => 1000,
            'discount_price' => 0,
        ]);

        $customer = Customer::create([
            'name' => 'Test Customer '.uniqid(),
            'phone' => '077'.rand(1000000, 9999999),
            'customer_type' => 'distributor',
            'opening_balance' => 0,
            'opening_balance_paid' => 0,
            'due_amount' => 0,
            'total_due' => 0,
            'overpaid_amount' => 0,
        ]);

        // Invoice 1: 30,000 paid in full
        $sale1 = Sale::create([
            'sale_id' => 'SALE-'.uniqid(),
            'invoice_number' => 'INV-1-'.uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'subtotal' => 30000.00,
            'total_amount' => 30000.00,
            'due_amount' => 0.00,
            'payment_type' => 'full',
            'payment_status' => 'paid',
            'status' => 'confirm',
            'delivery_status' => 'delivered',
            'created_at' => Carbon::now()->subMonths(2),
        ]);

        $payment1 = Payment::create([
            'customer_id' => $customer->id,
            'sale_id' => null,
            'amount' => 30000.00,
            'payment_method' => 'cheque',
            'is_completed' => true,
            'status' => 'approved',
            'payment_date' => Carbon::now()->subMonths(2),
        ]);

        DB::table('payment_allocations')->insert([
            'payment_id' => $payment1->id,
            'sale_id' => $sale1->id,
            'allocated_amount' => 30000.00,
            'created_at' => Carbon::now()->subMonths(2),
        ]);

        // Invoice 2: 10,000 with 6,000 payment => 4,000 unpaid
        $sale2 = Sale::create([
            'sale_id' => 'SALE-'.uniqid(),
            'invoice_number' => 'INV-2-'.uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'subtotal' => 10000.00,
            'total_amount' => 10000.00,
            'due_amount' => 4000.00,
            'payment_type' => 'partial',
            'payment_status' => 'partial',
            'status' => 'confirm',
            'delivery_status' => 'delivered',
            'created_at' => Carbon::now()->subMonth(),
        ]);

        $payment2 = Payment::create([
            'customer_id' => $customer->id,
            'sale_id' => null,
            'amount' => 6000.00,
            'payment_method' => 'cheque',
            'is_completed' => true,
            'status' => 'paid',
            'payment_date' => Carbon::now()->subDays(5),
        ]);

        DB::table('payment_allocations')->insert([
            'payment_id' => $payment2->id,
            'sale_id' => $sale2->id,
            'allocated_amount' => 6000.00,
            'created_at' => Carbon::now()->subDays(5),
        ]);

        // Invoice 3: 9,000 pending
        $sale3 = Sale::create([
            'sale_id' => 'SALE-'.uniqid(),
            'invoice_number' => 'INV-3-'.uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'subtotal' => 9000.00,
            'total_amount' => 9000.00,
            'due_amount' => 9000.00,
            'payment_type' => 'partial',
            'payment_status' => 'pending',
            'status' => 'confirm',
            'delivery_status' => 'delivered',
            'created_at' => Carbon::now()->subDays(2),
        ]);

        // Now customer returns 4,000 on Invoice 1 (which was paid)
        ReturnsProduct::create([
            'sale_id' => $sale1->id,
            'product_id' => $product->id,
            'return_quantity' => 4,
            'usable_quantity' => 4,
            'selling_price' => 1000.00,
            'total_amount' => 4000.00,
            'created_at' => Carbon::now(),
        ]);

        $sale1->update([
            'subtotal' => 26000.00,
            'total_amount' => 26000.00,
        ]);

        // Run syncCustomerBalance
        $synced = CustomerBalanceService::syncCustomerBalance($customer->id);

        $sale1->refresh();
        $sale2->refresh();
        $sale3->refresh();

        // Sale 1 should remain paid (due = 0)
        $this->assertEquals(0, floatval($sale1->due_amount));
        $this->assertEquals('paid', $sale1->payment_status);

        // The 4,000 return credit from Sale 1 should have satisfied Sale 2's 4,000 due!
        $this->assertEquals(0, floatval($sale2->due_amount));
        $this->assertEquals('paid', $sale2->payment_status);

        // Sale 3 should remain with 9,000 due
        $this->assertEquals(9000, floatval($sale3->due_amount));
        $this->assertEquals('pending', $sale3->payment_status);

        // Customer's total due should be exactly 9,000
        $this->assertEquals(9000, floatval($synced->due_amount));
        $this->assertEquals(0, floatval($synced->overpaid_amount));
        $this->assertEquals(9000, floatval($synced->total_due));

        // Test AddCustomerReceipt Livewire component loading
        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\AddCustomerReceipt::class)
            ->call('selectCustomer', $customer->id)
            ->assertSet('totalDueAmount', 0) // before selection
            ->call('selectAllInvoices')
            ->assertSet('totalDueAmount', 9000.0);
    }

    public function test_return_amount_exceeding_all_unpaid_invoices_leaves_remaining_as_overpaid(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'contact' => '0717894272',
            ]
        );

        $customer = Customer::create([
            'name' => 'Excess Test Customer '.uniqid(),
            'phone' => '077'.rand(1000000, 9999999),
            'customer_type' => 'distributor',
            'opening_balance' => 0,
            'opening_balance_paid' => 0,
            'due_amount' => 0,
            'total_due' => 0,
            'overpaid_amount' => 0,
        ]);

        // Sale 1: 10,000 paid in full
        $sale1 = Sale::create([
            'sale_id' => 'SALE-'.uniqid(),
            'invoice_number' => 'INV-E1-'.uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'subtotal' => 10000.00,
            'total_amount' => 10000.00,
            'due_amount' => 0.00,
            'payment_type' => 'full',
            'payment_status' => 'paid',
            'status' => 'confirm',
            'delivery_status' => 'delivered',
            'created_at' => Carbon::now()->subMonths(1),
        ]);

        $payment1 = Payment::create([
            'customer_id' => $customer->id,
            'sale_id' => null,
            'amount' => 10000.00,
            'payment_method' => 'cheque',
            'is_completed' => true,
            'status' => 'approved',
            'payment_date' => Carbon::now()->subMonths(1),
        ]);

        DB::table('payment_allocations')->insert([
            'payment_id' => $payment1->id,
            'sale_id' => $sale1->id,
            'allocated_amount' => 10000.00,
            'created_at' => Carbon::now()->subMonths(1),
        ]);

        // Sale 2: 3,000 unpaid
        $sale2 = Sale::create([
            'sale_id' => 'SALE-'.uniqid(),
            'invoice_number' => 'INV-E2-'.uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'subtotal' => 3000.00,
            'total_amount' => 3000.00,
            'due_amount' => 3000.00,
            'payment_type' => 'partial',
            'payment_status' => 'pending',
            'status' => 'confirm',
            'delivery_status' => 'delivered',
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $brand = BrandList::firstOrCreate(['brand_name' => 'Test Brand'], ['status' => 'active']);
        $cat = CategoryList::firstOrCreate(['category_name' => 'Test Category'], ['status' => 'active']);

        $product = ProductDetail::create([
            'code' => 'TEST-EX-'.uniqid(),
            'name' => 'Test Tool Excess',
            'brand_id' => $brand->id,
            'category_id' => $cat->id,
            'status' => 'active',
        ]);

        // Customer returns 5,000 on Sale 1 (more than Sale 2's 3,000 due)
        ReturnsProduct::create([
            'sale_id' => $sale1->id,
            'product_id' => $product->id,
            'return_quantity' => 1,
            'usable_quantity' => 1,
            'selling_price' => 5000.00,
            'total_amount' => 5000.00,
            'created_at' => Carbon::now(),
        ]);

        $sale1->update([
            'subtotal' => 5000.00,
            'total_amount' => 5000.00,
        ]);

        $synced = CustomerBalanceService::syncCustomerBalance($customer->id);

        $sale1->refresh();
        $sale2->refresh();

        // Sale 1 paid
        $this->assertEquals(0, floatval($sale1->due_amount));
        $this->assertEquals('paid', $sale1->payment_status);

        // Sale 2 fully covered by 3,000 of the 5,000 return
        $this->assertEquals(0, floatval($sale2->due_amount));
        $this->assertEquals('paid', $sale2->payment_status);

        // Remaining 2,000 of return credit becomes overpaid_amount!
        $this->assertEquals(0, floatval($synced->due_amount));
        $this->assertEquals(2000, floatval($synced->overpaid_amount));
        $this->assertEquals(-2000, floatval($synced->total_due));
    }
}
