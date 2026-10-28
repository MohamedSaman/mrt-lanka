<?php

namespace App\Livewire\Staff;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Customer;
use App\Models\ProductDetail;
use App\Models\StaffProduct;
use App\Models\Quotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Livewire\Concerns\WithDynamicLayout;

#[Title('Create Quotation')]
class StaffQuotationSystem extends Component
{
    use WithDynamicLayout;

    // Basic Properties
    public $search = '';
    public $searchResults = [];
    public $customerId = '';
    public $validUntil;

    // Cart Items
    public $cart = [];

    // Customer Properties
    public $customers = [];
    public $selectedCustomer = null;

    // Customer Form (for new customer - only used in modal)
    public $customerName = '';
    public $customerPhone = '';
    public $customerEmail = '';
    public $customerAddress = '';
    public $customerType = 'retail';
    public $businessName = '';

    // Quotation Properties
    public $notes = '';
    public $termsConditions = "1. This quotation is valid for 30 days.\n2. Prices are subject to change.";

    // Discount Properties
    public $additionalDiscount = 0;
    public $additionalDiscountType = 'fixed'; // 'fixed' or 'percentage'


    // Modals
    public $showQuotationModal = false;
    public $showCustomerModal = false;
    public $lastQuotationId = null;
    public $createdQuotation = null;


    // Update Unit Price
    public function updateUnitPrice($index, $price)
    {
        $price = floatval($price);
        if ($price < 0) $price = 0;
        $this->cart[$index]['price'] = $price;
        // Recalculate total for this item
        $this->cart[$index]['total'] = ($price - $this->cart[$index]['discount']) * $this->cart[$index]['quantity'];
    }

    public function mount()
    {
        $this->validUntil = now()->addDays(30)->format('Y-m-d');
        $this->loadCustomers();
    }

    // Load customers for dropdown - filtered by staff's user_id
    public function loadCustomers()
    {
        $this->customers = Customer::where('user_id', Auth::id())
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
            // Calculate percentage discount from subtotal after item discounts
            return ($this->subtotalAfterItemDiscounts * $this->additionalDiscount) / 100;
        }

