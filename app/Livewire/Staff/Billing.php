<?php

namespace App\Livewire\Staff;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use App\Models\Customer;
use App\Models\ProductDetail;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\Cheque;
use App\Models\StaffProduct;
use App\Models\StaffSale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

#[Layout('components.layouts.staff')]
#[Title('Staff POS Billing')]
class Billing extends Component
{
    use WithFileUploads;

    // Basic Properties
    public $search = '';
    public $searchResults = [];
    public $customerId = '';

    // Cart Items
    public $cart = [];

    // Customer Properties
    public $customers = [];
    public $selectedCustomer = null;

    // Customer Form
    public $customerName = '';
    public $customerPhone = '';
    public $customerEmail = '';
    public $customerAddress = '';
    public $customerType = 'retail';
    public $businessName = '';

    // Sale Properties
    public $notes = '';

    // Payment Properties (same as admin StoreBilling)
    public $paymentMethod = 'cash';
    public $paidAmount = 0;

    // Cash Payment
    public $cashAmount = 0;

    // Cheque Payment
    public $cheques = [];
    public $tempChequeNumber = '';
    public $tempBankName = '';
    public $tempChequeDate = '';
    public $tempChequeAmount = 0;

    // Bank Transfer Payment
    public $bankTransferAmount = 0;
    public $bankTransferBankName = '';
    public $bankTransferReferenceNumber = '';

    // Discount Properties
    public $additionalDiscount = 0;
    public $additionalDiscountType = 'fixed';

    // Modals
    public $showSaleModal = false;
    public $showCustomerModal = false;
    public $showPaymentConfirmModal = false;
    public $lastSaleId = null;
    public $createdSale = null;
    public $pendingDueAmount = 0;

    public function mount()
    {
        // Staff can only access this if they have allocated products
        if (!StaffProduct::where('staff_id', Auth::id())->exists()) {
            session()->flash('warning', 'No products allocated to you. Please contact admin.');
        }

        $this->loadCustomers();
        $this->tempChequeDate = now()->format('Y-m-d');
    }

    // Load customers - only customers created by this staff member
    public function loadCustomers()
    {
        $staffId = Auth::id();

        // Get customers created by this staff member only
        $this->customers = Customer::where('user_id', $staffId)
            ->orderBy('name')
            ->get();
    }

    // Computed Properties for Totals
    public function getSubtotalProperty()
    {
        return collect($this->cart)->sum('total');
    }

    public function getTotalDiscountProperty()
    {
        return collect($this->cart)->sum(function ($item) {
            return ($item['discount'] * $item['quantity']);
        });
    }

    public function getSubtotalAfterItemDiscountsProperty()
    {
        return $this->subtotal;
    }

    public function getAdditionalDiscountAmountProperty()
    {
        if (empty($this->additionalDiscount) || $this->additionalDiscount <= 0) {
            return 0;
        }

        if ($this->additionalDiscountType === 'percentage') {
            return ($this->subtotalAfterItemDiscounts * $this->additionalDiscount) / 100;
        }

        return min($this->additionalDiscount, $this->subtotalAfterItemDiscounts);
    }

    public function getGrandTotalProperty()
    {
        return $this->subtotalAfterItemDiscounts - $this->additionalDiscountAmount;
    }

    public function getTotalPaidAmountProperty()
    {
        $total = 0;

        if ($this->paymentMethod === 'cash') {
            $total = $this->cashAmount;
        } elseif ($this->paymentMethod === 'cheque') {
            $total = collect($this->cheques)->sum('amount');
        } elseif ($this->paymentMethod === 'bank_transfer') {
            $total = $this->bankTransferAmount;
        }

        return $total;
    }

    public function getDueAmountProperty()
    {
        if ($this->paymentMethod === 'credit') {
            return $this->grandTotal;
        }
        return max(0, $this->grandTotal - (int)$this->totalPaidAmount);
    }

    public function getPaymentStatusProperty()
    {
        if ($this->paymentMethod === 'credit' || (int)$this->totalPaidAmount <= 0) {
            return 'pending';
        } elseif ((int)$this->totalPaidAmount >= $this->grandTotal) {
            return 'paid';
        } else {
            return 'partial';
        }
    }

