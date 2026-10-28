<?php

namespace App\Livewire\Staff;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\SaleItem;
use App\Models\StaffProduct;
use App\Models\ProductStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

#[Layout('components.layouts.staff')]
#[Title('Add New Sale')]
class StaffSalesSystem extends Component
{
    public $search = '';
    public $cart = [];
    public $customers = [];
    public $customerId;
    public $paidAmount = 0;
    public $notes = '';
    public $discount = 0;
    public $discountType = 'fixed'; // 'fixed' or 'percentage'

    // Customer modal
    public $showCustomerModal = false;
    public $customerName = '';
    public $customerPhone = '';
    public $customerEmail = '';
    public $customerAddress = '';
    public $customerType = 'retail';

    // Sale complete modal
    public $showSaleModal = false;
    public $createdSale = null;

    public function mount()
    {
        $this->loadCustomers();
        $this->setDefaultCustomer();
    }

    public function loadCustomers()
    {
        // Only load customers created by the logged-in staff user
        // Exclude "Walking Customer"
        $this->customers = Customer::where('user_id', Auth::id())
            ->where('name', '!=', 'Walking Customer')
            ->orderBy('name')
            ->get();
    }

    public function setDefaultCustomer()
    {
        // Don't set any default customer - let user select one
        $this->customerId = '';
    }

    public function addToCart($product)
    {
        $productData = is_array($product) ? $product : json_decode($product, true);

        // Check if product already exists in cart
        $existingIndex = collect($this->cart)->search(function ($item) use ($productData) {
            return $item['id'] == $productData['id'];
        });

        if ($existingIndex !== false) {
            // Increase quantity if exists
            $this->cart[$existingIndex]['quantity']++;
            $this->cart[$existingIndex]['total'] = $this->cart[$existingIndex]['quantity'] * $this->cart[$existingIndex]['price'];
        } else {
            // Add new item to cart
            $this->cart[] = [
                'id' => $productData['id'],
                'name' => $productData['name'],
                'code' => $productData['code'] ?? '',
                'model' => $productData['model'] ?? '',
                'price' => $productData['price'],
                'quantity' => 1,
                'stock' => $productData['stock'],
                'total' => $productData['price'],
                'image' => $productData['image'] ?? asset('images/default-product.png'),
                'key' => uniqid(),
            ];
        }

        // Clear search after adding product
        $this->search = '';
    }

    public function updateQuantity($index, $quantity)
    {
        if ($quantity <= 0) {
            unset($this->cart[$index]);
            $this->cart = array_values($this->cart);
            return;
        }

        if ($quantity > $this->cart[$index]['stock']) {
            $this->dispatch('showToast', [
                'type' => 'warning',
                'message' => 'Quantity exceeds available stock!'
            ]);
            $quantity = $this->cart[$index]['stock'];
        }

        $this->cart[$index]['quantity'] = $quantity;
        $this->cart[$index]['total'] = $quantity * (float)$this->cart[$index]['price'];
    }

    public function updatePrice($index, $price)
    {
        $price = max(0, (float)$price);
        $this->cart[$index]['price'] = $price;
        $this->cart[$index]['total'] = $this->cart[$index]['quantity'] * $price;
    }

    public function removeFromCart($index)
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function decrementQuantity($index)
    {
        if (isset($this->cart[$index])) {
            $newQty = $this->cart[$index]['quantity'] - 1;
            $this->updateQuantity($index, $newQty);
        }
    }

    public function incrementQuantity($index)
    {
        if (isset($this->cart[$index])) {
            $newQty = $this->cart[$index]['quantity'] + 1;
            $this->updateQuantity($index, $newQty);
        }
    }

    public function clearCart()
    {
        $this->cart = [];
    }

    public function getSubtotalProperty()
    {
        return collect($this->cart)->sum('total');
    }

    public function getDiscountAmountProperty()
    {
        if ($this->discountType === 'percentage') {
            return ($this->subtotal * $this->discount) / 100;
        }
        return $this->discount;
    }

    public function getGrandTotalProperty()
    {
        return max(0, $this->subtotal - $this->discountAmount);
    }