        // For fixed discount, ensure it doesn't exceed the subtotal
        return min($this->additionalDiscount, $this->subtotalAfterItemDiscounts);
    }

    public function getGrandTotalProperty()
    {
        return $this->subtotalAfterItemDiscounts - $this->additionalDiscountAmount;
    }

    // When customer is selected from dropdown
    public function updatedCustomerId($value)
    {
        if ($value) {
            $customer = Customer::find($value);
            if ($customer) {
                // Store selected customer data but don't populate form fields
                $this->selectedCustomer = $customer;
            }
        } else {
            // If customer is deselected, clear selection
            $this->selectedCustomer = null;
        }
    }

    // Reset customer fields
    public function resetCustomerFields()
    {
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerEmail = '';
        $this->customerAddress = '';
        $this->customerType = 'retail';
        $this->businessName = '';
    }

    // Open customer modal
    public function openCustomerModal()
    {
        $this->resetCustomerFields();
        $this->showCustomerModal = true;
    }

    // Close customer modal
    public function closeCustomerModal()
    {
        $this->showCustomerModal = false;
        $this->resetCustomerFields();
    }

    // Create new customer
    public function createCustomer()
    {
        $this->validate([
            'customerName' => 'required|string|max:255',
            'customerPhone' => 'nullable|string|max:20|unique:customers,phone',
            'customerEmail' => 'nullable|email|unique:customers,email',
            'customerAddress' => 'required|string',
            'customerType' => 'required|in:retail,wholesale,distributor',
        ]);

        try {
            $customer = Customer::create([
                'name' => $this->customerName,
                'phone' => $this->customerPhone ?: null,
                'email' => $this->customerEmail,
                'address' => $this->customerAddress,
                'type' => $this->customerType,
                'business_name' => $this->businessName,
                'user_id' => Auth::id(),
            ]);

            // Reload customers and select the new one
            $this->loadCustomers();
            $this->customerId = $customer->id;
            $this->selectedCustomer = $customer;
            $this->closeCustomerModal();
            $this->js("Swal.fire('Success!', 'Customer created successfully!', 'success');");
        } catch (\Exception $e) {
            $this->js("Swal.fire('Error!', 'Failed to create customer: " . addslashes($e->getMessage()) . "', 'error');");
        }
    }

    // Search Products - filtered by staff's allocated products
    public function updatedSearch()
    {
        if (strlen($this->search) >= 2) {
            // For staff: only show their allocated products
            $this->searchResults = \App\Models\StaffProduct::where('staff_id', auth()->id())
                ->join('product_details', 'staff_products.product_id', '=', 'product_details.id')
                ->where(function ($query) {
                    $query->where('product_details.name', 'like', '%' . $this->search . '%')
                        ->orWhere('product_details.code', 'like', '%' . $this->search . '%')
                        ->orWhere('product_details.model', 'like', '%' . $this->search . '%');
                })
                ->select(
                    'product_details.id',
                    'product_details.name',
                    'product_details.code',
                    'product_details.model',
                    'product_details.image',
                    'staff_products.unit_price as price',
                    'staff_products.quantity',
                    'staff_products.sold_quantity'
                )
                ->take(10)
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'code' => $product->code,
                        'model' => $product->model,
                        'price' => $product->price,
                        'stock' => ($product->quantity - $product->sold_quantity),
                        'image' => $product->image
                    ];
                });
        } else {
            $this->searchResults = [];
        }
    }

    // Add to Cart
    public function addToCart($product)
    {
        $existing = collect($this->cart)->firstWhere('id', $product['id']);

        if ($existing) {
            // Increase quantity if already in cart
            $this->cart = collect($this->cart)->map(function ($item) use ($product) {
                if ($item['id'] == $product['id']) {
                    $item['quantity'] += 1;
                    $item['total'] = ($item['price'] - $item['discount']) * $item['quantity'];
                }
                return $item;
            })->toArray();
        } else {
            // Add new item - use discount_price if available, otherwise 0
            $discountPrice = ProductDetail::find($product['id'])->price->discount_price ?? 0;

            $this->cart[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'code' => $product['code'],
                'model' => $product['model'],
                'price' => $product['price'], // Unit price from selling_price
                'quantity' => 1,
                'discount' => $discountPrice, // Pre-fill with discount_price from database
                'total' => $product['price'] - $discountPrice // Initial total with discount applied
            ];
        }

        $this->search = '';
        $this->searchResults = [];
    }

    // Update Quantity
    public function updateQuantity($index, $quantity)
    {
        if ($quantity < 1) $quantity = 1;

        $this->cart[$index]['quantity'] = $quantity;
        $this->cart[$index]['total'] = ($this->cart[$index]['price'] - $this->cart[$index]['discount']) * $quantity;
    }

    // Increment Quantity
    public function incrementQuantity($index)
    {
        $this->cart[$index]['quantity'] += 1;
        $this->cart[$index]['total'] = ($this->cart[$index]['price'] - $this->cart[$index]['discount']) * $this->cart[$index]['quantity'];
    }

    // Decrement Quantity
    public function decrementQuantity($index)
    {
        if ($this->cart[$index]['quantity'] > 1) {
            $this->cart[$index]['quantity'] -= 1;
            $this->cart[$index]['total'] = ($this->cart[$index]['price'] - $this->cart[$index]['discount']) * $this->cart[$index]['quantity'];
        }
    }

    // Update Discount (only discount is editable now)
    public function updateDiscount($index, $discount)
    {
        if ($discount < 0) $discount = 0;
        // Ensure discount doesn't exceed price
        if ($discount > $this->cart[$index]['price']) {
            $discount = $this->cart[$index]['price'];
        }

        $this->cart[$index]['discount'] = $discount;
        $this->cart[$index]['total'] = ($this->cart[$index]['price'] - $discount) * $this->cart[$index]['quantity'];
    }

    // Remove from Cart
    public function removeFromCart($index)
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart); // Reindex array
    }

    // Clear Cart
    public function clearCart()
    {
        $this->cart = [];
        $this->additionalDiscount = 0;
        $this->additionalDiscountType = 'fixed';
    }

    // Update additional discount with real-time validation
    public function updatedAdditionalDiscount($value)
    {
        // Convert empty string to 0
        if ($value === '') {
            $this->additionalDiscount = 0;
            return;
        }

        // Ensure it's a positive number
        if ($value < 0) {
            $this->additionalDiscount = 0;
            return;
        }

        // If percentage discount, ensure it doesn't exceed 100%
        if ($this->additionalDiscountType === 'percentage' && $value > 100) {
            $this->additionalDiscount = 100;
            return;
        }

        // For fixed discount, ensure it doesn't exceed the subtotal after item discounts
        if ($this->additionalDiscountType === 'fixed' && $value > $this->subtotalAfterItemDiscounts) {
            $this->additionalDiscount = $this->subtotalAfterItemDiscounts;
            return;
        }
    }

    // Update additional discount type with better handling
    public function updatedAdditionalDiscountType($type)
    {
        // Reset discount when type changes to avoid confusion
        $this->additionalDiscount = 0;
    }

    public function toggleDiscountType()
    {
        // Toggle between percentage and fixed
        $this->additionalDiscountType = $this->additionalDiscountType === 'percentage' ? 'fixed' : 'percentage';

        // Reset value after switch
        $this->additionalDiscount = 0;
    }

    // Apply percentage discount
    public function applyPercentageDiscount($percentage)
    {
        if ($percentage >= 0 && $percentage <= 100) {
            $this->additionalDiscountType = 'percentage';
            $this->additionalDiscount = $percentage;
        }
    }

    // Apply fixed discount
    public function applyFixedDiscount($amount)
    {
        if ($amount >= 0) {
            $this->additionalDiscountType = 'fixed';
            $this->additionalDiscount = min($amount, $this->subtotalAfterItemDiscounts);
        }
    }

    // Remove additional discount
    public function removeAdditionalDiscount()
    {
        $this->additionalDiscount = 0;
    }

    // Create Quotation
    public function createQuotation()
    {
        // Validate required fields
        if (empty($this->cart)) {
            $this->js("Swal.fire('Error!', 'Please add at least one product to the quotation.', 'error');");
            return;
        }

        // Validate customer is selected - check if customerId has a valid value
        if (empty($this->customerId) || $this->customerId === '') {
            $this->js("Swal.fire('Error!', 'Please select a customer for the quotation.', 'error');");
            return;
        }

        // Validate valid until date
        if (empty($this->validUntil)) {
            $this->js("Swal.fire('Error!', 'Please select a valid until date.', 'error');");
            return;
        }

        try {
            DB::beginTransaction();

            // Get customer data
            $customer = Customer::find($this->customerId);

            if (!$customer) {
                $this->js("Swal.fire('Error!', 'Customer not found.', 'error');");
                return;
            }

            // Prepare items for JSON storage
            $items = collect($this->cart)->map(function ($item, $index) {
                return [
                    'id' => $index + 1,
                    'product_id' => $item['id'],
                    'product_code' => $item['code'],
                    'product_name' => $item['name'],
                    'product_model' => $item['model'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'discount_per_unit' => $item['discount'],
                    'total_discount' => $item['discount'] * $item['quantity'],
                    'total' => $item['total']
                ];
            })->toArray();

            // Calculate total discount (item discounts + additional discount)
            $totalItemDiscount = $this->totalDiscount;
            $totalDiscount = $totalItemDiscount + $this->additionalDiscountAmount;

            // Map customer type to valid quotation customer types (retail or wholesale)
            $quotationCustomerType = match ($customer->type) {
                'distributor', 'wholesale' => 'wholesale',
                'retail' => 'retail',
                default => 'retail'
            };

            // Create quotation with created_by and user_id = Auth::id()
            $quotation = Quotation::create([
                'quotation_number' => Quotation::generateQuotationNumber(),
                'customer_id' => $customer->id,
                'customer_type' => $quotationCustomerType,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'customer_email' => $customer->email,
                'customer_address' => $customer->address,
                'quotation_date' => now(),
                'valid_until' => $this->validUntil,
                'subtotal' => $this->subtotal,
                'discount_amount' => $totalDiscount,
                'additional_discount' => $this->additionalDiscountAmount,
                'additional_discount_type' => $this->additionalDiscountType,
                'additional_discount_value' => $this->additionalDiscount,
                'tax_amount' => 0,
                'shipping_charges' => 0,
                'total_amount' => $this->grandTotal,
                'items' => $items,
                'terms_conditions' => $this->termsConditions,
                'notes' => $this->notes,
                'status' => 'draft',
                'created_by' => Auth::id(),
                'user_id' => Auth::id(),
            ]);

            $this->js("Swal.fire('Success!', 'Quotation created successfully!', 'success');");
            // NOTE: Quotations don't reduce stock - only sales do
            // Staff products sold_quantity is NOT updated for quotations

            DB::commit();

            // Store quotation data and show modal WITHOUT resetting the page
            $this->lastQuotationId = $quotation->id;
            $this->createdQuotation = $quotation;
            $this->showQuotationModal = true;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->js("Swal.fire('Error!', 'Failed to create quotation: " . addslashes($e->getMessage()) . "', 'error');");
        }
    }

    // Download Quotation
    public function downloadQuotation()
    {
        if (!$this->createdQuotation) {
            $this->js("Swal.fire('Error!', 'No quotation found to download.', 'error');");
            return;
        }

        try {
            $quotation = Quotation::find($this->createdQuotation->id);

            if (!$quotation) {
                $this->js("Swal.fire('Error!', 'Quotation not found.', 'error');");
                return;
            }

            $pdf = PDF::loadView('admin.quotations.print', compact('quotation'));
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('dpi', 150);
            $pdf->setOption('defaultFont', 'sans-serif');

            return response()->streamDownload(
                function () use ($pdf) {
                    echo $pdf->output();
                },
                'quotation-' . $quotation->quotation_number . '.pdf'
            );
        } catch (\Exception $e) {
            $this->js("Swal.fire('Error!', 'Failed to generate PDF: " . addslashes($e->getMessage()) . "', 'error');");
        }
    }

    // Print Quotation
    public function printQuotation()
    {
        if (!$this->createdQuotation) {
            $this->js("Swal.fire('Error!', 'No quotation found to print.', 'error');");
            return;
        }

        $quotation = Quotation::find($this->createdQuotation->id);

        if (!$quotation) {
            $this->js("Swal.fire('Error!', 'Quotation not found.', 'error');");
            return;
        }

        // Open print page in new window
        $printUrl = '/admin/print/quotation/' . $quotation->id;
        $this->js("window.open('$printUrl', '_blank', 'width=800,height=600');");
    }

    // Close Modal and reset all fields
    public function closeModal()
    {
        $this->showQuotationModal = false;
        $this->lastQuotationId = null;
        $this->createdQuotation = null;

        // Reset all fields when closing modal
        $this->reset([
            'cart',
            'search',
            'searchResults',
            'customerId',
            'notes',
            'termsConditions',
            'additionalDiscount',
            'additionalDiscountType'
        ]);

        // Reload defaults
        $this->validUntil = now()->addDays(30)->format('Y-m-d');
        $this->termsConditions = "1. This quotation is valid for 30 days.\n2. Prices are subject to change.";
        $this->loadCustomers();
    }

    // Continue creating new quotation (reset everything)
    public function createNewQuotation()
    {
        $this->resetExcept(['customers', 'validUntil']);
        $this->validUntil = now()->addDays(30)->format('Y-m-d');
        $this->showQuotationModal = false;
    }

    public function render()
    {
        return view('livewire.staff.staff-quotation-system', [
            'subtotal' => $this->subtotal,
            'totalDiscount' => $this->totalDiscount,
            'subtotalAfterItemDiscounts' => $this->subtotalAfterItemDiscounts,
            'additionalDiscountAmount' => $this->additionalDiscountAmount,
            'grandTotal' => $this->grandTotal
        ])->layout($this->layout);
    }
}
