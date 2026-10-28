<?php

namespace App\Livewire\Salesman;

use App\Models\Customer;
use App\Models\ProductDetail;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ProductStock;
use App\Models\ProductBatch;
use App\Services\StockAvailabilityService;
use App\Services\FIFOStockService;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Title('Create Sales Order')]
#[Layout('components.layouts.salesman')]
class SalesmanBilling extends Component
{
    // Search and Products
    public $search = '';
    public $searchResults = [];

    // Cart
    public $cart = [];

    // Customer
    public $customers = [];
    public $customerId = '';
    public $selectedCustomer = null;
    public $customerSearch = '';
    public $customerSearchResults = [];

    // Customer Form
    public $showCustomerModal = false;
    public $customerName = '';
    public $customerPhone = '';
    public $customerEmail = '';
    public $customerAddress = '';
    public $customerType = 'distributor';
    public $businessName = '';
    public $customerOpeningBalance = 0;
    public $customerOverpaidAmount = 0;
    public $showCustomerMoreInfo = false;

    // Sale Details
    public $notes = '';
    public $additionalDiscount = 0;
    public $additionalDiscountType = 'fixed';

    // Modal states
    public $showSaleModal = false;
    public $createdSale = null;

    // Edit Mode
    public $editMode = false;
    public $editingSaleId = null;
    public $editingSale = null;

    // Stock Service
    protected StockAvailabilityService $stockService;

