<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\User;
use App\Models\ProductDetail;
use App\Models\StaffProduct;
use App\Models\ProductStock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Livewire\Concerns\WithDynamicLayout;

#[Title('Staff Product Allocation')]
class StaffProductAllocation extends Component
{
    use WithDynamicLayout;

    // Basic Properties
    public $search = '';
    public $searchResults = [];
    public $staffId = '';

    // Cart Items (allocated products)
    public $cart = [];

    // Staff Properties
    public $staff = [];
    public $selectedStaff = null;

    // Product allocation properties
    public $notes = '';

    // Additional Discount
    public $additionalDiscount = 0;
    public $additionalDiscountType = 'fixed'; // 'fixed' or 'percentage'

    // Modals
    public $showAllocationModal = false;
    public $lastAllocationId = null;

    // Search and pagination
    public $products = [];
    public $perPage = 10;
    public $currentPage = 1;

    public function mount()
    {
        $this->loadStaff();
    }

    // Load staff for dropdown
    public function loadStaff()
    {
        $this->staff = User::where('role', 'staff')
            ->orderBy('name')
            ->get();
    }

    // Search products
    public function searchProducts()
    {
        if (strlen($this->search) < 2) {
            $this->searchResults = [];
            return;
        }

        $this->searchResults = ProductDetail::where(function ($q) {
            $q->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('code', 'like', '%' . $this->search . '%');
        })
            ->with(['brand', 'category', 'price'])
            ->limit(10)
            ->get();
    }

    // Add product to cart
    public function addToCart($productId)
    {
        if (!$this->staffId) {
            $this->js("Swal.fire('error', 'Please select a staff member first.', 'error')");
            return;
        }

        $product = ProductDetail::with(['brand', 'category'])->find($productId);

        if (!$product) {
            $this->js("Swal.fire('error', 'Product not found.', 'error')");
            return;
        }

        // Get available stock
        $stock = ProductStock::where('product_id', $productId)->sum('available_stock');

        if ($stock <= 0) {
            $this->js("Swal.fire('error', 'Product is out of stock.', 'error')");
            return;
        }

        // Check if product already in cart (search by product_id)
        $existingIndex = null;
        foreach ($this->cart as $index => $item) {
            if ($item['product_id'] == $productId) {
                $existingIndex = $index;
                break;
            }
        }

        if ($existingIndex !== null) {
            // Increment quantity if already in cart
            if ($this->cart[$existingIndex]['quantity'] < $stock) {
                $this->cart[$existingIndex]['quantity']++;
                $this->updateCartTotal($existingIndex);
            } else {
                $this->js("Swal.fire('error', 'Cannot add more. Insufficient stock.', 'error')");
                return;
            }
        } else {
            // Add new product to cart
            $this->cart[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_code' => $product->code,
                'brand' => $product->brand?->brand_name ?? 'N/A',
                'category' => $product->category?->category_name ?? 'N/A',
                'unit_price' => $product->price?->selling_price ?? 0,
                'quantity' => 1,
                'discount' => 0,
                'discount_type' => 'fixed',
                'total' => $product->price?->selling_price ?? 0,
                'available_stock' => $stock,
            ];
        }

        $this->search = '';
        $this->searchResults = [];
    }

    // Update quantity in cart
    public function updateQuantity($index, $quantity)
    {
        if (!isset($this->cart[$index])) {
            return;
        }

        $quantity = max(1, (int)$quantity);

        if ($quantity > $this->cart[$index]['available_stock']) {
            $this->js("Swal.fire('error', 'Quantity exceeds available stock.', 'error')");
            return;
        }

        $this->cart[$index]['quantity'] = $quantity;
        $this->updateCartTotal($index);
    }

    // Update price in cart
    public function updatePrice($index, $price)
    {
        if (!isset($this->cart[$index])) {
            return;
        }

        $this->cart[$index]['unit_price'] = max(0, (float)$price);
        $this->updateCartTotal($index);
    }

    // Update discount in cart
    public function updateDiscount($index, $discount)
    {
        if (!isset($this->cart[$index])) {
            return;
        }

        $this->cart[$index]['discount'] = max(0, (float)$discount);
        $this->updateCartTotal($index);
    }

    // Update discount type
    public function updateDiscountType($index, $type)
    {
        if (!isset($this->cart[$index])) {
            return;
        }

        $this->cart[$index]['discount_type'] = $type;
        $this->updateCartTotal($index);
    }

    // Update cart item total
    private function updateCartTotal($index)
    {
        $item = $this->cart[$index];
        $subtotal = $item['quantity'] * $item['unit_price'];

        if ($item['discount_type'] === 'percentage') {
            $discountAmount = ($subtotal * $item['discount']) / 100;
        } else {
            $discountAmount = $item['discount'];
        }

        $this->cart[$index]['total'] = max(0, $subtotal - $discountAmount);
    }

    // Remove item from cart
    public function removeFromCart($index)
    {
        if (isset($this->cart[$index])) {
            unset($this->cart[$index]);
            // Re-index array to maintain sequential indices
            $this->cart = array_values($this->cart);
            $this->js("Swal.fire('success', 'Product removed from allocation.', 'success')");
        }
    }