    public function getDueAmountProperty()
    {
        return max(0, $this->grandTotal - $this->paidAmount);
    }

    public function toggleDiscountType()
    {
        $this->discountType = $this->discountType === 'fixed' ? 'percentage' : 'fixed';
        $this->discount = 0;
    }

    public function removeDiscount()
    {
        $this->discount = 0;
    }

    public function openCustomerModal()
    {
        $this->showCustomerModal = true;
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerEmail = '';
        $this->customerAddress = '';
        $this->customerType = 'retail';
    }

    public function closeCustomerModal()
    {
        $this->showCustomerModal = false;
        $this->resetErrorBag();
    }

    public function createCustomer()
    {
        $this->validate([
            'customerName' => 'required|string|max:255',
            'customerPhone' => 'required|string|max:20',
            'customerAddress' => 'required|string|max:500',
            'customerEmail' => 'nullable|email|max:255',
            'customerType' => 'required|in:retail,wholesale,distributor',
        ]);

        try {
            $customer = Customer::create([
                'name' => $this->customerName,
                'phone' => $this->customerPhone,
                'email' => $this->customerEmail,
                'address' => $this->customerAddress,
                'type' => $this->customerType,
                'user_id' => Auth::id(),
            ]);

            $this->loadCustomers();
            $this->customerId = $customer->id;
            $this->closeCustomerModal();

            $this->dispatch('showToast', [
                'type' => 'success',
                'message' => 'Customer created successfully!'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('showToast', [
                'type' => 'error',
                'message' => 'Error creating customer: ' . $e->getMessage()
            ]);
        }
    }

    public function downloadInvoice()
    {
        if (!$this->createdSale) {
            $this->js("Swal.fire('error', 'No sale found to download.', 'error')");
            return;
        }

        $sale = Sale::with(['customer', 'items'])->find($this->createdSale->id);

        if (!$sale) {
            $this->js("Swal.fire('error', 'Sale not found.', 'error')");
            return;
        }

        $pdf = PDF::loadView('admin.sales.invoice', compact('sale'));
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('dpi', 150);
        $pdf->setOption('defaultFont', 'sans-serif');

        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf->output();
            },
            'invoice-' . $sale->invoice_number . '.pdf'
        );
    }

    public function closeModal()
    {
        $this->showSaleModal = false;
        $this->createdSale = null;
        // Reset all fields when closing modal
        $this->reset(['cart', 'search', 'customerId', 'notes', 'discount', 'discountType', 'paidAmount']);
        $this->loadCustomers();
        $this->setDefaultCustomer();
    }

    public function createNewSale()
    {
        $this->reset(['cart', 'search', 'customerId', 'notes', 'discount', 'discountType', 'paidAmount', 'createdSale']);
        $this->loadCustomers();
        $this->setDefaultCustomer();
        $this->showSaleModal = false;
    }

    public function validateAndCreateSale()
    {
        if (empty($this->cart)) {
            $this->dispatch('showToast', [
                'type' => 'error',
                'message' => 'Cart is empty!'
            ]);
            return;
        }

        if (!$this->customerId) {
            $this->dispatch('showToast', [
                'type' => 'error',
                'message' => 'Please select a customer!'
            ]);
            return;
        }

        // Check stock availability for all items
        foreach ($this->cart as $item) {
            $staffProducts = StaffProduct::where('product_id', $item['id'])
                ->where('staff_id', Auth::id())
                ->get();

            $totalRemaining = $staffProducts->sum(function ($sp) {
                return $sp->quantity - $sp->sold_quantity;
            });

            if ($totalRemaining < $item['quantity']) {
                $this->dispatch('showToast', [
                    'type' => 'error',
                    'message' => "Insufficient stock for {$item['name']}"
                ]);
                return;
            }
        }

        $this->createSale();
    }

