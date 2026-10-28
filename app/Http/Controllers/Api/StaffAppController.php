<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\StaffProduct;
use App\Models\StaffSale;
use App\Models\StaffReturn;
use App\Models\StaffPermission;
use App\Models\ProductDetail;
use App\Models\SaleItem;
use App\Models\Cheque;
use App\Models\UserLocation;
use App\Notifications\NewSaleNotification;
use App\Notifications\PaymentNotification;
use App\Notifications\LowStockNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * Staff App Controller
 * 
 * This controller handles all staff-specific API endpoints.
 * Staff can only see their own data and have restricted permissions.
 * Staff cannot create, edit, or delete products or settings.
 */
class StaffAppController extends ApiController
{
    /**
     * Get staff dashboard data
     * Shows only staff's own sales, stock, and payment info
     */
    public function getDashboard(Request $request)
    {
        $staffId = Auth::id();

        // Get sales statistics for this staff member
        $salesStats = Sale::where('user_id', $staffId)
            ->select(
                DB::raw('SUM(total_amount) as total_sales'),
                DB::raw('SUM(due_amount) as total_due'),
                DB::raw('COUNT(*) as sales_count')
            )->first();

        $totalSales = $salesStats->total_sales ?? 0;
        $totalDue = $salesStats->total_due ?? 0;
        $totalRevenue = $totalSales - $totalDue;

        // Get inventory statistics
        $inventoryStats = StaffProduct::where('staff_id', $staffId)
            ->select(
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(sold_quantity) as sold_quantity'),
                DB::raw('SUM((quantity - sold_quantity) * unit_price) as available_value'),
                DB::raw('SUM(sold_value) as sold_value')
            )->first();

        // Get payment stats
        $paymentStats = Payment::whereHas('sale', function ($q) use ($staffId) {
            $q->where('user_id', $staffId);
        })
            ->select(
                DB::raw("SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_amount"),
                DB::raw("SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount"),
                DB::raw("COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count")
            )->first();

        // Get today's sales
        $todaySales = Sale::where('user_id', $staffId)
            ->whereDate('created_at', today())
            ->select(
                DB::raw('SUM(total_amount) as total'),
                DB::raw('COUNT(*) as count')
            )->first();

        // Get recent sales
        $recentSales = Sale::where('user_id', $staffId)
            ->with('customer:id,name,phone')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'customer_name' => $sale->customer->name ?? 'N/A',
                    'total_amount' => $sale->total_amount,
                    'due_amount' => $sale->due_amount,
                    'payment_status' => $sale->payment_status,
                    'created_at' => $sale->created_at,
                ];
            });

        return $this->success([
            'sales' => [
                'total_sales' => $totalSales,
                'total_revenue' => $totalRevenue,
                'total_due' => $totalDue,
                'sales_count' => $salesStats->sales_count ?? 0,
            ],
            'inventory' => [
                'total_quantity' => $inventoryStats->total_quantity ?? 0,
                'sold_quantity' => $inventoryStats->sold_quantity ?? 0,
                'available_quantity' => ($inventoryStats->total_quantity ?? 0) - ($inventoryStats->sold_quantity ?? 0),
                'available_value' => $inventoryStats->available_value ?? 0,
                'sold_value' => $inventoryStats->sold_value ?? 0,
            ],
            'payments' => [
                'approved_amount' => $paymentStats->approved_amount ?? 0,
                'pending_amount' => $paymentStats->pending_amount ?? 0,
                'pending_count' => $paymentStats->pending_count ?? 0,
            ],
            'today' => [
                'sales_total' => $todaySales->total ?? 0,
                'sales_count' => $todaySales->count ?? 0,
            ],
            'recent_sales' => $recentSales,
        ]);
    }

    /**
     * Get staff permissions
     * Returns list of permissions assigned to the authenticated staff
     */
    public function getPermissions(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            // Admin has all permissions
            return $this->success([
                'role' => 'admin',
                'permissions' => array_keys(StaffPermission::availablePermissions()),
                'all_permissions' => true,
            ]);
        }

        // Get staff permissions
        $permissions = StaffPermission::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('permission_key')
            ->toArray();

        // If no permissions assigned, only show dashboard
        if (empty($permissions)) {
            $permissions = ['menu_dashboard'];
        }

        return $this->success([
            'role' => 'staff',
            'permissions' => $permissions,
            'all_permissions' => false,
        ]);
    }

    /**
     * Get staff's allocated stock/products
     * Read-only view - staff cannot edit
     */
    public function getMyStock(Request $request)
    {
        $staffId = Auth::id();
        $search = $request->query('search', '');

        $query = StaffProduct::where('staff_id', $staffId)
            ->with(['product:id,name,code,model,image,brand_id', 'product.brand:id,brand_name']);

        $products = $query->get()->map(function ($item) {
            $availableQty = ($item->quantity ?? 0) - ($item->sold_quantity ?? 0);
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? 'N/A',
                'product_code' => $item->product->code ?? 'N/A',
                'product_model' => $item->product->model ?? '',
                'product_image' => $item->product->image ?? '',
                'brand_name' => $item->product->brand->brand_name ?? '',
                'quantity' => $item->quantity,
                'sold_quantity' => $item->sold_quantity,
                'available_quantity' => max(0, $availableQty),
                'unit_price' => $item->unit_price,
                'total_value' => $item->total_value,
                'sold_value' => $item->sold_value,
                'available_value' => ($availableQty * $item->unit_price),
                'status' => $availableQty <= 0 ? 'sold_out' : ($item->sold_quantity > 0 ? 'partial' : 'available'),
            ];
        });

        // Apply search filter
        if (!empty($search)) {
            $searchLower = strtolower($search);
            $products = $products->filter(function ($item) use ($searchLower) {
                return str_contains(strtolower($item['product_name']), $searchLower) ||
                    str_contains(strtolower($item['product_code']), $searchLower) ||
                    str_contains(strtolower($item['product_model']), $searchLower);
            })->values();
        }

        // Calculate totals
        $totals = [
            'total_quantity' => $products->sum('quantity'),
            'sold_quantity' => $products->sum('sold_quantity'),
            'available_quantity' => $products->sum('available_quantity'),
            'total_value' => $products->sum('total_value'),
            'sold_value' => $products->sum('sold_value'),
            'available_value' => $products->sum('available_value'),
        ];

        return $this->success([
            'results' => $products,
            'count' => $products->count(),
            'totals' => $totals,
        ]);
    }

    /**
     * Get staff's sales list
     * Read-only view of their own sales
     */
    public function getMySales(Request $request)
    {
        $staffId = Auth::id();
        $status = $request->query('status', 'all');
        $search = $request->query('search', '');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = Sale::where('user_id', $staffId)
            ->with(['customer:id,name,phone,email', 'items']);

        if ($status !== 'all') {
            $query->where('payment_status', $status);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', '%' . $search . '%')
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $sales = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->success([
            'results' => $sales->items(),
            'count' => $sales->total(),
            'current_page' => $sales->currentPage(),
            'last_page' => $sales->lastPage(),
        ]);
    }

    /**
     * Get single sale details
     */
    public function getSaleDetails(Request $request, $saleId)
    {
        $staffId = Auth::id();

        $sale = Sale::where('id', $saleId)
            ->where('user_id', $staffId)
            ->with(['customer', 'items', 'payments'])
            ->first();

        if (!$sale) {
            return $this->error('Sale not found or access denied', 404);
        }

        return $this->success($sale);
    }

    /**
     * Get staff's customers
     * Only customers created by this staff member
     */
    public function getMyCustomers(Request $request)
    {
        $staffId = Auth::id();
        $search = $request->query('search', '');

        $query = Customer::where('user_id', $staffId);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $customers = $query->orderBy('name')->get();

        return $this->success([
            'results' => $customers,
            'count' => $customers->count(),
        ]);
    }

    /**
     * Create a new customer
     * Staff can create customers linked to them
     */
    public function createCustomer(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'type' => 'required|in:retail,wholesale',
            'business_name' => 'nullable|string|max:255',
        ]);

        try {
            $customer = Customer::create([
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'address' => $request->address,
                'type' => $request->type,
                'business_name' => $request->business_name,
                'user_id' => Auth::id(),
            ]);

            return $this->success($customer, 'Customer created successfully', 201);
        } catch (\Exception $e) {
            Log::error('Staff create customer error: ' . $e->getMessage());
            return $this->error('Failed to create customer', 500);
        }
    }

    /**
     * Get products available for sale
     * If stock_allocation is enabled: returns only staff-allocated products
     * If stock_allocation is disabled: returns ALL products from catalog
     */
    public function getProductsForSale(Request $request)
    {
        $staffId = Auth::id();
        $search = $request->query('search', '');

        if (strlen($search) < 2) {
            return $this->success(['results' => [], 'count' => 0]);
        }

        // Check stock_allocation setting
        $stockAllocation = \App\Models\Setting::where('key', 'stock_allocation')->first();
        $isAllocationEnabled = $stockAllocation ? ($stockAllocation->value === 'true' || $stockAllocation->value === '1') : true;

        if ($isAllocationEnabled) {
            return $this->getProductsForSaleAllocated($staffId, $search);
        } else {
            return $this->getProductsForSaleAll($search);
        }
    }

    /**
     * Get allocated products for sale (stock_allocation = true)
     */
    private function getProductsForSaleAllocated($staffId, $search)
    {
        $products = StaffProduct::where('staff_id', $staffId)
            ->with([
                'product:id,name,code,model,image,brand_id,variant_id',
                'product.brand:id,brand_name',
                'product.variant',
                'product.stocks',
                'product.prices',
                'product.price',
            ])
            ->get()
            ->filter(function ($item) use ($search) {
                $searchLower = strtolower($search);
                return str_contains(strtolower($item->product->name ?? ''), $searchLower) ||
                    str_contains(strtolower($item->product->code ?? ''), $searchLower) ||
                    str_contains(strtolower($item->product->model ?? ''), $searchLower);
            });

        // Expand variants the same way the web billing page does
        $expandedProducts = collect();
        foreach ($products as $item) {
            $availableQty = max(0, ($item->quantity ?? 0) - ($item->sold_quantity ?? 0));
            if ($availableQty <= 0) continue;

            $product = $item->product;
            if (!$product) continue;

            // If product has variants, expand each variant as its own entry
            if ($product->variant_id && $product->stocks && $product->stocks->isNotEmpty()) {
                $orderedValues = [];
                if ($product->variant && is_array($product->variant->variant_values) && count($product->variant->variant_values) > 0) {
                    $orderedValues = $product->variant->variant_values;
                }

                $stocksByValue = [];
                foreach ($product->stocks as $stock) {
                    if (($stock->available_stock ?? 0) <= 0) continue;
                    $stocksByValue[$stock->variant_value] = $stock;
                }

                $addVariant = function ($variantValue, $stock) use ($product, $item, &$expandedProducts) {
                    $priceRecord = $product->prices->firstWhere('variant_value', $variantValue) ?? $product->price;
                    $expandedProducts->push([
                        'id' => $product->id,
                        'composite_id' => $product->id . '::' . $variantValue,
                        'name' => $product->name . ' (' . $variantValue . ')',
                        'base_name' => $product->name,
                        'code' => $product->code ?? '',
                        'model' => $product->model ?? '',
                        'image' => $product->image ?? '',
                        'brand' => $product->brand->brand_name ?? '',
                        'variant_id' => $stock->variant_id ?? $product->variant_id,
                        'variant_value' => $variantValue,
                        'variant_name' => $product->variant->variant_name ?? null,
                        'has_variants' => true,
                        'price' => (float) ($priceRecord->wholesale_price ?? $priceRecord->selling_price ?? $item->unit_price ?? 0),
                        'retail_price' => (float) ($priceRecord->retail_price ?? 0),
                        'wholesale_price' => (float) ($priceRecord->wholesale_price ?? 0),
                        'distributor_price' => (float) ($priceRecord->distributor_price ?? 0),
                        'stock' => min($stock->available_stock ?? 0, max(0, ($item->quantity ?? 0) - ($item->sold_quantity ?? 0))),
                    ]);
                };

                if (!empty($orderedValues)) {
                    foreach ($orderedValues as $val) {
                        if (!isset($stocksByValue[$val])) continue;
                        $addVariant($val, $stocksByValue[$val]);
                    }
                    foreach ($stocksByValue as $v => $stock) {
                        if (in_array($v, $orderedValues)) continue;
                        $addVariant($v, $stock);
                    }
                } else {
                    foreach ($stocksByValue as $v => $stock) {
                        $addVariant($v, $stock);
                    }
                }
            } else {
                // Single product (no variants)
                $expandedProducts->push([
                    'id' => $product->id,
                    'composite_id' => (string) $product->id,
                    'name' => $product->name ?? 'N/A',
                    'base_name' => $product->name ?? 'N/A',
                    'code' => $product->code ?? '',
                    'model' => $product->model ?? '',
                    'image' => $product->image ?? '',
                    'brand' => $product->brand->brand_name ?? '',
                    'variant_id' => null,
                    'variant_value' => null,
                    'variant_name' => null,
                    'has_variants' => false,
                    'price' => $item->unit_price,
                    'retail_price' => (float) ($product->price->retail_price ?? 0),
                    'wholesale_price' => (float) ($product->price->wholesale_price ?? 0),
                    'distributor_price' => (float) ($product->price->distributor_price ?? 0),
                    'stock' => $availableQty,
                ]);
            }
        }

        $expandedProducts = $expandedProducts->take(30)->values();

        return $this->success([
            'results' => $expandedProducts,
            'count' => $expandedProducts->count(),
        ]);
    }

    /**
     * Get ALL products for sale (stock_allocation = false)
     * Returns products from the main catalog with actual stock levels
     */
    private function getProductsForSaleAll($search)
    {
        $searchLower = strtolower($search);

        $products = ProductDetail::with([
            'brand:id,brand_name',
            'variant',
            'stocks',
            'prices',
            'price',
        ])
        ->where(function ($q) use ($searchLower) {
            $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchLower}%"])
              ->orWhereRaw('LOWER(code) LIKE ?', ["%{$searchLower}%"])
              ->orWhereRaw('LOWER(model) LIKE ?', ["%{$searchLower}%"]);
        })
        ->where('status', 'active')
        ->take(50)
        ->get();

        $expandedProducts = collect();

        foreach ($products as $product) {
            // If product has variants, expand each variant as its own entry
            if ($product->variant_id && $product->stocks && $product->stocks->isNotEmpty()) {
                $orderedValues = [];
                if ($product->variant && is_array($product->variant->variant_values) && count($product->variant->variant_values) > 0) {
                    $orderedValues = $product->variant->variant_values;
                }

                $stocksByValue = [];
                foreach ($product->stocks as $stock) {
                    if (($stock->available_stock ?? 0) <= 0) continue;
                    $stocksByValue[$stock->variant_value] = $stock;
                }

                $addVariant = function ($variantValue, $stock) use ($product, &$expandedProducts) {
                    $priceRecord = $product->prices->firstWhere('variant_value', $variantValue) ?? $product->price;
                    $expandedProducts->push([
                        'id' => $product->id,
                        'composite_id' => $product->id . '::' . $variantValue,
                        'name' => $product->name . ' (' . $variantValue . ')',
                        'base_name' => $product->name,
                        'code' => $product->code ?? '',
                        'model' => $product->model ?? '',
                        'image' => $product->image ?? '',
                        'brand' => $product->brand->brand_name ?? '',
                        'variant_id' => $stock->variant_id ?? $product->variant_id,
                        'variant_value' => $variantValue,
                        'variant_name' => $product->variant->variant_name ?? null,
                        'has_variants' => true,
                        'price' => (float) ($priceRecord->wholesale_price ?? $priceRecord->selling_price ?? 0),
                        'retail_price' => (float) ($priceRecord->retail_price ?? 0),
                        'wholesale_price' => (float) ($priceRecord->wholesale_price ?? 0),
                        'distributor_price' => (float) ($priceRecord->distributor_price ?? 0),
                        'stock' => (int) ($stock->available_stock ?? 0),
                    ]);
                };

                if (!empty($orderedValues)) {
                    foreach ($orderedValues as $val) {
                        if (!isset($stocksByValue[$val])) continue;
                        $addVariant($val, $stocksByValue[$val]);
                    }
                    foreach ($stocksByValue as $v => $stock) {
                        if (in_array($v, $orderedValues)) continue;
                        $addVariant($v, $stock);
                    }
                } else {
                    foreach ($stocksByValue as $v => $stock) {
                        $addVariant($v, $stock);
                    }
                }
            } else {
                // Single product (no variants)
                $mainStock = $product->stocks->first();
                $availableStock = (int) ($mainStock->available_stock ?? 0);
                if ($availableStock <= 0) continue;

                $expandedProducts->push([
                    'id' => $product->id,
                    'composite_id' => (string) $product->id,
                    'name' => $product->name ?? 'N/A',
                    'base_name' => $product->name ?? 'N/A',
                    'code' => $product->code ?? '',
                    'model' => $product->model ?? '',
                    'image' => $product->image ?? '',
                    'brand' => $product->brand->brand_name ?? '',
                    'variant_id' => null,
                    'variant_value' => null,
                    'variant_name' => null,
                    'has_variants' => false,
                    'price' => (float) ($product->price->wholesale_price ?? $product->price->selling_price ?? 0),
                    'retail_price' => (float) ($product->price->retail_price ?? 0),
                    'wholesale_price' => (float) ($product->price->wholesale_price ?? 0),
                    'distributor_price' => (float) ($product->price->distributor_price ?? 0),
                    'stock' => $availableStock,
                ]);
            }
        }

        $expandedProducts = $expandedProducts->take(30)->values();

        return $this->success([
            'results' => $expandedProducts,
            'count' => $expandedProducts->count(),
        ]);
    }

    /**
     * Create a sale
     * If stock_allocation enabled: auto-confirms and deducts stock
     * If stock_allocation disabled: creates as pending for admin approval
     */
    public function createSale(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:product_details,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.variant_id' => 'nullable|integer',
            'items.*.variant_value' => 'nullable|string',
            'payment_method' => 'required|in:cash,cheque,bank_transfer,credit',
            'paid_amount' => 'nullable|numeric|min:0',
            'additional_discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'cheques' => 'nullable|array',
            'bank_reference' => 'nullable|string',
            'bank_name' => 'nullable|string',
        ]);

        $staffId = Auth::id();

        try {
            DB::beginTransaction();

            $customer = Customer::find($request->customer_id);

            // Check stock_allocation setting
            $stockAllocation = \App\Models\Setting::where('key', 'stock_allocation')->first();
            $isAllocationEnabled = $stockAllocation ? ($stockAllocation->value === 'true' || $stockAllocation->value === '1') : true;

            // Calculate totals
            $subtotal = 0;
            $totalDiscount = 0;

            foreach ($request->items as $item) {
                $itemTotal = ($item['price'] - ($item['discount'] ?? 0)) * $item['quantity'];
                $subtotal += $itemTotal;
                $totalDiscount += ($item['discount'] ?? 0) * $item['quantity'];
            }

            $additionalDiscount = $request->additional_discount ?? 0;
            $grandTotal = $subtotal - $additionalDiscount;
            $paidAmount = $request->paid_amount ?? 0;

            if ($request->payment_method === 'credit') {
                $paidAmount = 0;
            }

            $dueAmount = max(0, $grandTotal - $paidAmount);

            // Determine payment status
            $paymentStatus = 'pending';
            if ($paidAmount >= $grandTotal) {
                $paymentStatus = 'pending'; // Still pending until admin approves
            } elseif ($paidAmount > 0) {
                $paymentStatus = 'partial';
            }

            // Check stock availability and update sold quantities (only when allocation is enabled)
            if ($isAllocationEnabled) {
                foreach ($request->items as $item) {
                    $staffProduct = StaffProduct::where('staff_id', $staffId)
                        ->where('product_id', $item['product_id'])
                        ->first();

                    if (!$staffProduct) {
                        throw new \Exception("Product ID {$item['product_id']} is not allocated to you");
                    }

                    $availableStock = $staffProduct->quantity - $staffProduct->sold_quantity;
                    if ($item['quantity'] > $availableStock) {
                        throw new \Exception("Insufficient stock for product ID {$item['product_id']}. Available: {$availableStock}");
                    }

                    // Update sold quantity
                    $staffProduct->increment('sold_quantity', $item['quantity']);
                    $staffProduct->increment('sold_value', ($item['price'] - ($item['discount'] ?? 0)) * $item['quantity']);
                }
            }

            // Create sale
            $sale = Sale::create([
                'sale_id' => Sale::generateSaleId(),
                'invoice_number' => Sale::generateInvoiceNumber(),
                'customer_id' => $customer->id,
                'customer_type' => $customer->type,
                'subtotal' => $subtotal,
                'discount_amount' => $totalDiscount + $additionalDiscount,
                'total_amount' => $grandTotal,
                'payment_type' => $paidAmount >= $grandTotal ? 'full' : 'partial',
                'payment_status' => $paymentStatus,
                'due_amount' => $dueAmount,
                'notes' => $request->notes,
                'user_id' => $staffId,
                'status' => $isAllocationEnabled ? 'confirm' : 'pending',
                'sale_type' => 'staff',
            ]);

            // Create sale items
            foreach ($request->items as $item) {
                $product = ProductDetail::find($item['product_id']);
                $variantId = $item['variant_id'] ?? null;
                $variantValue = $item['variant_value'] ?? null;
                $displayName = $variantValue ? ($product->name ?? '') . ' (' . $variantValue . ')' : ($product->name ?? '');

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'product_code' => $product->code ?? '',
                    'product_name' => $displayName,
                    'product_model' => $product->model ?? '',
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'discount_per_unit' => $item['discount'] ?? 0,
                    'total_discount' => ($item['discount'] ?? 0) * $item['quantity'],
                    'total' => ($item['price'] - ($item['discount'] ?? 0)) * $item['quantity'],
                    'variant_id' => $variantId,
                    'variant_value' => $variantValue,
                ]);

                // Also update the main product stock for variant items (only when allocation enabled; pending sales deduct on approval)
                if ($isAllocationEnabled) {
                    if ($variantValue || $variantId) {
                        $stockRecord = \App\Models\ProductStock::where('product_id', $item['product_id'])
                            ->when($variantId, fn($q) => $q->where('variant_id', $variantId))
                            ->when($variantValue, fn($q) => $q->where('variant_value', $variantValue))
                            ->first();
                        if ($stockRecord) {
                            $stockRecord->available_stock = max(0, $stockRecord->available_stock - $item['quantity']);
                            $stockRecord->sold_count = ($stockRecord->sold_count ?? 0) + $item['quantity'];
                            $stockRecord->save();
                        }
                    } else {
                        $stock = \App\Models\ProductStock::where('product_id', $item['product_id'])->first();
                        if ($stock) {
                            $stock->available_stock = max(0, $stock->available_stock - $item['quantity']);
                            $stock->sold_count = ($stock->sold_count ?? 0) + $item['quantity'];
                            $stock->save();
                        }
                    }
                }
            }

            // Create payment if amount paid
            if ($paidAmount > 0 && $request->payment_method !== 'credit') {
                $payment = Payment::create([
                    'customer_id' => $customer->id,
                    'sale_id' => $sale->id,
                    'amount' => $paidAmount,
                    'payment_method' => $request->payment_method,
                    'payment_date' => now(),
                    'is_completed' => false,
                    'status' => 'pending', // Requires admin approval
                    'created_by' => $staffId,
                    'payment_reference' => 'STAFF-' . strtoupper($request->payment_method) . '-' . now()->format('YmdHis'),
                ]);

                // Handle cheques
                if ($request->payment_method === 'cheque' && !empty($request->cheques)) {
                    foreach ($request->cheques as $cheque) {
                        Cheque::create([
                            'cheque_number' => $cheque['number'],
                            'cheque_date' => $cheque['date'],
                            'bank_name' => $cheque['bank_name'] ?? '',
                            'cheque_amount' => $cheque['amount'],
                            'status' => 'pending',
                            'customer_id' => $customer->id,
                            'payment_id' => $payment->id,
                        ]);
                    }
                }

                // Handle bank transfer
                if ($request->payment_method === 'bank_transfer') {
                    $payment->update([
                        'bank_name' => $request->bank_name,
                        'transfer_reference' => $request->bank_reference,
                    ]);
                }
            }

            // Update staff_sales (only when allocation enabled)
            if ($isAllocationEnabled) {
                $staffSale = StaffSale::where('staff_id', $staffId)->first();
                if ($staffSale) {
                    $staffSale->increment('sold_quantity', collect($request->items)->sum('quantity'));
                    $staffSale->increment('sold_value', $grandTotal);
                }
            }

            DB::commit();

            $sale->load(['customer', 'items', 'payments']);

            // Notify all admin users about the new staff sale
            try {
                $staffUser = Auth::user();
                $creatorName = $staffUser ? $staffUser->name : 'Staff';
                $admins = User::where('role', 'admin')->get();
                foreach ($admins as $admin) {
                    $admin->notify(new NewSaleNotification($sale, $creatorName));
                }
            } catch (\Exception $notifErr) {
                Log::warning('Failed to send staff sale notification: ' . $notifErr->getMessage());
            }

            $message = $isAllocationEnabled
                ? 'Sale created successfully. Payment is pending admin approval.'
                : 'Sale submitted for admin approval.';

            return $this->success([
                'sale' => $sale,
                'message' => $message,
                'is_pending' => !$isAllocationEnabled,
            ], $isAllocationEnabled ? 'Sale created' : 'Sale pending approval', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Staff create sale error: ' . $e->getMessage());
            return $this->error('Failed to create sale: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get staff's payments
     */
    public function getMyPayments(Request $request)
    {
        $staffId = Auth::id();
        $status = $request->query('status', 'all');

        $query = Payment::whereHas('sale', function ($q) use ($staffId) {
            $q->where('user_id', $staffId);
        })->with(['sale:id,invoice_number,total_amount', 'sale.customer:id,name']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $payments = $query->orderBy('created_at', 'desc')->paginate(20);

        // Get counts
        $counts = [
            'pending' => Payment::whereHas('sale', function ($q) use ($staffId) {
                $q->where('user_id', $staffId);
            })->where('status', 'pending')->count(),
            'approved' => Payment::whereHas('sale', function ($q) use ($staffId) {
                $q->where('user_id', $staffId);
            })->where('status', 'approved')->count(),
            'rejected' => Payment::whereHas('sale', function ($q) use ($staffId) {
                $q->where('user_id', $staffId);
            })->where('status', 'rejected')->count(),
        ];

        return $this->success([
            'results' => $payments->items(),
            'count' => $payments->total(),
            'current_page' => $payments->currentPage(),
            'last_page' => $payments->lastPage(),
            'counts' => $counts,
        ]);
    }

    /**
     * Get due payments/sales that need payment
     */
    public function getDueSales(Request $request)
    {
        $staffId = Auth::id();

        $dueSales = Sale::where('user_id', $staffId)
            ->where('due_amount', '>', 0)
            ->with(['customer:id,name,phone,email'])
            ->orderBy('created_at', 'desc')
            ->get();

        $totalDue = $dueSales->sum('due_amount');

        return $this->success([
            'results' => $dueSales,
            'count' => $dueSales->count(),
            'total_due' => $totalDue,
        ]);
    }

    /**
     * Add payment to existing sale
     */
    public function addPayment(Request $request, $saleId)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,cheque,bank_transfer',
            'cheques' => 'nullable|array',
            'bank_reference' => 'nullable|string',
            'bank_name' => 'nullable|string',
        ]);

        $staffId = Auth::id();

        $sale = Sale::where('id', $saleId)
            ->where('user_id', $staffId)
            ->first();

        if (!$sale) {
            return $this->error('Sale not found or access denied', 404);
        }

        if ($sale->due_amount <= 0) {
            return $this->error('This sale has no due amount', 400);
        }

        try {
            DB::beginTransaction();

            $payment = Payment::create([
                'customer_id' => $sale->customer_id,
                'sale_id' => $sale->id,
                'amount' => min($request->amount, $sale->due_amount),
                'payment_method' => $request->payment_method,
                'payment_date' => now(),
                'is_completed' => false,
                'status' => 'pending',
                'created_by' => $staffId,
                'payment_reference' => 'STAFF-' . strtoupper($request->payment_method) . '-' . now()->format('YmdHis'),
            ]);

            // Handle cheques
            if ($request->payment_method === 'cheque' && !empty($request->cheques)) {
                foreach ($request->cheques as $cheque) {
                    Cheque::create([
                        'cheque_number' => $cheque['number'],
                        'cheque_date' => $cheque['date'],
                        'bank_name' => $cheque['bank_name'] ?? '',
                        'cheque_amount' => $cheque['amount'],
                        'status' => 'pending',
                        'customer_id' => $sale->customer_id,
                        'payment_id' => $payment->id,
                    ]);
                }
            }

            // Handle bank transfer
            if ($request->payment_method === 'bank_transfer') {
                $payment->update([
                    'bank_name' => $request->bank_name,
                    'transfer_reference' => $request->bank_reference,
                ]);
            }

            DB::commit();

            // Notify admins about new payment from staff
            try {
                $admins = User::where('role', 'admin')->get();
                foreach ($admins as $admin) {
                    $admin->notify(new PaymentNotification($payment, 'received'));
                }
            } catch (\Exception $notifErr) {
                Log::warning('Failed to send staff payment notification: ' . $notifErr->getMessage());
            }

            return $this->success([
                'payment' => $payment,
                'message' => 'Payment added. Pending admin approval.',
            ], 'Payment added', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Staff add payment error: ' . $e->getMessage());
            return $this->error('Failed to add payment', 500);
        }
    }

    /**
     * Get staff's returns
     */
    public function getMyReturns(Request $request)
    {
        $staffId = Auth::id();

        $returns = StaffReturn::where('staff_id', $staffId)
            ->with(['product:id,name,code,model', 'customer:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success([
            'results' => $returns,
            'count' => $returns->count(),
        ]);
    }
    /**
     * Get customers with due amounts
     * Groups due amounts by customer for the collection screen
     */
    public function getDueCustomers(Request $request)
    {
        $staffId = Auth::id();
        $search = $request->query('search', '');

        $query = Customer::where('user_id', $staffId)
            ->whereHas('sales', function ($q) {
                $q->where('due_amount', '>', 0);
            })
            ->withSum([
                'sales' => function ($q) {
                    $q->where('due_amount', '>', 0);
                }
            ], 'due_amount');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        $customers = $query->get()->map(function ($customer) {
            // Calculate real due amount (Sale Due - Pending Payments)
            $sales = $customer->sales()->where('due_amount', '>', 0)->get();
            $totalDue = 0;
            $billCount = 0;

            foreach ($sales as $sale) {
                $pendingAmount = $sale->payments()->where('status', 'pending')->sum('amount');
                $realDue = max(0, $sale->due_amount - $pendingAmount);

                if ($realDue > 0.01) {
                    $totalDue += $realDue;
                    $billCount++;
                }
            }

            if ($totalDue <= 0.01)
                return null;

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'total_due' => $totalDue,
                'bill_count' => $billCount,
            ];
        })->filter()->values();

        return $this->success([
            'results' => $customers,
            'count' => $customers->count(),
        ]);
    }

    /**
     * Get due bills for a specific customer
     */
    public function getCustomerDueBills(Request $request, $customerId)
    {
        $staffId = Auth::id();

        $bills = Sale::where('user_id', $staffId)
            ->where('customer_id', $customerId)
            ->where('due_amount', '>', 0)
            ->with([
                'payments' => function ($q) {
                    $q->where('status', 'pending');
                }
            ])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($sale) {
                $pendingAmount = $sale->payments->sum('amount');
                $realDue = max(0, $sale->due_amount - $pendingAmount);

                if ($realDue <= 0.01)
                    return null;

                return [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'total_amount' => $sale->total_amount,
                    'due_amount' => $realDue,
                    'created_at' => $sale->created_at,
                ];
            })
            ->filter()
            ->values();

        return $this->success([
            'results' => $bills,
            'count' => $bills->count(),
        ]);
    }

    /**
     * Process bulk payment collection
     * Handles payments for multiple bills at once
     */
    public function collectPayment(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'payments' => 'required|array|min:1',
            'payments.*.sale_id' => 'required|exists:sales,id',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,cheque,bank_transfer',
            'cheques' => 'nullable|array',
            'bank_reference' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $staffId = Auth::id();

        try {
            DB::beginTransaction();

            $idx = 0;
            foreach ($request->payments as $paymentItem) {
                $sale = Sale::where('id', $paymentItem['sale_id'])
                    ->where('customer_id', $request->customer_id)
                    ->first();

                if (!$sale)
                    continue;

                if ($paymentItem['amount'] > $sale->due_amount + 0.01) { // Add small epsilon for float comparison
                    // Skip or error? Let's error to be safe
                    // throw new \Exception("Payment amount exceeds due amount for invoice {$sale->invoice_number}");
                }

                $payment = Payment::create([
                    'customer_id' => $request->customer_id,
                    'sale_id' => $sale->id,
                    'amount' => $paymentItem['amount'],
                    'payment_method' => $request->payment_method,
                    'payment_date' => now(),
                    'is_completed' => false,
                    'status' => 'pending',
                    'created_by' => $staffId,
                    'payment_reference' => 'COLL-' . strtoupper($request->payment_method) . '-' . now()->format('YmdHis') . '-' . $idx,
                    'notes' => $request->notes,
                ]);

                if ($request->payment_method === 'cheque' && !empty($request->cheques)) {
                    foreach ($request->cheques as $cheque) {
                        Cheque::create([
                            'cheque_number' => $cheque['number'],
                            'cheque_date' => $cheque['date'],
                            'bank_name' => $cheque['bank_name'] ?? '',
                            'cheque_amount' => $paymentItem['amount'],
                            'status' => 'pending',
                            'customer_id' => $request->customer_id,
                            'payment_id' => $payment->id,
                        ]);
                    }
                }

                if ($request->payment_method === 'bank_transfer') {
                    $payment->update([
                        'bank_name' => $request->bank_name,
                        'transfer_reference' => $request->bank_reference,
                    ]);
                }

                $idx++;
            }

            DB::commit();

            // Notify admins about collected payments
            try {
                $admins = User::where('role', 'admin')->get();
                $staffName = Auth::user()->name ?? 'Staff';
                foreach ($request->payments as $paymentItem) {
                    $lastPayment = Payment::where('sale_id', $paymentItem['sale_id'])
                        ->where('created_by', $staffId)
                        ->latest()
                        ->first();
                    if ($lastPayment) {
                        foreach ($admins as $admin) {
                            $admin->notify(new PaymentNotification($lastPayment, 'received'));
                        }
                    }
                }
            } catch (\Exception $notifErr) {
                Log::warning('Failed to send collection notification: ' . $notifErr->getMessage());
            }

            return $this->success(null, 'Payments collected successfully and sent for approval', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Staff collection error: ' . $e->getMessage());
            return $this->error('Failed to collect payment: ' . $e->getMessage(), 500);
        }
    }

    // ─── Admin Sale Approval API (for Expo) ───────────────────────────

    /**
     * Get pending staff sales for admin approval
     */
    public function getPendingSales(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return $this->error('Unauthorized', 403);
        }

        $status = $request->query('status', 'pending');
        $search = $request->query('search', '');

        $query = Sale::with(['customer:id,name,phone', 'user:id,name', 'items'])
            ->where('sale_type', 'staff');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('sale_id', 'like', "%{$search}%")
                  ->orWhere('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn($cq) => $cq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%"));
            });
        }

        $sales = $query->orderBy('created_at', 'desc')->paginate(20);

        $counts = [
            'pending' => Sale::where('sale_type', 'staff')->where('status', 'pending')->count(),
            'confirmed' => Sale::where('sale_type', 'staff')->where('status', 'confirm')->count(),
            'rejected' => Sale::where('sale_type', 'staff')->where('status', 'rejected')->count(),
        ];

        return $this->success([
            'sales' => $sales->items(),
            'counts' => $counts,
            'pagination' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'total' => $sales->total(),
            ],
        ]);
    }

    /**
     * Approve a pending staff sale
     * Mirrors the Livewire SaleApproval::approveSale logic
     */
    public function approveSale(Request $request, $id)
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return $this->error('Unauthorized', 403);
        }

        try {
            DB::beginTransaction();

            $sale = Sale::with(['items', 'customer'])->find($id);

            if (!$sale) {
                return $this->error('Sale not found', 404);
            }

            if ($sale->status !== 'pending') {
                return $this->error('Sale is already processed', 422);
            }

            // Deduct stock for each item (same logic as SaleApproval Livewire)
            foreach ($sale->items as $item) {
                $stock = null;

                if ($item->variant_id || $item->variant_value) {
                    $stockQuery = \App\Models\ProductStock::where('product_id', $item->product_id);
                    if ($item->variant_id) {
                        $stockQuery->where('variant_id', $item->variant_id);
                    }
                    if ($item->variant_value) {
                        $stockQuery->where('variant_value', $item->variant_value);
                    }
                    $stock = $stockQuery->first();
                } else {
                    $stock = \App\Models\ProductStock::where('product_id', $item->product_id)
                        ->where(function ($q) {
                            $q->whereNull('variant_value')
                              ->orWhere('variant_value', '')
                              ->orWhere('variant_value', 'null');
                        })
                        ->whereNull('variant_id')
                        ->first();

                    if (!$stock) {
                        $stock = \App\Models\ProductStock::where('product_id', $item->product_id)->first();
                    }
                }

                if (!$stock) {
                    DB::rollBack();
                    return $this->error("Stock record not found for {$item->product_name}", 422);
                }

                if ($stock->available_stock < $item->quantity) {
                    DB::rollBack();
                    return $this->error("Insufficient stock for {$item->product_name}. Available: {$stock->available_stock}, Required: {$item->quantity}", 422);
                }

                $stock->available_stock -= $item->quantity;
                $stock->sold_count = ($stock->sold_count ?? 0) + $item->quantity;
                $stock->save();
            }

            // Update sale status
            $sale->status = 'confirm';
            $sale->approved_by = $user->id;
            $sale->approved_at = now();

            // Recalculate due
            $existingPayments = Payment::where('sale_id', $sale->id)->sum('amount');
            $sale->due_amount = max(0, $sale->total_amount - $existingPayments);

            if ($sale->due_amount <= 0) {
                $sale->payment_status = 'paid';
            } elseif ($existingPayments > 0) {
                $sale->payment_status = 'partial';
            } else {
                $sale->payment_status = 'pending';
            }

            $sale->save();

            // Update customer due amount
            if ($sale->customer && $sale->due_amount > 0) {
                $sale->customer->due_amount = ($sale->customer->due_amount ?? 0) + $sale->due_amount;
                $sale->customer->total_due = ($sale->customer->opening_balance ?? 0) + $sale->customer->due_amount;
                $sale->customer->save();
            }

            DB::commit();

            $sale->load(['customer', 'user', 'items']);

            // Notify the staff member that their sale was approved
            try {
                $staffUser = User::find($sale->user_id);
                if ($staffUser) {
                    $staffUser->notify(new NewSaleNotification($sale, 'Admin (Approved)'));
                }
            } catch (\Exception $notifErr) {
                Log::warning('Failed to send sale approval notification: ' . $notifErr->getMessage());
            }

            return $this->success([
                'sale' => $sale,
            ], 'Sale approved successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API Sale approval error: ' . $e->getMessage());
            return $this->error('Failed to approve sale: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reject a pending staff sale
     */
    public function rejectSale(Request $request, $id)
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return $this->error('Unauthorized', 403);
        }

        $request->validate([
            'reason' => 'required|string|min:3',
        ]);

        try {
            $sale = Sale::find($id);
            if (!$sale) {
                return $this->error('Sale not found', 404);
            }

            if ($sale->status !== 'pending') {
                return $this->error('Sale is already processed', 422);
            }

            $sale->update([
                'status' => 'rejected',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'rejection_reason' => $request->reason,
            ]);

            return $this->success([
                'sale' => $sale->load(['customer', 'user']),
            ], 'Sale rejected');

        } catch (\Exception $e) {
            Log::error('API Sale rejection error: ' . $e->getMessage());
            return $this->error('Failed to reject sale: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store staff location tracking
     * Called by mobile app to update current location
     */
    public function postLocation(Request $request)
    {
        try {
            $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'accuracy' => 'nullable|numeric|min:0',
                'recorded_at' => 'nullable|string',
            ]);

            $staffId = Auth::id();

            // Parse recorded_at - accept any date format (ISO 8601, Y-m-d H:i:s, etc.)
            $recordedAt = now();
            if ($request->recorded_at) {
                try {
                    $recordedAt = \Carbon\Carbon::parse($request->recorded_at);
                } catch (\Exception $e) {
                    $recordedAt = now();
                }
            }

            // Create a new location record for this staff member
            $location = UserLocation::create([
                'user_id' => $staffId,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'accuracy' => $request->accuracy,
                'recorded_at' => $recordedAt,
            ]);

            Log::info('Staff location recorded', ['staff_id' => $staffId, 'lat' => $request->latitude, 'lng' => $request->longitude]);

            return $this->success([
                'location' => $location,
            ], 'Location recorded successfully');

        } catch (\Exception $e) {
            Log::error('API Location recording error: ' . $e->getMessage(), [
                'staff_id' => Auth::id(),
                'input' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('Failed to record location: ' . $e->getMessage(), 500);
        }
    }
}