    // Clear entire cart
    public function clearCart()
    {
        $this->cart = [];
        $this->js("Swal.fire('success', 'Allocation cleared.', 'success')");
    }

    // Computed properties for totals
    public function getSubtotalProperty()
    {
        return collect($this->cart)->sum(function ($item) {
            return $item['quantity'] * $item['unit_price'];
        });
    }

    public function getTotalDiscountProperty()
    {
        return collect($this->cart)->sum(function ($item) {
            if ($item['discount_type'] === 'percentage') {
                $subtotal = $item['quantity'] * $item['unit_price'];
                return ($subtotal * $item['discount']) / 100;
            }
            return $item['discount'];
        });
    }

    public function getGrandTotalProperty()
    {
        $subtotal = $this->getSubtotalProperty();
        $discount = $this->getTotalDiscountProperty();

        // Apply additional discount
        if ($this->additionalDiscountType === 'percentage') {
            $additionalDiscount = ($subtotal * (float)$this->additionalDiscount) / 100;
        } else {
            $additionalDiscount = (float)$this->additionalDiscount;
        }

        return max(0, $subtotal - $discount - $additionalDiscount);
    }

    public function getItemCountProperty()
    {
        return collect($this->cart)->sum('quantity');
    }

    // Allocate products to staff
    public function allocateProducts()
    {
        if (!$this->staffId) {
            $this->js("Swal.fire('error', 'Please select a staff member.', 'error')");
            return;
        }

        if (empty($this->cart)) {
            $this->js("Swal.fire('error', 'Please add products to allocate.', 'error')");
            return;
        }

        try {
            DB::beginTransaction();

            $staff = User::find($this->staffId);
            if (!$staff) {
                throw new \Exception('Staff member not found.');
            }

            // Create staff product allocations for each item
            foreach ($this->cart as $item) {
                // Calculate discount
                $subtotal = $item['quantity'] * $item['unit_price'];
                if ($item['discount_type'] === 'percentage') {
                    $totalDiscount = ($subtotal * $item['discount']) / 100;
                } else {
                    $totalDiscount = $item['discount'];
                }

                // Check if product is already allocated to this staff
                $existingAllocation = StaffProduct::where('staff_id', $this->staffId)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if ($existingAllocation) {
                    // Update existing allocation - add to quantity
                    $existingAllocation->quantity += $item['quantity'];

                    // Recalculate totals based on new quantity
                    $newSubtotal = $existingAllocation->quantity * $item['unit_price'];
                    if ($item['discount_type'] === 'percentage') {
                        $newTotalDiscount = ($newSubtotal * $item['discount']) / 100;
                    } else {
                        $newTotalDiscount = $item['discount'] * $item['quantity'];
                    }

                    $existingAllocation->unit_price = $item['unit_price'];
                    $existingAllocation->discount_per_unit = $item['discount_type'] === 'percentage' ?
                        ($item['unit_price'] * $item['discount']) / 100 : ($item['discount'] / $item['quantity']);
                    $existingAllocation->total_discount += $newTotalDiscount;
                    $existingAllocation->total_value = $newSubtotal - $existingAllocation->total_discount;
                    $existingAllocation->save();
                } else {
                    // Create new staff product record
                    $staffProduct = StaffProduct::create([
                        'product_id' => $item['product_id'],
                        'staff_id' => $this->staffId,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'discount_per_unit' => $item['discount_type'] === 'percentage' ?
                            ($item['unit_price'] * $item['discount']) / 100 : ($item['discount'] / $item['quantity']),
                        'total_discount' => $totalDiscount,
                        'total_value' => $subtotal - $totalDiscount,
                        'sold_quantity' => 0,
                        'sold_value' => 0,
                        'status' => 'assigned',
                    ]);
                }

                // Reduce stock using FIFO
                $this->reduceProductStock($item['product_id'], $item['quantity']);
            }

            DB::commit();

            // Clear cart and reset form
            $this->showAllocationModal = true;
            $this->lastAllocationId = $this->staffId;
            $this->js("Swal.fire('success', 'Products allocated to " . $staff->name . " successfully!', 'success')");

            // Clear cart
            $this->cart = [];
            $this->staffId = '';
            $this->additionalDiscount = 0;
            $this->notes = '';
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Staff Product Allocation Error: ' . $e->getMessage());
            $this->js("Swal.fire('error', 'Error allocating products: " . $e->getMessage() . "', 'error')");
        }
    }

    // Reduce product stock (FIFO method)
    private function reduceProductStock($productId, $quantity)
    {
        // Get batches in FIFO order (oldest first)
        $batches = ProductStock::where('product_id', $productId)
            ->where('available_stock', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $toReduce = min($batch->available_stock, $remaining);
            $batch->available_stock -= $toReduce;
            $batch->save();

            $remaining -= $toReduce;
        }
    }

    public function render()
    {
        return view('livewire.admin.staff-product-allocation')->layout($this->layout);
    }
}