    public function getDatabasePaymentTypeProperty()
    {
        if ($this->paymentMethod === 'credit') {
            return 'partial';
        }
        if ((int)$this->totalPaidAmount >= $this->grandTotal) {
            return 'full';
        } else {
            return 'partial';
        }
    }

    public function updatedCustomerId($value)
    {
        if ($value) {
            $customer = Customer::find($value);
            if ($customer) {
                $this->selectedCustomer = $customer;
            }
        } else {
            $this->setDefaultCustomer();
        }
    }

    public function updatedPaymentMethod($value)
    {
        $this->cashAmount = 0;
        $this->cheques = [];
        $this->bankTransferAmount = 0;
        $this->bankTransferBankName = '';
        $this->bankTransferReferenceNumber = '';

        if ($value === 'cash') {
            $this->cashAmount = $this->grandTotal;
        } elseif ($value === 'bank_transfer') {
            $this->bankTransferAmount = $this->grandTotal;
        }
    }

    public function updated($propertyName)
    {
        if (
            str_contains($propertyName, 'cart') ||
            str_contains($propertyName, 'additionalDiscount') ||
            str_contains($propertyName, 'additionalDiscountType')
        ) {
            if ($this->paymentMethod === 'cash') {
                $this->cashAmount = $this->grandTotal;
            } elseif ($this->paymentMethod === 'bank_transfer') {
                $this->bankTransferAmount = $this->grandTotal;
            }
        }
    }

    // Add Cheque
    public function addCheque()
    {
        $this->validate([
            'tempChequeNumber' => 'required|string|max:50',
            'tempBankName' => 'required|string|max:100',
            'tempChequeDate' => 'required|date',
            'tempChequeAmount' => 'required|numeric|min:0.01',
        ]);

        $existingCheque = Cheque::where('cheque_number', $this->tempChequeNumber)->first();
        if ($existingCheque) {
            $this->js("Swal.fire('Error!', 'Cheque number already exists.', 'error');");
            return;
        }

        $this->cheques[] = [
            'number' => $this->tempChequeNumber,
            'bank_name' => $this->tempBankName,
            'date' => $this->tempChequeDate,
            'amount' => $this->tempChequeAmount,
        ];

        $this->tempChequeNumber = '';
        $this->tempBankName = '';
        $this->tempChequeDate = now()->format('Y-m-d');
        $this->tempChequeAmount = 0;

        $this->js("Swal.fire('Success!', 'Cheque added successfully!', 'success')");
    }

    public function removeCheque($index)
    {
        unset($this->cheques[$index]);
        $this->cheques = array_values($this->cheques);
        $this->js("Swal.fire('success', 'Cheque removed!', 'success')");
    }

    public function resetCustomerFields()
    {
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerEmail = '';
        $this->customerAddress = '';
        $this->customerType = 'retail';
        $this->businessName = '';
    }

    public function openCustomerModal()
    {
        $this->resetCustomerFields();
        $this->showCustomerModal = true;
    }

    public function closeCustomerModal()
    {
        $this->showCustomerModal = false;
        $this->resetCustomerFields();
    }

    // Create new customer - linked to this staff member
    public function createCustomer()
    {
        $this->validate([
            'customerName' => 'required|string|max:255',
            'customerPhone' => 'nullable|string|max:10|unique:customers,phone',
            'customerEmail' => 'nullable|email|unique:customers,email',
            'customerAddress' => 'required|string',
            'customerType' => 'required|in:retail,wholesale',
        ]);

        try {
            $customer = Customer::create([
                'name' => $this->customerName,
                'phone' => $this->customerPhone ?: null,
                'email' => $this->customerEmail,
                'address' => $this->customerAddress,
                'type' => $this->customerType,
                'business_name' => $this->businessName,
                'user_id' => Auth::id(), // Link customer to staff who created them
            ]);

            $this->loadCustomers();
            $this->customerId = $customer->id;
            $this->selectedCustomer = $customer;
            $this->closeCustomerModal();

            $this->js("Swal.fire('success', 'Customer created successfully!', 'success')");
        } catch (\Exception $e) {
            $this->js("Swal.fire('error', 'Failed to create customer', 'error')");
        }
    }

