<?php

namespace Tests\Feature;

use App\Livewire\Admin\ReturnProduct;
use App\Models\BrandList;
use App\Models\CategoryList;
use App\Models\Customer;
use App\Models\ProductDetail;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\ReturnsProduct;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProductReturnTest extends TestCase
{
    public function test_return_uses_sold_price_after_discount_instead_of_original_price(): void
    {
        // 1. Authenticate as Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('Jaffnagold5467@@@'),
                'role' => 'admin',
                'contact' => '0717894272',
            ]
        );
        $this->actingAs($admin);

        // 2. Setup Category, Brand, Product
        $brand = BrandList::firstOrCreate(['brand_name' => 'QA Brand'], ['status' => 'active']);
        $cat = CategoryList::firstOrCreate(['category_name' => 'QA Category'], ['status' => 'active']);

        $product = ProductDetail::create([
            'code' => 'QA-PROD-'.uniqid(),
            'name' => 'QA Test Product 1200',
            'brand_id' => $brand->id,
            'category_id' => $cat->id,
            'status' => 'active',
        ]);

        ProductPrice::create([
            'product_id' => $product->id,
            'supplier_price' => 700,
            'selling_price' => 1200,
            'retail_price' => 1200,
            'wholesale_price' => 1100,
            'discount_price' => 0,
        ]);

        $initialStock = 20;
        ProductStock::create([
            'product_id' => $product->id,
            'available_stock' => $initialStock,
            'total_stock' => $initialStock,
        ]);

        // 3. Setup Customer
        $customer = Customer::create([
            'name' => 'QA Customer '.uniqid(),
            'phone' => '077'.rand(1000000, 9999999),
            'customer_type' => 'retail',
            'current_balance' => 0,
        ]);

        // 4. Create Sale with discount:
        // Customer buys 2 items at 1200 with 200 discount per item => sold at 1000 each
        // Subtotal = 2000, Total = 2000
        $sale = Sale::create([
            'sale_id' => 'SALE-'.uniqid(),
            'invoice_number' => 'INV-QA-'.uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'customer_type' => 'retail',
            'subtotal' => 2000.00,
            'discount_amount' => 400.00,
            'discount_type' => 'fixed',
            'total_amount' => 2000.00,
            'paid_amount' => 2000.00,
            'due_amount' => 0.00,
            'payment_type' => 'full',
            'payment_status' => 'paid',
            'status' => 'confirm',
            'sale_type' => 'admin',
        ]);

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 1200.00,
            'discount_per_unit' => 200.00,
            'total_discount' => 400.00,
            'discount_type' => 'fixed',
            'total' => 2000.00,
        ]);

        // 5. Test Livewire Return Product Component
        Livewire::test(ReturnProduct::class)
            ->call('selectCustomer', $customer->id)
            ->call('selectInvoiceForReturn', $sale->id)
            ->assertSet('returnItems.0.unit_price', 1200.0)
            ->assertSet('returnItems.0.discount_per_unit', 200.0)
            // Verify SOLD PRICE is 1000.0, NOT 1200.0!
            ->assertSet('returnItems.0.selling_price', 1000.0)
            // Set 1 quantity for return
            ->set('returnItems.0.usable_qty', 1)
            // Verify Total Return Value is 1000.0 (sold price), NOT 1200.0!
            ->assertSet('totalReturnValue', 1000.0)
            // Process return
            ->call('processReturn')
            ->call('confirmReturn');

        // 6. Verify Return Record in Database
        $returnRecord = ReturnsProduct::where('sale_id', $sale->id)
            ->where('product_id', $product->id)
            ->latest()
            ->first();

        $this->assertNotNull($returnRecord, 'Return record should be created');
        $this->assertEquals(1, $returnRecord->return_quantity);
        $this->assertEquals(1000.00, (float) $returnRecord->selling_price, 'Return record selling_price must be 1000.00 (effective sold price)');
        $this->assertEquals(1000.00, (float) $returnRecord->total_amount, 'Return record total_amount must be 1000.00');

        // 7. Verify Invoice Subtotal Recalculation
        $sale->refresh();
        $this->assertEquals(1000.00, (float) $sale->subtotal, 'Invoice subtotal should be reduced by sold price 1000.00, not 1200.00');
        $this->assertGreaterThanOrEqual(0, (float) $sale->subtotal, 'Invoice subtotal must never be negative');

        // 8. Verify Inventory Restoration
        $stock = ProductStock::where('product_id', $product->id)->first();
        $this->assertEquals($initialStock + 1, $stock->available_stock, 'Available stock should be restored by 1');
    }

    public function test_full_return_prevents_negative_subtotal_and_uses_sold_price(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('Jaffnagold5467@@@'),
                'role' => 'admin',
                'contact' => '0717894272',
            ]
        );
        $this->actingAs($admin);

        $brand = BrandList::firstOrCreate(['brand_name' => 'QA Brand 2'], ['status' => 'active']);
        $cat = CategoryList::firstOrCreate(['category_name' => 'QA Category 2'], ['status' => 'active']);

        $product = ProductDetail::create([
            'code' => 'QA-PROD-'.uniqid(),
            'name' => 'Full Return Product',
            'brand_id' => $brand->id,
            'category_id' => $cat->id,
            'status' => 'active',
        ]);

        ProductPrice::create([
            'product_id' => $product->id,
            'supplier_price' => 500,
            'selling_price' => 1200,
            'retail_price' => 1200,
            'wholesale_price' => 1100,
            'discount_price' => 0,
        ]);

        ProductStock::create([
            'product_id' => $product->id,
            'available_stock' => 10,
            'total_stock' => 10,
        ]);

        $customer = Customer::create([
            'name' => 'Full Return Customer '.uniqid(),
            'phone' => '077'.rand(1000000, 9999999),
            'customer_type' => 'retail',
            'current_balance' => 0,
        ]);

        // Sale: 1 item at 1200 with 200 discount = sold at 1000
        $sale = Sale::create([
            'sale_id' => 'SALE-'.uniqid(),
            'invoice_number' => 'INV-QA-'.uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'customer_type' => 'retail',
            'subtotal' => 1000.00,
            'discount_amount' => 200.00,
            'discount_type' => 'fixed',
            'total_amount' => 1000.00,
            'paid_amount' => 1000.00,
            'due_amount' => 0.00,
            'payment_type' => 'full',
            'payment_status' => 'paid',
            'status' => 'confirm',
            'sale_type' => 'admin',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 1200.00,
            'discount_per_unit' => 200.00,
            'total_discount' => 200.00,
            'discount_type' => 'fixed',
            'total' => 1000.00,
        ]);

        Livewire::test(ReturnProduct::class)
            ->call('selectCustomer', $customer->id)
            ->call('selectInvoiceForReturn', $sale->id)
            ->assertSet('returnItems.0.selling_price', 1000.0)
            ->call('processFullReturn');

        $sale->refresh();
        $this->assertEquals(0.00, (float) $sale->subtotal, 'Full return subtotal must be exactly 0, never negative');

        $returnRecord = ReturnsProduct::where('sale_id', $sale->id)->first();
        $this->assertNotNull($returnRecord);
        $this->assertEquals(1000.00, (float) $returnRecord->selling_price, 'Return record must use sold price 1000.00');
        $this->assertEquals(1000.00, (float) $returnRecord->total_amount, 'Return total amount must be 1000.00');
    }
}
