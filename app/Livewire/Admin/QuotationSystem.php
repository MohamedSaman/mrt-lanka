<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Customer;
use App\Models\ProductDetail;
use App\Models\Quotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Livewire\Concerns\WithDynamicLayout;

#[Title('Create Quotation')]
class QuotationSystem extends Component
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

    // Price Type Selection
    public $priceType = 'wholesale'; // 'retail', 'wholesale', or 'distribute'

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
        $this->setDefaultCustomer();
    }

    // Set default walking customer
    public function setDefaultCustomer()
    {
        // Find or create walking customer (only one)
        $walkingCustomer = Customer::where('name', 'Walking Customer')->first();

        if (!$walkingCustomer) {
            $walkingCustomer = Customer::create([
                'name' => 'Walking Customer',
                'phone' => 'xxxxx', // Empty phone number
                'email' => null,
                'address' => 'xxxxx',
                'type' => 'retail',
                'business_name' => null,
            ]);

            $this->loadCustomers(); // Reload customers after creating new one
        }

        $this->customerId = $walkingCustomer->id;
        $this->selectedCustomer = $walkingCustomer;
    }

    // Load customers for dropdown
    public function loadCustomers()
    {
        $this->customers = Customer::orderBy('name')->get();
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
            // If customer is deselected, set back to walking customer
            $this->setDefaultCustomer();
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
            'customerType' => 'required|in:retail,wholesale,business',
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
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create customer: ' . $e->getMessage());
        }
    }

    // Helper method to get price based on price type
    public function getPriceValue($priceRecord)
    {
        return match ($this->priceType) {
            'retail' => $priceRecord->retail_price ?? 0,
            'wholesale' => $priceRecord->wholesale_price ?? 0,
            'distribute' => $priceRecord->distributor_price ?? 0,
            default => $priceRecord->wholesale_price ?? 0,
        };
    }

    // When price type changes
    public function updatedPriceType($value)
    {
        // Re-run search if there are existing results
        if (!empty($this->search)) {
            $this->updatedSearch();
        }
    }

    // Search Products
    public function updatedSearch()
    {
        if (strlen($this->search) >= 2) {
            if ($this->isStaff()) {
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
                // For admin: show all products with variants
                $products = ProductDetail::with(['stocks', 'prices', 'variant'])
                    ->where(function ($query) {
                        $query->where('name', 'like', '%' . $this->search . '%')
                            ->orWhere('code', 'like', '%' . $this->search . '%')
                            ->orWhere('model', 'like', '%' . $this->search . '%');
                    })
                    ->orWhereHas('stocks', function ($query) {
                        $query->where('variant_value', 'like', '%' . $this->search . '%');
                    })
                    ->take(10)
                    ->get();

                $results = [];
                foreach ($products as $product) {
                    // Check if product has variants
                    $hasVariants = $product->stocks()->whereNotNull('variant_value')->exists();

                    if ($hasVariants) {
                        // Show each variant as separate entry
                        $variantStocks = $product->stocks()->whereNotNull('variant_value')->get();
                        foreach ($variantStocks as $variantStock) {
                            // Get variant-specific price
                            $variantPrice = $product->prices()->where('variant_id', $variantStock->variant_id)->first();
                            $price = $variantPrice ? $this->getPriceValue($variantPrice) : 0;

                            $results[] = [
                                'id' => $product->id . '_variant_' . $variantStock->id,
                                'product_id' => $product->id,
                                'variant_id' => $variantStock->variant_id,
                                'variant_value' => $variantStock->variant_value,
                                'name' => $product->name . ' (' . $variantStock->variant_value . ')',
                                'code' => $product->code,
                                'model' => $product->model,
                                'price' => $price,
                                'stock' => $variantStock->available_stock ?? 0,
                                'image' => $product->image
                            ];
                        }
                    } else {
                        // Show product without variant
                        $productPrice = $product->prices()->whereNull('variant_id')->first();
                        $price = $productPrice ? $this->getPriceValue($productPrice) : 0;
                        $productStock = $product->stocks()->whereNull('variant_value')->first();

                        $results[] = [
                            'id' => $product->id,
                            'product_id' => $product->id,
                            'variant_id' => null,
                            'variant_value' => null,
                            'name' => $product->name,
                            'code' => $product->code,
                            'model' => $product->model,
                            'price' => $price,
                            'stock' => $productStock->available_stock ?? 0,
                            'image' => $product->image
                        ];
                    }
                }

                $this->searchResults = collect($results);
            }
        } else {
            $this->searchResults = [];
        }
    }

    // Add to Cart
    public function addToCart($product)
    {
        $cartId = $product['id'];
        $existing = collect($this->cart)->firstWhere('id', $cartId);

        if ($existing) {
            // Increase quantity if already in cart
            $this->cart = collect($this->cart)->map(function ($item) use ($cartId) {
                if ($item['id'] == $cartId) {
                    $item['quantity'] += 1;
                    $item['total'] = ($item['price'] - $item['discount']) * $item['quantity'];
                }
                return $item;
            })->toArray();
        } else {
            // Add new item - use discount_price if available, otherwise 0
            $productId = $product['product_id'] ?? $product['id'];
            $variantId = $product['variant_id'] ?? null;

            // Get discount price from prices table based on variant
            $priceRecord = \App\Models\ProductPrice::where('product_id', $productId)
                ->where(function ($q) use ($variantId) {
                    if ($variantId) {
                        $q->where('variant_id', $variantId);
                    } else {
                        $q->whereNull('variant_id');
                    }
                })
                ->first();

            $discountPrice = $priceRecord->discount_price ?? 0;

            // Add new item at the beginning of cart array (prepend)
            array_unshift($this->cart, [
                'id' => $cartId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'variant_value' => $product['variant_value'] ?? null,
                'name' => $product['name'],
                'code' => $product['code'],
                'model' => $product['model'] ?? '',
                'price' => $product['price'], // Unit price based on selected price type
                'quantity' => 1,
                'discount' => $discountPrice, // Pre-fill with discount_price from database
                'total' => $product['price'] - $discountPrice // Initial total with discount applied
            ]);
        }

        $this->search = '';
        $this->searchResults = [];

        // Dispatch event to focus the first cart item's quantity input
        $this->dispatch('product-added-to-cart');
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
            session()->flash('error', 'Please add at least one product to the quotation.');
            return;
        }

        // If no customer selected, use walking customer
        if (!$this->selectedCustomer && !$this->customerId) {
            $this->setDefaultCustomer();
        }

        try {
            DB::beginTransaction();

            // Get customer data
            if ($this->selectedCustomer) {
                $customer = $this->selectedCustomer;
            } else {
                $customer = Customer::find($this->customerId);
            }

            if (!$customer) {
                session()->flash('error', 'Customer not found.');
                return;
            }

            // Prepare items for JSON storage
            $items = collect($this->cart)->map(function ($item, $index) {
                return [
                    'id' => $index + 1,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'variant_value' => $item['variant_value'] ?? null,
                    'product_code' => $item['code'],
                    'product_name' => $item['name'],
                    'product_model' => $item['model'] ?? '',
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'discount_per_unit' => $item['discount'],
                    'total_discount' => $item['discount'] * $item['quantity'],
                    'total' => $item['total']
                ];
            })->toArray();

            // ✅ FIX: Calculate total discount (item discounts + additional discount)
            $totalItemDiscount = $this->totalDiscount;
            $totalDiscount = $totalItemDiscount + $this->additionalDiscountAmount;

            // Map customer type to valid quotation customer types (retail or wholesale)
            $quotationCustomerType = match ($customer->type) {
                'distributor', 'wholesale' => 'wholesale',
                'retail' => 'retail',
                default => 'retail'
            };

            // Create quotation
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
                // created_by is set automatically in the Quotation model's booted method
            ]);

            DB::commit();

            // Store quotation data and show modal WITHOUT resetting the page
            $this->lastQuotationId = $quotation->id;
            $this->createdQuotation = $quotation;
            $this->showQuotationModal = true;
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Failed to create quotation: ' . $e->getMessage());
        }
    }

    // Download Quotation
    public function downloadQuotation()
    {
        if (!$this->lastQuotationId) {
            session()->flash('error', 'No quotation found to download.');
            return;
        }

        $quotation = Quotation::find($this->lastQuotationId);

        if (!$quotation) {
            session()->flash('error', 'Quotation not found.');
            return;
        }

        $pdf = PDF::loadView('admin.quotations.print', compact('quotation'));

        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf->output();
            },
            'quotation-' . $quotation->quotation_number . '.pdf'
        );
    }

    // Print Quotation
    public function printQuotation()
    {
        if (!$this->lastQuotationId) {
            session()->flash('error', 'No quotation found to print.');
            return;
        }

        $quotation = Quotation::find($this->lastQuotationId);

        if (!$quotation) {
            session()->flash('error', 'Quotation not found.');
            return;
        }

        $pdf = PDF::loadView('admin.quotations.print', compact('quotation'));

        return $pdf->download('quotation-' . $quotation->quotation_number . '.pdf');
    }

    // Close Modal and reset only necessary fields
    public function closeModal()
    {

        $this->showQuotationModal = false;
        $this->lastQuotationId = null;
        $this->createdQuotation = null;
    }

    // Continue creating new quotation (reset everything)
    public function createNewQuotation()
    {
        $this->resetExcept(['customers', 'validUntil']);
        $this->validUntil = now()->addDays(30)->format('Y-m-d');
        $this->setDefaultCustomer(); // Set walking customer again for new quotation
        $this->showQuotationModal = false;
    }

    public function render()
    {
        return view('livewire.admin.quotation-system', [
            'subtotal' => $this->subtotal,
            'totalDiscount' => $this->totalDiscount,
            'subtotalAfterItemDiscounts' => $this->subtotalAfterItemDiscounts,
            'additionalDiscountAmount' => $this->additionalDiscountAmount,
            'grandTotal' => $this->grandTotal
        ])->layout($this->layout);
    }
}