    // Search for staff allocated products only
    public function updatedSearch()
    {
        if (strlen($this->search) >= 2) {
            $staffId = Auth::id();

            // Get staff products with their product details
            $staffProducts = StaffProduct::where('staff_id', $staffId)
                ->with(['product'])
                ->get();

            // Filter based on search term
            $this->searchResults = $staffProducts->filter(function ($staffProduct) {
                if (!$staffProduct->product) {
                    return false;
                }

                $searchTerm = strtolower($this->search);
                $name = strtolower($staffProduct->product->name ?? '');
                $code = strtolower($staffProduct->product->code ?? '');
                $model = strtolower($staffProduct->product->model ?? '');

                return str_contains($name, $searchTerm) ||
                    str_contains($code, $searchTerm) ||
                    str_contains($model, $searchTerm);
            })
                ->take(10)
                ->map(function ($staffProduct) {
                    $availableStock = ($staffProduct->quantity ?? 0) - ($staffProduct->sold_quantity ?? 0);

                    return [
                        'id' => $staffProduct->product->id,
                        'name' => $staffProduct->product->name,
                        'code' => $staffProduct->product->code,
                        'model' => $staffProduct->product->model ?? '',
                        'price' => $staffProduct->unit_price,
                        'stock' => max(0, $availableStock),
                        'image' => $staffProduct->product->image ?? ''
                    ];
                })
                ->values()
                ->toArray();
        } else {
            $this->searchResults = [];
        }
    }

    public function addToCart($product)
    {
        if (($product['stock'] ?? 0) <= 0) {
            $this->js("Swal.fire('error', 'Not enough stock available!', 'error')");
            return;
        }

        $existing = collect($this->cart)->firstWhere('id', $product['id']);

        if ($existing) {
            if (($existing['quantity'] + 1) > $product['stock']) {
                $this->js("Swal.fire('error', 'Not enough stock available!', 'error')");
                return;
            }

            $this->cart = collect($this->cart)->map(function ($item) use ($product) {
                if ($item['id'] == $product['id']) {
                    $item['quantity'] += 1;
                    $item['total'] = ($item['price'] - $item['discount']) * $item['quantity'];
                    if (!isset($item['key'])) {
                        $item['key'] = uniqid('cart_');
                    }
                }
                return $item;
            })->toArray();
        } else {
            $newItem = [
                'key' => uniqid('cart_'),
                'id' => $product['id'],
                'name' => $product['name'],
                'code' => $product['code'],
                'model' => $product['model'],
                'price' => $product['price'],
                'quantity' => 1,
                'discount' => 0,
                'total' => $product['price'],
                'stock' => $product['stock']
            ];

            array_unshift($this->cart, $newItem);
        }

        $this->search = '';
        $this->searchResults = [];
    }

    public function updateQuantity($index, $quantity)
    {
        if ($quantity < 1) $quantity = 1;

        $productStock = $this->cart[$index]['stock'];
        if ($quantity > $productStock) {
            $this->js("Swal.fire('error', 'Not enough stock! Maximum: {$productStock}', 'error')");
            return;
        }

        $this->cart[$index]['quantity'] = $quantity;
        $this->cart[$index]['total'] = ($this->cart[$index]['price'] - $this->cart[$index]['discount']) * $quantity;
    }

    public function incrementQuantity($index)
    {
        $currentQuantity = $this->cart[$index]['quantity'];
        $productStock = $this->cart[$index]['stock'];

        if (($currentQuantity + 1) > $productStock) {
            $this->js("Swal.fire('error', 'Not enough stock! Maximum: {$productStock}', 'error')");
            return;
        }

        $this->cart[$index]['quantity'] += 1;
        $this->cart[$index]['total'] = ($this->cart[$index]['price'] - $this->cart[$index]['discount']) * $this->cart[$index]['quantity'];
    }

    public function decrementQuantity($index)
    {
        if ($this->cart[$index]['quantity'] > 1) {
            $this->cart[$index]['quantity'] -= 1;
            $this->cart[$index]['total'] = ($this->cart[$index]['price'] - $this->cart[$index]['discount']) * $this->cart[$index]['quantity'];
        }
    }