    public function boot(StockAvailabilityService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function mount($saleId = null)
    {
        $this->loadCustomers();

        // Load existing sale for editing if saleId provided
        if ($saleId) {
            $this->loadSaleForEditing($saleId);
        }
    }

    public function loadCustomers()
    {
        if (strlen($this->customerSearch) >= 1) {
            $this->customerSearchResults = Customer::where('type', 'distributor')
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->customerSearch . '%')
                        ->orWhere('business_name', 'like', '%' . $this->customerSearch . '%')
                        ->orWhere('phone', 'like', '%' . $this->customerSearch . '%');
                })
                ->orderBy('business_name')
                ->limit(10)
                ->get();
        } else {
            $this->customerSearchResults = [];
        }

        $this->customers = Customer::where('type', 'distributor')->orderBy('business_name')->get();
    }

    public function updatedCustomerSearch()
    {
        $this->loadCustomers();
    }

    public function selectCustomer($id)
    {
        $this->customerId = $id;
        $this->selectedCustomer = Customer::find($id);
        $this->customerSearch = '';
        $this->customerSearchResults = [];
        $this->dispatch('customer-selected');
    }

    /**
     * Load existing sale for editing
     */
    public function loadSaleForEditing($saleId)
    {
        try {
            $sale = Sale::with(['items', 'customer'])->findOrFail($saleId);

            // Only allow editing own sales
            if ($sale->user_id !== Auth::id()) {
                session()->flash('error', 'You can only edit your own sales');
                return;
            }

            // Allow editing of pending and approved sales
            if (!in_array($sale->status, ['pending', 'confirm'])) {
                session()->flash('error', 'Delivered or rejected sales cannot be edited. Only pending and approved sales can be modified.');
                return;
            }

            $this->editMode = true;
            $this->editingSaleId = $sale->id;
            $this->editingSale = $sale;
            $this->customerId = $sale->customer_id;
            $this->selectedCustomer = $sale->customer;
            $this->notes = $sale->notes ?? '';

            // Load discount and discount type
            $this->additionalDiscountType = $sale->discount_type ?? 'fixed';
            if ($this->additionalDiscountType === 'percentage') {
                // For percentage type, discount_amount stores the percentage value
                $this->additionalDiscount = $sale->discount_amount ?? 0;
            } else {
                // For fixed type, discount_amount stores the rupee amount
                $this->additionalDiscount = $sale->discount_amount ?? 0;
            }

            // Load cart items from sale items with actual stock
            $this->cart = [];
            foreach ($sale->items as $item) {
                $cartKey = $item->product_id . ($item->variant_value ? '_' . $item->variant_value : '');

                // Calculate actual available stock for this product
                $actualAvailable = $this->getActualAvailableForEdit(
                    $item->product_id,
                    $item->variant_id ?? null,
                    $item->variant_value ?? null
                );

                // Use product_name as stored — it already contains the full display name
                // (e.g., "Screw (Size: 5")") set when the product was first added to cart.
                // Strip any duplicate trailing " (variant_value)" suffixes that may have been
                // appended in a previous buggy edit cycle, to repair already-corrupted records.
                $displayName = $item->product_name;
                if ($item->variant_value && $item->variant_value !== '' && $item->variant_value !== 'null') {
                    $variantSuffix = ' (' . $item->variant_value . ')';
                    // Remove all duplicate trailing suffixes caused by previous edit cycles
                    while (str_ends_with($displayName, $variantSuffix)) {
                        $displayName = substr($displayName, 0, -strlen($variantSuffix));
                    }
                }

                $this->cart[] = [
                    'cart_key' => $cartKey,
                    'id' => $item->product_id,
                    'variant_id' => $item->variant_id ?? null,
                    'variant_value' => $item->variant_value ?? null,
                    'name' => $displayName,
                    'code' => $item->product_code,
                    'price' => $item->unit_price,
                    'distributor_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'discount' => $item->discount_per_unit,
                    'total' => $item->total,
                    'available' => $actualAvailable,
                    'image' => '',
                    'is_variant' => $item->variant_id ? true : false,
                    'original_qty' => $item->quantity, // Track original qty for stock validation
                ];
            }

            session()->flash('message', 'Sale loaded for editing');
        } catch (\Exception $e) {
            Log::error('Failed to load sale for editing: ' . $e->getMessage());
            session()->flash('error', 'Failed to load sale: ' . $e->getMessage());
        }
    }

    /**
     * Calculate available stock considering pending sales
     * Actual stock - Pending order quantities = Available to sell
     * When editing, excludes the current sale's quantities from pending calculation
     */
    private function getAvailableStock($productId, $variantValue = null)
    {
        $stockInfo = $this->stockService->getAvailableStock($productId);
        $actualAvailable = $stockInfo['available'] ?? 0;

        // Get pending quantity from pending sales for this product
        $pendingQuery = SaleItem::whereHas('sale', function ($q) {
            $q->where('status', 'pending');
        })->where('product_id', $productId);

        if ($variantValue) {
            $pendingQuery->where('variant_value', $variantValue);
        }

        // Exclude current editing sale's items from pending count
        if ($this->editMode && $this->editingSaleId) {
            $pendingQuery->where('sale_id', '!=', $this->editingSaleId);
        }

        $pendingQuantity = $pendingQuery->sum('quantity');

        // Return: actual stock minus pending orders (other sales)
        return max(0, $actualAvailable - $pendingQuantity);
    }

    /**
     * Get actual available stock for edit mode
     * Returns actual stock - other pending sales (excludes current sale)
     */
    private function getActualAvailableForEdit($productId, $variantId = null, $variantValue = null)
    {
        // Get raw stock from ProductStock
        $stockQuery = ProductStock::where('product_id', $productId);

        if ($variantValue) {
            $stockQuery->where('variant_value', $variantValue);
        } elseif ($variantId) {
            $stockQuery->where('variant_id', $variantId);
        } else {
            $stockQuery->where(function ($q) {
                $q->whereNull('variant_value')
                    ->orWhere('variant_value', '')
                    ->orWhere('variant_value', 'null');
            })->whereNull('variant_id');
        }

        $stockRecord = $stockQuery->first();
        $rawAvailable = $stockRecord ? ($stockRecord->available_stock ?? 0) : 0;

        // If the sale is confirmed, add back the quantity of this product/variant from the current sale
        if ($this->editMode && $this->editingSale && $this->editingSale->status === 'confirm') {
            $currentQty = SaleItem::where('sale_id', $this->editingSaleId)
                ->where('product_id', $productId)
                ->when($variantValue, function ($q) use ($variantValue) {
                    return $q->where('variant_value', $variantValue);
                }, function ($q) {
                    return $q->where(function ($sq) {
                        $sq->whereNull('variant_value')
                            ->orWhere('variant_value', '')
                            ->orWhere('variant_value', 'null');
                    })->whereNull('variant_id');
                })
                ->sum('quantity');
            $rawAvailable += $currentQty;
        }

        // Subtract pending quantities from OTHER sales only
        $otherPendingQuery = SaleItem::whereHas('sale', function ($q) {
            $q->where('status', 'pending');
        })->where('product_id', $productId);

        if ($variantValue) {
            $otherPendingQuery->where('variant_value', $variantValue);
        }

        if ($this->editingSaleId) {
            $otherPendingQuery->where('sale_id', '!=', $this->editingSaleId);
        }

        $otherPending = $otherPendingQuery->sum('quantity');

        return max(0, $rawAvailable - $otherPending);
    }

    public function updatedSearch()
    {
        if (strlen($this->search) >= 2) {
            // Split search term by space to check for combined searches (e.g., "weel 1/16")
            $searchParts = array_filter(array_map('trim', explode(' ', $this->search)));

            $productsByNameCode = ProductDetail::with(['stock', 'price', 'prices', 'stocks', 'variant'])
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('code', 'like', '%' . $this->search . '%');
                })
                ->limit(50)
                ->get();

            // Also get all products with variants to search by variant values
            $variantProducts = ProductDetail::with(['stock', 'price', 'prices', 'stocks', 'variant'])
                ->whereHas('variant')
                ->limit(100)
                ->get()
                ->filter(function ($product) {
                    // Check if any variant value contains the search term
                    if ($product->variant && is_array($product->variant->variant_values)) {
                        return collect($product->variant->variant_values)->some(function ($value) {
                            return stripos($value, $this->search) !== false;
                        });
                    }
                    return false;
                });

            // If search contains multiple parts, also search for combined product name + variant
            $combinedProducts = collect();
            if (count($searchParts) > 1) {
                $combinedProducts = ProductDetail::with(['stock', 'price', 'prices', 'stocks', 'variant'])
                    ->whereHas('variant')
                    ->limit(100)
                    ->get()
                    ->filter(function ($product) use ($searchParts) {
                        // Check if product name/code matches one part AND variant matches another part
                        $nameMatches = false;
                        $variantMatches = false;

                        foreach ($searchParts as $part) {
                            if (stripos($product->name, $part) !== false || stripos($product->code, $part) !== false) {
                                $nameMatches = true;
                            }
                        }

                        if ($product->variant && is_array($product->variant->variant_values)) {
                            foreach ($searchParts as $part) {
                                if (collect($product->variant->variant_values)->some(function ($value) use ($part) {
                                    return stripos($value, $part) !== false;
                                })) {
                                    $variantMatches = true;
                                    break;
                                }
                            }
                        }

                        // Return true only if both product AND variant parts match
                        return $nameMatches && $variantMatches;
                    });
            }

            // Merge all result sets and deduplicate by product ID
            $products = $productsByNameCode->merge($variantProducts)->merge($combinedProducts)->unique('id')->values();

            $this->searchResults = [];

            foreach ($products as $product) {
                if ($product->hasVariants() && $product->variant) {
                    // Product has variants - show each variant as a separate item
                    $variantPrices = $product->prices()->whereNotNull('variant_value')->get();
                    $variantStocks = $product->stocks()->whereNotNull('variant_value')->get();

                    foreach ($product->variant->variant_values as $variantValue) {
                        // Check if variant value matches search term or product name matches
                        $variantMatches = stripos($variantValue, $this->search) !== false;
                        $productMatches = stripos($product->name, $this->search) !== false || stripos($product->code, $this->search) !== false;

                        // For combined searches, be strict: only show variant if BOTH product AND variant match
                        if (count($searchParts) > 1) {
                            $productMatchesAnyPart = false;
                            $variantMatchesAnyPart = false;

                            foreach ($searchParts as $part) {
                                if (stripos($product->name, $part) !== false || stripos($product->code, $part) !== false) {
                                    $productMatchesAnyPart = true;
                                }
                                if (stripos($variantValue, $part) !== false) {
                                    $variantMatchesAnyPart = true;
                                }
                            }

                            // For multi-part search, BOTH must match
                            $shouldShow = $productMatchesAnyPart && $variantMatchesAnyPart;
                        } else {
                            // For single search term, show if variant OR product matches
                            $shouldShow = $variantMatches || $productMatches;
                        }

                        if ($shouldShow) {
                            $variantPrice = $variantPrices->where('variant_value', $variantValue)->first();
                            $variantStock = $variantStocks->where('variant_value', $variantValue)->first();

                            if ($variantPrice && $variantStock) {
                                // Get pending quantity for this specific variant
                                $editingSaleId = $this->editingSaleId;
                                $pendingQuantity = SaleItem::whereHas('sale', function ($q) use ($editingSaleId) {
                                    $q->where('status', 'pending');
                                    // Exclude current editing sale from pending count
                                    if ($editingSaleId) {
                                        $q->where('id', '!=', $editingSaleId);
                                    }
                                })
                                    ->where('product_id', $product->id)
                                    ->where('variant_value', $variantValue)
                                    ->sum('quantity');

                                $availableStock = max(0, ($variantStock->available_stock ?? 0) - $pendingQuantity);

                                if ($this->editMode && $this->editingSale && $this->editingSale->status === 'confirm') {
                                    $currentQty = SaleItem::where('sale_id', $this->editingSaleId)
                                        ->where('product_id', $product->id)
                                        ->where('variant_value', $variantValue)
                                        ->sum('quantity');
                                    $availableStock += $currentQty;
                                }

                                if ($availableStock > 0) {
                                    $price = $variantPrice->distributor_price ?? $variantPrice->wholesale_price ?? 0;
                                    $this->searchResults[] = [
                                        'id' => $product->id . '_' . $variantValue,
                                        'product_id' => $product->id,
                                        'variant_id' => $product->variant_id,
                                        'variant_value' => $variantValue,
                                        'name' => $product->name,
                                        'code' => $product->code,
                                        'display_name' => $product->name . ' (' . $product->variant->variant_name . ': ' . $variantValue . ')',
                                        'price' => $price,
                                        'distributor_price' => $price,
                                        'stock' => $variantStock->total_stock ?? 0,
                                        'available' => $availableStock,
                                        'pending' => $pendingQuantity,
                                        'image' => $product->image ?? '',
                                        'is_variant' => true,
                                    ];
                                }
                            }
                        }
                    }
                } else {
                    // Single product (no variants) - check both old and new pricing/stock structure
                    // First try new structure (prices/stocks tables)
                    $productPrice = $product->prices()->where('pricing_mode', 'single')->first();
                    $productStock = $product->stocks()->whereNull('variant_value')->first();

                    // Fallback to old structure if new structure doesn't exist
                    if (!$productPrice && $product->price) {
                        $productPrice = $product->price; // Old singular relationship
                    }

                    if (!$productStock && $product->stock) {
                        $productStock = $product->stock; // Old singular relationship
                    }

                    // Only proceed if product has price and stock
                    if ($productPrice && $productStock) {
                        $totalStock = $productStock->total_stock ?? 0;
                        $availableStockRaw = $productStock->available_stock ?? 0;

                        // Get pending quantity for this product (all variants combined if any)
                        $editingSaleId = $this->editingSaleId;
                        $pendingQuantity = SaleItem::whereHas('sale', function ($q) use ($editingSaleId) {
                            $q->where('status', 'pending');
                            // Exclude current editing sale from pending count
                            if ($editingSaleId) {
                                $q->where('id', '!=', $editingSaleId);
                            }
                        })
                            ->where('product_id', $product->id)
                            ->sum('quantity');

                        $availableStock = max(0, $availableStockRaw - $pendingQuantity);

                        if ($this->editMode && $this->editingSale && $this->editingSale->status === 'confirm') {
                            $currentQty = SaleItem::where('sale_id', $this->editingSaleId)
                                ->where('product_id', $product->id)
                                ->where(function ($sq) {
                                    $sq->whereNull('variant_value')
                                        ->orWhere('variant_value', '')
                                        ->orWhere('variant_value', 'null');
                                })
                                ->sum('quantity');
                            $availableStock += $currentQty;
                        }

                        // Add to results if there's available stock
                        if ($availableStock > 0) {
                            $price = $productPrice->distributor_price ?? $productPrice->wholesale_price ?? 0;
                            $this->searchResults[] = [
                                'id' => $product->id,
                                'product_id' => $product->id,
                                'variant_id' => null,
                                'variant_value' => null,
                                'name' => $product->name,
                                'code' => $product->code,
                                'display_name' => $product->name,
                                'price' => $price,
                                'distributor_price' => $price,
                                'stock' => $totalStock,
                                'available' => $availableStock,
                                'pending' => $pendingQuantity,
                                'image' => $product->image ?? '',
                                'is_variant' => false,
                            ];
                        }
                    }
                }
            }
        } else {
            $this->searchResults = [];
        }
    }

    public function updatedCustomerId($value)
    {
        if ($value) {
            $this->selectedCustomer = Customer::find($value);
        } else {
            $this->selectedCustomer = null;
        }
    }

    // Customer Management
    public function openCustomerModal()
    {
        $this->resetCustomerForm();
        $this->showCustomerModal = true;
    }

    public function closeCustomerModal()
    {
        $this->showCustomerModal = false;
        $this->resetCustomerForm();
    }

    public function resetCustomerForm()
    {
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerEmail = '';
        $this->customerAddress = '';
        $this->customerType = 'distributor';
        $this->businessName = '';
        $this->customerOpeningBalance = 0;
        $this->customerOverpaidAmount = 0;
        $this->showCustomerMoreInfo = false;
    }

    public function createCustomer()
    {
        $this->validate([
            'customerName' => 'required|string|max:255',
            'customerPhone' => 'required|string|max:20|regex:/^[0-9\s,\/\-\+]+$/',
            'customerEmail' => 'nullable|email|max:255',
            'customerAddress' => 'required|string|max:500',
            'customerType' => 'required|in:distributor',
            'customerOpeningBalance' => 'nullable|numeric|min:0',
            'customerOverpaidAmount' => 'nullable|numeric|min:0',
        ], [
            'customerPhone.regex' => 'Phone number can only contain numbers, spaces, and separators (-, /, +, ,)',
        ]);

        try {
            $openingBalance = $this->customerOpeningBalance ?? 0;
            $totalDue = $openingBalance;

            $customer = Customer::create([
                'name' => $this->customerName,
                'phone' => $this->customerPhone,
                'email' => $this->customerEmail,
                'address' => $this->customerAddress,
                'type' => $this->customerType,
                'business_name' => $this->businessName,
                'opening_balance' => $openingBalance,
                'overpaid_amount' => $this->customerOverpaidAmount ?? 0,
                'total_due' => $totalDue,
                'created_by' => Auth::id(),
                'user_id' => Auth::id(),
            ]);

            $this->customerId = $customer->id;
            $this->selectedCustomer = $customer;
            $this->loadCustomers();
            $this->closeCustomerModal();

            session()->flash('success', 'Customer created successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create customer: ' . $e->getMessage());
        }
    }

    public function addToCart($product)
    {
        // Check available stock
        if (($product['available'] ?? 0) <= 0) {
            session()->flash('error', 'Not enough stock available!');
            return;
        }

        // Extract actual numeric product ID (handles composite IDs like "393_1.5M" or "393")
        $rawId = $product['product_id'] ?? $product['id'];
        $productId = is_numeric($rawId) ? (int)$rawId : (int)preg_replace('/[^0-9].*$/', '', (string)$rawId);

        $variantId = $product['variant_id'] ?? null;
        $variantValue = $product['variant_value'] ?? null;

        // Always use the latest selling price passed via $product['price']
        $priceToUse = $product['price'];

        $cartKey = $productId . ($variantValue ? '_' . $variantValue : '');

        // Check if this item already exists in cart
        $existingIndex = null;
        foreach ($this->cart as $index => $item) {
            if ($item['cart_key'] === $cartKey) {
                $existingIndex = $index;
                break;
            }
        }

        if ($existingIndex !== null) {
            if (($this->cart[$existingIndex]['quantity'] + 1) > $product['available']) {
                session()->flash('error', 'Not enough available stock!');
                return;
            }
            
            $item = $this->cart[$existingIndex];
            $item['quantity'] += 1;
            $item['total'] = ($item['price'] - $item['discount']) * $item['quantity'];
            
            // Remove from current position and move to top
            unset($this->cart[$existingIndex]);
            $this->cart = array_values($this->cart); // Re-index
            array_unshift($this->cart, $item);
        } else {
            array_unshift($this->cart, [
                'cart_key' => $cartKey,
                'id' => $productId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'variant_value' => $variantValue,
                'name' => $product['display_name'] ?? $product['name'],
                'code' => $product['code'] ?? '',
                'price' => $priceToUse,
                'distributor_price' => $priceToUse,
                'quantity' => 1,
                'discount' => 0,
                'total' => $priceToUse,
                'available' => $product['available'],
                'image' => $product['image'] ?? '',
                'is_variant' => $product['is_variant'] ?? false,
            ]);
        }

        $this->search = '';
        $this->searchResults = [];
        $this->customerSearch = '';

        // Dispatch event so Alpine.js can focus the qty input of the added/updated item
        $this->dispatch('product-added-to-cart', cartKey: $cartKey);
    }

    public function updateQuantity($cartKey, $quantity)
    {
        $quantity = (int)$quantity;

        // Find item by cart_key
        $index = null;
        foreach ($this->cart as $i => $item) {
            if ($item['cart_key'] === $cartKey) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return;
        }

        if ($quantity <= 0) {
            $this->removeFromCart($cartKey);
            return;
        }

        $item = $this->cart[$index];

        // Stock validation: check if quantity exceeds available stock
        $availableStock = $item['available'];
        if ($quantity > $availableStock) {
            session()->flash('error', 'Exceeds available stock! Maximum: ' . $availableStock);
            return;
        }

        $this->cart[$index]['quantity'] = $quantity;
        $this->cart[$index]['total'] = ($this->cart[$index]['price'] - $this->cart[$index]['discount']) * $quantity;
    }

    public function updatePrice($cartKey, $price)
    {
        $price = (float)$price;

        // Find item by cart_key
        $index = null;
        foreach ($this->cart as $i => $item) {
            if ($item['cart_key'] === $cartKey) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return;
        }

        // Ensure price is not negative
        $price = max(0, $price);
        $this->cart[$index]['price'] = $price;

        // Recalculate total with the new price
        $this->cart[$index]['total'] = ($price - $this->cart[$index]['discount']) * $this->cart[$index]['quantity'];
    }

    public function updateDiscount($cartKey, $discount)
    {
        // Find item by cart_key
        $index = null;
        foreach ($this->cart as $i => $item) {
            if ($item['cart_key'] === $cartKey) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return;
        }

        $discount = max(0, min($discount, $this->cart[$index]['price']));
        $this->cart[$index]['discount'] = $discount;
        $this->cart[$index]['total'] = ($this->cart[$index]['price'] - $discount) * $this->cart[$index]['quantity'];
    }

    public function removeFromCart($cartKey)
    {
        // Find and remove item by cart_key
        foreach ($this->cart as $index => $item) {
            if ($item['cart_key'] === $cartKey) {
                unset($this->cart[$index]);
                break;
            }
        }
        // Re-index array to maintain order
        $this->cart = array_values($this->cart);
    }

    public function clearCart()
    {
        $this->cart = [];
        $this->additionalDiscount = 0;
        $this->notes = '';
    }

    public function createSale()
    {
        if (!$this->customerId) {
            session()->flash('error', 'Please select a customer');
            return;
        }

        if (empty($this->cart)) {
            session()->flash('error', 'Please add items to cart');
            return;
        }

        try {
            DB::beginTransaction();

            if ($this->editMode && $this->editingSaleId) {
                // Update existing sale
                $sale = Sale::findOrFail($this->editingSaleId);

                // Store old due amount for customer update
                $oldDueAmount = $sale->due_amount;

                // Only restore stock if sale was previously approved (status = 'confirm')
                // Pending sales never had stock deducted, so no need to restore
                if ($sale->status === 'confirm') {
                    // Restore previous stock quantities (both ProductStock and ProductBatch)
                    $previousItems = SaleItem::where('sale_id', $sale->id)->get();
                    foreach ($previousItems as $prevItem) {
                        $baseProductId = $prevItem->product_id;
                        $variantValue = $prevItem->variant_value ?? null;
                        $variantId = $prevItem->variant_id ?? null;
                        $quantity = $prevItem->quantity;

                        try {
                            // Use FIFO service to restore stock (reverse deduction)
                            // Note: We restore by adding back to the most recent batch
                            $batchQuery = ProductBatch::where('product_id', $baseProductId)
                                ->whereIn('status', ['active', 'depleted']);

                            if ($variantId) {
                                    $batchQuery->where('variant_id', $variantId);
                            }
                            if ($variantValue) {
                                    $batchQuery->where('variant_value', $variantValue);
                            }

                            $batch = $batchQuery->orderBy('received_date', 'desc')
                                ->orderBy('id', 'desc')
                                ->first();

                            if ($batch) {
                                $batch->remaining_quantity += $quantity;
                                $batch->quantity += $quantity;
                                if ($batch->status === 'depleted') {
                                    $batch->status = 'active';
                                }
                                $batch->save();
                            }

                            // Restore ProductStock
                            if ($variantValue || $variantId) {
                                $stockRecord = ProductStock::where('product_id', $baseProductId)
                                    ->when($variantId, function ($q) use ($variantId) {
                                        return $q->where('variant_id', $variantId);
                                    })
                                    ->when($variantValue, function ($q) use ($variantValue) {
                                        return $q->where('variant_value', $variantValue);
                                    })
                                    ->first();

                                if ($stockRecord) {
                                    $stockRecord->available_stock += $quantity;
                                    $stockRecord->updateTotals();
                                }
                            } else {
                                $product = ProductDetail::find($baseProductId);
                                if ($product && $product->stock) {
                                    $product->stock->available_stock += $quantity;
                                    $product->stock->updateTotals();
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('Stock restoration failed for product: ' . $baseProductId, [
                                'error' => $e->getMessage(),
                                'quantity' => $quantity,
                            ]);
                        }
                    }
                }

                // Calculate discount to store properly for edit mode
                $discountToStoreEdit = 0;
                if ($this->additionalDiscountType === 'percentage') {
                    // Store the percentage value
                    $discountToStoreEdit = max(0, (float) ($this->additionalDiscount ?? 0));
                } else {
                    // Store the rupee amount
                    $discountToStoreEdit = max(0, (float) ($this->additionalDiscountAmount ?? 0));
                }

                $newDueAmount = $this->grandTotal;

                // Update customer due_amount: subtract old due, add new due
                $customer = Customer::find($this->customerId);
                if ($customer) {
                    // Only subtract old due if it was actually added (>0)
                    if ($oldDueAmount > 0) {
                        $customer->due_amount = max(0, ($customer->due_amount ?? 0) - $oldDueAmount);
                    }

                    // Apply customer's overpaid amount to reduce the new due
                    $overpaidUsed = 0;
                    if ($newDueAmount > 0 && ($customer->overpaid_amount ?? 0) > 0) {
                        $overpaidUsed = min($customer->overpaid_amount, $newDueAmount);
                        $newDueAmount = $newDueAmount - $overpaidUsed;
                        $customer->overpaid_amount = max(0, $customer->overpaid_amount - $overpaidUsed);

                        Log::info('Overpaid amount applied on edit', [
                            'customer_id' => $customer->id,
                            'overpaid_used' => $overpaidUsed,
                            'remaining_due' => $newDueAmount,
                            'remaining_overpaid' => $customer->overpaid_amount,
                        ]);
                    }
                }

                $sale->update([
                    'customer_id' => $this->customerId,
                    'subtotal' => $this->subtotal,
                    'discount_amount' => $discountToStoreEdit,
                    'discount_type' => $this->additionalDiscountType,
                    'total_amount' => $this->grandTotal,
                    'due_amount' => $newDueAmount,
                    'payment_status' => $newDueAmount <= 0 ? 'paid' : ($overpaidUsed > 0 ? 'partial' : 'pending'),
                    'customer_type' => $this->selectedCustomer->type ?? 'distributor',
                    'notes' => $this->notes,
                ]);

                // Delete existing items
                SaleItem::where('sale_id', $sale->id)->delete();

                // Create new Sale Items and deduct stock only if sale was already confirmed
                foreach ($this->cart as $item) {
                    $rawId = $item['product_id'] ?? $item['id'];
                    $baseProductId = is_numeric($rawId) ? (int)$rawId : (int)preg_replace('/[^0-9].*$/', '', (string)$rawId);
                    $variantId = $item['variant_id'] ?? null;
                    $variantValue = $item['variant_value'] ?? null;
                    $quantity = $item['quantity'];

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $baseProductId,
                        'product_code' => $item['code'],
                        'product_name' => $item['name'],
                        'quantity' => $quantity,
                        'unit_price' => $item['price'],
                        'discount_per_unit' => $item['discount'],
                        'total_discount' => $item['discount'] * $quantity,
                        'total' => $item['total'],
                        'variant_id' => $variantId,
                        'variant_value' => $variantValue,
                    ]);

                    // Deduct stock using FIFO method only if sale is confirmed
                    // Pending sales will have stock deducted when admin approves
                    if ($sale->status === 'confirm') {
                        try {
                            FIFOStockService::deductStock($baseProductId, $quantity, $variantId, $variantValue);
                        } catch (\Exception $e) {
                            Log::error('Stock deduction failed for product: ' . $baseProductId, [
                                'error' => $e->getMessage(),
                                'quantity' => $quantity,
                                'variant_id' => $variantId,
                                'variant_value' => $variantValue,
                            ]);
                            throw new \Exception('Failed to deduct stock: ' . $e->getMessage());
                        }
                    }
                }

                // Sync customer balances accurately
                \App\Services\CustomerBalanceService::syncCustomerBalance($this->customerId);

                DB::commit();
                $this->createdSale = $sale->load(['customer', 'items.product']);
                $this->showSaleModal = true;
                session()->flash('success', 'Sale order updated successfully!');
            } else {
                // Create new sale
                $saleId = Sale::generateSaleId();
                $invoiceNumber = Sale::generateInvoiceNumber();

                // Create Sale (status pending means awaiting admin approval)
                // Store discount amount or percentage based on discount type
                $discountToStore = 0;
                if ($this->additionalDiscountType === 'percentage') {
                    // Store the percentage value
                    $discountToStore = max(0, (float) ($this->additionalDiscount ?? 0));
                } else {
                    // Store the rupee amount
                    $discountToStore = max(0, (float) ($this->additionalDiscountAmount ?? 0));
                }

                $sale = Sale::create([
                    'sale_id' => $saleId,
                    'invoice_number' => $invoiceNumber,
                    'customer_id' => $this->customerId,
                    'sale_type' => 'staff',
                    'user_id' => Auth::id(),
                    'customer_type' => $this->selectedCustomer->type ?? 'distributor',
                    'subtotal' => $this->subtotal,
                    'discount_amount' => $discountToStore,
                    'discount_type' => $this->additionalDiscountType,
                    'total_amount' => $this->grandTotal,
                    'due_amount' => $this->grandTotal,
                    'status' => 'confirm',
                    'payment_status' => 'pending',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                    'notes' => $this->notes,
                ]);

                // Create Sale Items and deduct stock immediately (auto-approved)
                foreach ($this->cart as $item) {
                    $rawId = $item['product_id'] ?? $item['id'];
                    $baseProductId = is_numeric($rawId) ? (int)$rawId : (int)preg_replace('/[^0-9].*$/', '', (string)$rawId);
                    $variantId = $item['variant_id'] ?? null;
                    $variantValue = $item['variant_value'] ?? null;
                    $quantity = $item['quantity'];

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $baseProductId,
                        'product_code' => $item['code'],
                        'product_name' => $item['name'],
                        'quantity' => $quantity,
                        'unit_price' => $item['price'],
                        'discount_per_unit' => $item['discount'],
                        'total_discount' => $item['discount'] * $quantity,
                        'total' => $item['total'],
                        'variant_id' => $variantId,
                        'variant_value' => $variantValue,
                    ]);

                    // Deduct stock using FIFO method (sale is auto-approved)
                    try {
                        FIFOStockService::deductStock($baseProductId, $quantity, $variantId, $variantValue);
                    } catch (\Exception $e) {
                        Log::error('Stock deduction failed for product: ' . $baseProductId, [
                            'error' => $e->getMessage(),
                            'quantity' => $quantity,
                            'variant_id' => $variantId,
                            'variant_value' => $variantValue,
                        ]);
                        throw new \Exception('Failed to deduct stock for ' . $item['name'] . ': ' . $e->getMessage());
                    }
                }

                // Apply customer's overpaid amount to reduce the due
                $customer = Customer::find($this->customerId);
                $saleDueAmount = $this->grandTotal;
                $overpaidUsed = 0;

                if ($customer && $saleDueAmount > 0 && ($customer->overpaid_amount ?? 0) > 0) {
                    $overpaidUsed = min($customer->overpaid_amount, $saleDueAmount);
                    $saleDueAmount = $saleDueAmount - $overpaidUsed;
                    $customer->overpaid_amount = max(0, $customer->overpaid_amount - $overpaidUsed);

                    // Update sale with reduced due amount
                    $sale->due_amount = $saleDueAmount;
                    $sale->payment_status = $saleDueAmount <= 0 ? 'paid' : 'partial';
                    $sale->save();

                    Log::info('Overpaid amount applied on new sale', [
                        'customer_id' => $customer->id,
                        'sale_id' => $sale->id,
                        'overpaid_used' => $overpaidUsed,
                        'remaining_due' => $saleDueAmount,
                        'remaining_overpaid' => $customer->overpaid_amount,
                    ]);
                }

                // Sync customer balances accurately
                \App\Services\CustomerBalanceService::syncCustomerBalance($this->customerId);

                DB::commit();
                $this->createdSale = $sale->load(['customer', 'items.product']);
                $this->showSaleModal = true;

                $successMsg = 'Sale created successfully! Stock has been updated.';
                if ($overpaidUsed > 0) {
                    $successMsg .= ' Rs.' . number_format($overpaidUsed, 2) . ' applied from customer overpaid balance.';
                }
                session()->flash('success', $successMsg);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Sale creation failed: ' . $e->getMessage());
            session()->flash('error', 'Failed to create sale: ' . $e->getMessage());
        }
    }

    public function createNewSale()
    {
        $this->clearCart();
        $this->customerId = '';
        $this->selectedCustomer = null;
        $this->showSaleModal = false;
        $this->createdSale = null;
        $this->editMode = false;
        $this->editingSaleId = null;
        $this->editingSale = null;
    }

    public function cancelEdit()
    {
        $this->createNewSale();
        redirect()->route('salesman.sales');
    }

    // Computed Properties
    public function getSubtotalProperty()
    {
        return (float) collect($this->cart)->sum('total');
    }

    public function getTotalDiscountProperty()
    {
        return (float) collect($this->cart)->sum(function ($item) {
            return $item['discount'] * $item['quantity'];
        });
    }

    public function getAdditionalDiscountAmountProperty()
    {
        $discount = (float) $this->additionalDiscount;
        if ($this->additionalDiscountType === 'percentage') {
            return ($this->subtotal * $discount) / 100;
        }
        return min($discount, $this->subtotal);
    }

    public function getGrandTotalProperty()
    {
        return (float) max(0, $this->subtotal - $this->additionalDiscountAmount);
    }

    public function render()
    {
        return view('livewire.salesman.salesman-billing');
    }
}