    public function createSale()
    {
        try {
            DB::beginTransaction();

            // Calculate totals
            $subtotal = collect($this->cart)->sum('total');
            $discountAmount = $this->discountType === 'percentage'
                ? ($subtotal * $this->discount) / 100
                : $this->discount;
            $grandTotal = max(0, $subtotal - $discountAmount);
            $dueAmount = max(0, $grandTotal - $this->paidAmount);

            // Determine payment status and payment type
            $paymentStatus = 'pending';
            $paymentType = 'partial';

            if ($this->paidAmount >= $grandTotal) {
                $paymentStatus = 'paid';
                $paymentType = 'full';
            } elseif ($this->paidAmount > 0) {
                $paymentStatus = 'partial';
                $paymentType = 'partial';
            }

            // Create sale
            $sale = Sale::create([
                'sale_id' => Sale::generateSaleId(),
                'invoice_number' => Sale::generateInvoiceNumber(),
                'customer_id' => $this->customerId,
                'user_id' => Auth::id(),
                'sale_type' => 'staff',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'total_amount' => $grandTotal,
                'payment_type' => $paymentType,
                'payment_status' => $paymentStatus,
                'due_amount' => $dueAmount,
                'notes' => $this->notes,
                'status' => 'confirm',
            ]);

            // Create sale items and update staff product quantities
            foreach ($this->cart as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['id'],
                    'product_code' => $item['code'] ?? '',
                    'product_name' => $item['name'],
                    'product_model' => $item['model'] ?? '',
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'discount_per_unit' => 0,
                    'total_discount' => 0,
                    'total' => $item['total'],
                ]);

                // Reduce staff product allocation using FIFO
                $this->reduceStaffProduct($item['id'], $item['quantity']);
            }

            DB::commit();

            // Load the created sale with relationships
            $this->createdSale = Sale::with(['customer', 'items', 'user'])->find($sale->id);
            $this->showSaleModal = true;

            // Show success message
            $this->dispatch('showToast', [
                'type' => 'success',
                'message' => 'Sale created successfully! Invoice #' . $sale->invoice_number
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $errorMessage = addslashes($e->getMessage());
            $this->js("Swal.fire('error', 'Failed to create sale: {$errorMessage}', 'error')");
        }
    }

    private function reduceStaffProduct($productId, $quantity)
    {
        $remaining = $quantity;

        // Get staff products allocated to this staff member, ordered by date (FIFO)
        $staffProducts = StaffProduct::where('product_id', $productId)
            ->where('staff_id', Auth::id())
            ->whereRaw('quantity - sold_quantity > 0')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($staffProducts as $staffProduct) {
            if ($remaining <= 0) break;

            $remainingQty = $staffProduct->quantity - $staffProduct->sold_quantity;
            $deductQty = min($remaining, $remainingQty);

            $staffProduct->sold_quantity += $deductQty;

            $staffProduct->save();
            $remaining -= $deductQty;
        }
    }

    public function render()
    {
        // Get only staff allocated products
        $products = StaffProduct::with(['product.category', 'product.brand'])
            ->where('staff_id', Auth::id())
            ->whereRaw('quantity - sold_quantity > 0')
            ->when($this->search, function ($query) {
                $query->whereHas('product', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('code', 'like', '%' . $this->search . '%')
                        ->orWhere('model', 'like', '%' . $this->search . '%');
                });
            })
            ->get()
            ->map(function ($staffProduct) {
                $product = $staffProduct->product;
                $remainingQty = $staffProduct->quantity - $staffProduct->sold_quantity;
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'model' => $product->model,
                    'price' => $product->selling_price,
                    'stock' => $remainingQty,
                    'image' => $product->image ? asset('storage/' . $product->image) : asset('images/default-product.png'),
                    'category' => $product->category->category_name ?? 'N/A',
                    'brand' => $product->brand->brand_name ?? 'N/A',
                ];
            });

        // Calculate totals
        $subtotal = collect($this->cart)->sum('total');
        $discountAmount = $this->discountType === 'percentage'
            ? ($subtotal * $this->discount) / 100
            : $this->discount;
        $grandTotal = max(0, $subtotal - $discountAmount);
        $dueAmount = max(0, $grandTotal - $this->paidAmount);

        return view('livewire.staff.staff-sales-system', [
            'products' => $products,
            'subtotal' => $subtotal,
            'discountAmount' => $discountAmount,
            'grandTotal' => $grandTotal,
            'dueAmount' => $dueAmount,
        ]);
    }
}