    public function updatePrice($index, $price)
    {
        if ($price < 0) $price = 0;

        $this->cart[$index]['price'] = $price;
        $this->cart[$index]['total'] = ($price - $this->cart[$index]['discount']) * $this->cart[$index]['quantity'];
    }

    public function updateDiscount($index, $discount)
    {
        if ($discount < 0) $discount = 0;
        if ($discount > $this->cart[$index]['price']) {
            $discount = $this->cart[$index]['price'];
        }

        $this->cart[$index]['discount'] = $discount;
        $this->cart[$index]['total'] = ($this->cart[$index]['price'] - $discount) * $this->cart[$index]['quantity'];
    }

    public function removeFromCart($index)
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
        $this->js("Swal.fire('success', 'Product removed!', 'success')");
    }

    public function clearCart()
    {
        $this->cart = [];
        $this->additionalDiscount = 0;
        $this->additionalDiscountType = 'fixed';
        $this->resetPaymentFields();
        $this->js("Swal.fire('success', 'Cart cleared!', 'success')");
    }

    public function resetPaymentFields()
    {
        $this->cashAmount = 0;
        $this->cheques = [];
        $this->bankTransferAmount = 0;
        $this->bankTransferBankName = '';
        $this->bankTransferReferenceNumber = '';
        $this->paymentMethod = 'cash';
    }

    public function updatedAdditionalDiscount($value)
    {
        if ($value === '') {
            $this->additionalDiscount = 0;
            return;
        }

        if ($value < 0) {
            $this->additionalDiscount = 0;
            return;
        }

        if ($this->additionalDiscountType === 'percentage' && $value > 100) {
            $this->additionalDiscount = 100;
            return;
        }

        if ($this->additionalDiscountType === 'fixed' && $value > $this->subtotalAfterItemDiscounts) {
            $this->additionalDiscount = $this->subtotalAfterItemDiscounts;
            return;
        }
    }

    public function toggleDiscountType()
    {
        $this->additionalDiscountType = $this->additionalDiscountType === 'percentage' ? 'fixed' : 'percentage';
        $this->additionalDiscount = 0;
    }

    public function removeAdditionalDiscount()
    {
        $this->additionalDiscount = 0;
        $this->js("Swal.fire('success', 'Discount removed!', 'success')");
    }

    // Validate Payment Before Creating Sale
    public function validateAndCreateSale()
    {
        if (empty($this->cart)) {
            $this->js("Swal.fire('error', 'Please add at least one product to the sale.', 'error')");
            return;
        }

        if (!$this->selectedCustomer && !$this->customerId) {
            $this->js("Swal.fire('error', 'Please select a customer.', 'error')");
            return;
        }

        // Validate payment method specific fields
        if ($this->paymentMethod === 'cash') {
            if ($this->cashAmount < 0) {
                $this->js("Swal.fire('error', 'Please enter cash amount.', 'error')");
                return;
            }
        } elseif ($this->paymentMethod === 'cheque') {
            if (empty($this->cheques)) {
                $this->js("Swal.fire('error', 'Please add at least one cheque.', 'error')");
                return;
            }

            // Require total of cheques to exactly match grand total (no due allowed)
            $totalChequeAmount = round(collect($this->cheques)->sum('amount'), 2);
            $grand = round($this->grandTotal, 2);
            if ($totalChequeAmount !== $grand) {
                $this->js("Swal.fire('error', 'Total cheque amount must equal the grand total of Rs. ' . number_format($this->grandTotal, 2) . '. Partial cheque payments are not allowed.', 'error')");
                return;
            }
        } elseif ($this->paymentMethod === 'bank_transfer') {
            if ($this->bankTransferAmount <= 0) {
                $this->js("Swal.fire('error', 'Please enter bank transfer amount.', 'error')");
                return;
            }
        }

        // Check if payment amount matches grand total (except for credit)
        if ($this->paymentMethod !== 'credit') {
            if ((int)$this->totalPaidAmount < $this->grandTotal) {
                $this->pendingDueAmount = $this->grandTotal - (int)$this->totalPaidAmount;
                $this->showPaymentConfirmModal = true;
                return;
            }
        }

        $this->createSale();
    }

    public function confirmSaleWithDue()
    {
        $this->showPaymentConfirmModal = false;
        $this->createSale();
    }

    public function cancelSaleConfirmation()
    {
        $this->showPaymentConfirmModal = false;
        $this->pendingDueAmount = 0;
    }

    // Create Sale - saves to both staff_sales and sales tables
    // Payment requires admin approval
    public function createSale()
    {
        try {
            DB::beginTransaction();

            $customer = $this->selectedCustomer ?? Customer::find($this->customerId);

            if (!$customer) {
                $this->js("Swal.fire('error', 'Customer not found.', 'error')");
                return;
            }

            $staffId = Auth::id();

            // Update staff product sold_quantity
            foreach ($this->cart as $item) {
                $staffProduct = StaffProduct::where('staff_id', $staffId)
                    ->where('product_id', $item['id'])
                    ->first();

                if ($staffProduct) {
                    $staffProduct->increment('sold_quantity', $item['quantity']);
                }
            }

            // Create sale in sales table with 'staff' type and 'pending' status for payment
            $sale = Sale::create([
                'sale_id' => Sale::generateSaleId(),
                'invoice_number' => Sale::generateInvoiceNumber(),
                'customer_id' => $customer->id,
                'customer_type' => $customer->type,
                'subtotal' => $this->subtotal,
                'discount_amount' => $this->totalDiscount + $this->additionalDiscountAmount,
                'total_amount' => $this->grandTotal,
                'payment_type' => $this->databasePaymentType,
                'payment_status' => 'pending', // Payment requires admin approval
                'due_amount' => $this->dueAmount,
                'notes' => $this->notes,
                'user_id' => $staffId,
                'status' => 'confirm',
                'sale_type' => 'staff'
            ]);

            // Create sale items
            foreach ($this->cart as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['id'],
                    'product_code' => $item['code'],
                    'product_name' => $item['name'],
                    'product_model' => $item['model'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'discount_per_unit' => $item['discount'],
                    'total_discount' => $item['discount'] * $item['quantity'],
                    'total' => $item['total']
                ]);
            }

            // Create Payment Record with pending status
            if ($this->paymentMethod !== 'credit' && (int)$this->totalPaidAmount > 0) {
                $payment = Payment::create([
                    'customer_id' => $customer->id,
                    'sale_id' => $sale->id,
                    'amount' => (int)$this->totalPaidAmount,
                    'payment_method' => $this->paymentMethod,
                    'payment_date' => now(),
                    'is_completed' => false, // Not completed until admin approves
                    'status' => 'pending', // Requires admin approval
                    'created_by' => $staffId, // Track which staff made the payment
                ]);

                // Handle payment method specific data
                if ($this->paymentMethod === 'cash') {
                    $payment->update([
                        'payment_reference' => 'STAFF-CASH-' . now()->format('YmdHis'),
                    ]);
                } elseif ($this->paymentMethod === 'cheque') {
                    foreach ($this->cheques as $cheque) {
                        Cheque::create([
                            'cheque_number' => $cheque['number'],
                            'cheque_date' => $cheque['date'],
                            'bank_name' => $cheque['bank_name'],
                            'cheque_amount' => $cheque['amount'],
                            'status' => 'pending', // Cheque also needs approval
                            'customer_id' => $customer->id,
                            'payment_id' => $payment->id,
                        ]);
                    }

                    $payment->update([
                        'payment_reference' => 'STAFF-CHQ-' . collect($this->cheques)->pluck('number')->implode(','),
                        'bank_name' => collect($this->cheques)->pluck('bank_name')->unique()->implode(', '),
                    ]);
                } elseif ($this->paymentMethod === 'bank_transfer') {
                    $payment->update([
                        'payment_reference' => $this->bankTransferReferenceNumber ?: 'STAFF-BANK-' . now()->format('YmdHis'),
                        'bank_name' => $this->bankTransferBankName,
                        'transfer_date' => now(),
                        'transfer_reference' => $this->bankTransferReferenceNumber,
                    ]);
                }
            }

            // Create or Update staff_sales table with sold values
            $cartTotalQuantity = collect($this->cart)->sum('quantity');
            $cartTotalValue = $this->grandTotal;

            $staffSale = StaffSale::where('staff_id', $staffId)->first();

            if ($staffSale) {
                // Update existing staff_sales record
                $staffSale->increment('sold_quantity', $cartTotalQuantity);
                $staffSale->increment('sold_value', $cartTotalValue);

                // Update status based on completion
                $totalQuantity = $staffSale->total_quantity;
                $soldQuantity = $staffSale->sold_quantity;

                if ($soldQuantity >= $totalQuantity) {
                    $staffSale->update(['status' => 'completed']);
                } elseif ($soldQuantity > 0) {
                    $staffSale->update(['status' => 'partial']);
                }
            } else {
                // Create new staff_sales record if it doesn't exist
                $staffSale = StaffSale::create([
                    'staff_id' => $staffId,
                    'admin_id' => null, // No admin assigned yet
                    'total_quantity' => 0, // Will be set by admin during allocation
                    'total_value' => 0, // Will be set by admin during allocation
                    'sold_quantity' => $cartTotalQuantity,
                    'sold_value' => $cartTotalValue,
                    'status' => 'partial',
                ]);
            }

            DB::commit();

            $this->lastSaleId = $sale->id;
            $this->createdSale = Sale::with(['customer', 'items', 'payments'])->find($sale->id);
            $this->showSaleModal = true;

            // Clear cart and reset
            $this->cart = [];
            $this->additionalDiscount = 0;
            $this->additionalDiscountType = 'fixed';
            $this->resetPaymentFields();
            $this->notes = '';
            $this->setDefaultCustomer();

            $this->js("Swal.fire('success', 'Sale created successfully! Payment status: pending admin approval.', 'success')");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Staff billing error: ' . $e->getMessage());
            $this->js("Swal.fire('error', 'Failed to create sale: " . $e->getMessage() . "', 'error')");
        }
    }

    public function downloadInvoice()
    {
        if (!$this->lastSaleId) {
            $this->js("Swal.fire('error', 'No sale found to download.', 'error')");
            return;
        }

        $sale = Sale::with(['customer', 'items'])->find($this->lastSaleId);

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
        $this->lastSaleId = null;
    }

    public function createNewSale()
    {
        // Reset all properties except customers
        $this->search = '';
        $this->searchResults = [];
        $this->customerId = '';
        $this->cart = [];
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerEmail = '';
        $this->customerAddress = '';
        $this->customerType = 'retail';
        $this->businessName = '';
        $this->notes = '';
        $this->paymentMethod = 'cash';
        $this->paidAmount = 0;
        $this->cashAmount = 0;
        $this->cheques = [];
        $this->tempChequeNumber = '';
        $this->tempBankName = '';
        $this->tempChequeDate = now()->format('Y-m-d');
        $this->tempChequeAmount = 0;
        $this->bankTransferAmount = 0;
        $this->bankTransferBankName = '';
        $this->bankTransferReferenceNumber = '';
        $this->additionalDiscount = 0;
        $this->additionalDiscountType = 'fixed';
        $this->showSaleModal = false;
        $this->showCustomerModal = false;
        $this->showPaymentConfirmModal = false;
        $this->lastSaleId = null;
        $this->createdSale = null;
        $this->pendingDueAmount = 0;

        $this->loadCustomers();
        $this->setDefaultCustomer();
    }

    public function render()
    {
        return view('livewire.staff.billing', [
            'subtotal' => $this->subtotal,
            'totalDiscount' => $this->totalDiscount,
            'subtotalAfterItemDiscounts' => $this->subtotalAfterItemDiscounts,
            'additionalDiscountAmount' => $this->additionalDiscountAmount,
            'grandTotal' => $this->grandTotal,
            'totalPaidAmount' => $this->totalPaidAmount,
            'dueAmount' => $this->dueAmount,
            'paymentStatus' => $this->paymentStatus,
        ]);
    }
}
