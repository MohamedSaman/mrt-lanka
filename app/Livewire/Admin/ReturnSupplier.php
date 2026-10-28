<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\ProductSupplier;
use App\Models\ProductDetail;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ProductStock;
use App\Models\ReturnSupplier as ReturnSupplierModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\WithDynamicLayout;

#[Title("Supplier Return")]
class ReturnSupplier extends Component
{
    use WithDynamicLayout;

    public $searchSupplier = '';
    public $suppliers = [];
    public $selectedSupplier = null;

    public $supplierPurchaseOrders = [];
    public $selectedPurchaseOrder = null;
    public $selectedPurchaseOrders = [];

    public $purchaseOrderProducts = [];
    public $returnItems = [];
    public $totalReturnValue = 0;
    public $overallDiscountPerItem = 0;

    public $showPurchaseOrderModal = false;
    public $purchaseOrderModalData = null;

    public $showReturnSection = false;
    public $searchReturnProduct = '';
    public $availableProducts = [];
    public $purchaseOrderProductsForSearch = [];
    public $selectedProducts = [];

    public $previousReturns = []; // Track previously returned items
    public $returnReason = 'damaged'; // Default return reason

    /** 🔍 Search Supplier or Purchase Order */
    public function updatedSearchSupplier()
    {
        if (strlen($this->searchSupplier) > 2) {
            $this->suppliers = ProductSupplier::query()
                ->where('name', 'like', '%' . $this->searchSupplier . '%')
                ->orWhere('phone', 'like', '%' . $this->searchSupplier . '%')
                ->orWhere('email', 'like', '%' . $this->searchSupplier . '%')
                ->limit(10)
                ->get();

            $this->supplierPurchaseOrders = PurchaseOrder::where('order_code', 'like', '%' . $this->searchSupplier . '%')
                ->latest()
                ->limit(5)
                ->get();
        } else {
            $this->suppliers = [];
            $this->supplierPurchaseOrders = [];
        }
    }

    /** 👤 Select Supplier */
    public function selectSupplier($supplierId)
    {
        $this->selectedSupplier = ProductSupplier::find($supplierId);
        $this->searchSupplier = '';
        $this->suppliers = [];

        $this->resetReturnData();
        $this->loadSupplierPurchaseOrders();
    }

    /** 🧾 Load Selected Supplier's Purchase Orders */
    public function loadSupplierPurchaseOrders()
    {
        if (!$this->selectedSupplier) {
            $this->supplierPurchaseOrders = [];
            return;
        }

        $this->supplierPurchaseOrders = PurchaseOrder::where('supplier_id', $this->selectedSupplier->id)
            ->whereIn('status', ['received', 'complete'])
            ->latest()
            ->limit(5)
            ->get();
    }

    /** 🎯 Simple Purchase Order Selection for Return */
    public function selectPurchaseOrderForReturn($purchaseOrderId)
    {
        $this->resetReturnData();

        $this->selectedPurchaseOrder = PurchaseOrder::with(['items.product', 'supplier'])->find($purchaseOrderId);
        $this->selectedPurchaseOrders = [$purchaseOrderId];
        $this->showReturnSection = true;

        if ($this->selectedPurchaseOrder && $this->selectedPurchaseOrder->supplier) {
            $this->selectedSupplier = $this->selectedPurchaseOrder->supplier;
        }

        if ($this->selectedPurchaseOrder) {
            // Calculate overall discount per item
            $this->calculateOverallDiscountPerItem();

            // Load previous returns for this purchase order
            $this->loadPreviousReturns();

            // Build return items with remaining quantities
            foreach ($this->selectedPurchaseOrder->items as $item) {
                $alreadyReturned = $this->getAlreadyReturnedQuantity($item->product->id, $item->variant_value);
                $remainingQty = $item->received_quantity - $alreadyReturned;

                if ($remainingQty > 0) {
                    // Build display name with variant
                    $displayName = $item->product->name;
                    if ($item->variant_value) {
                        $displayName .= ' (' . $item->variant_value . ')';
                    }

                    // Calculate per-unit discount (item discount is percentage)
                    $unitPrice = $item->unit_price;
                    $itemDiscountPercentage = $item->discount ?? 0;
                    $itemDiscountAmount = ($unitPrice * $itemDiscountPercentage) / 100;
                    $proportionalOverallDiscount = $this->overallDiscountPerItem;
                    $totalDiscountPerUnit = $itemDiscountAmount + $proportionalOverallDiscount;

                    $this->returnItems[] = [
                        'purchase_order_item_id' => $item->id,
                        'product_id' => $item->product->id,
                        'variant_id' => $item->variant_id,
                        'variant_value' => $item->variant_value,
                        'name' => $displayName,
                        'code' => $item->product->code,
                        'unit_price' => $unitPrice,
                        'discount_percentage' => $itemDiscountPercentage,
                        'discount_per_unit' => $itemDiscountAmount,
                        'overall_discount_per_unit' => $proportionalOverallDiscount,
                        'total_discount_per_unit' => $totalDiscountPerUnit,
                        'net_unit_price' => $unitPrice - $totalDiscountPerUnit,
                        'original_qty' => $item->received_quantity,
                        'already_returned' => $alreadyReturned,
                        'max_qty' => $remainingQty,
                        'return_qty' => 0,
                        'return_reason' => 'damaged',
                    ];
                }
            }
        }

        $this->loadPurchaseOrderProductsForSearch();
        $this->searchSupplier = '';
    }

    /** 📊 Calculate Overall Discount Per Item */
    private function calculateOverallDiscountPerItem()
    {
        if (!$this->selectedPurchaseOrder) {
            $this->overallDiscountPerItem = 0;
            return;
        }

        $totalQuantity = $this->selectedPurchaseOrder->items->sum('received_quantity');
        $overallDiscount = $this->selectedPurchaseOrder->discount_amount ?? 0;

        $this->overallDiscountPerItem = $totalQuantity > 0 ? ($overallDiscount / $totalQuantity) : 0;
    }

    /** 📜 Load Previous Returns */
    private function loadPreviousReturns()
    {
        if (!$this->selectedPurchaseOrder) {
            $this->previousReturns = [];
            return;
        }

        $this->previousReturns = ReturnSupplierModel::where('purchase_order_id', $this->selectedPurchaseOrder->id)
            ->with('product')
            ->get()
            ->groupBy(function ($return) {
                // Group by product_id and variant_id combination
                return $return->product_id . '_' . ($return->variant_id ?? 'no_variant');
            })
            ->map(function ($returns) {
                $firstReturn = $returns->first();
                $productName = $firstReturn->product->name ?? 'Unknown';

                // Add variant value to name if exists
                if ($firstReturn->variant_value) {
                    $productName .= ' (' . $firstReturn->variant_value . ')';
                }

                return [
                    'product_name' => $productName,
                    'total_returned' => $returns->sum('return_quantity'),
                    'total_amount' => $returns->sum('total_amount'),
                    'returns' => $returns->map(function ($return) {
                        return [
                            'quantity' => $return->return_quantity,
                            'amount' => $return->total_amount,
                            'reason' => $return->return_reason,
                            'date' => $return->created_at->format('Y-m-d H:i'),
                        ];
                    })->toArray()
                ];
            })
            ->toArray();
    }

    /** 🔢 Get Already Returned Quantity */
    private function getAlreadyReturnedQuantity($productId, $variantValue = null)
    {
        if (!$this->selectedPurchaseOrder) return 0;

        $query = ReturnSupplierModel::where('purchase_order_id', $this->selectedPurchaseOrder->id)
            ->where('product_id', $productId);

        // Filter by variant_value if provided
        if ($variantValue) {
            $query->where('variant_value', $variantValue);
        } else {
            $query->whereNull('variant_value');
        }

        return $query->sum('return_quantity');
    }

    /** 👁️ View Purchase Order Details in Modal */
    public function viewPurchaseOrder($purchaseOrderId)
    {
        $purchaseOrder = PurchaseOrder::with(['items.product', 'supplier'])->find($purchaseOrderId);

        if ($purchaseOrder) {
            $overallDiscount = $purchaseOrder->discount_amount ?? 0;
            $totalQty = $purchaseOrder->items->sum('quantity');
            $discountPerItem = $totalQty > 0 ? ($overallDiscount / $totalQty) : 0;

            $this->purchaseOrderModalData = [
                'order_code' => $purchaseOrder->order_code,
                'supplier_name' => $purchaseOrder->supplier->name,
                'date' => $purchaseOrder->created_at->format('Y-m-d H:i:s'),
                'total_amount' => $purchaseOrder->total_amount,
                'overall_discount' => $overallDiscount,
                'items' => $purchaseOrder->items->map(function ($item) use ($discountPerItem) {
                    $unitPrice = $item->unit_price;
                    $itemDiscountPercentage = $item->discount ?? 0;
                    $itemDiscountAmount = ($unitPrice * $itemDiscountPercentage) / 100;
                    $totalDiscountPerUnit = $itemDiscountAmount + $discountPerItem;
                    $netPrice = $unitPrice - $totalDiscountPerUnit;

                    // Build display name with variant
                    $displayName = $item->product->name;
                    if ($item->variant_value) {
                        $displayName .= ' (' . $item->variant_value . ')';
                    }

                    return [
                        'product_name' => $displayName,
                        'product_code' => $item->product->code,
                        'quantity' => $item->received_quantity,
                        'unit_price' => $unitPrice,
                        'item_discount_percentage' => $itemDiscountPercentage,
                        'item_discount_amount' => $itemDiscountAmount,
                        'overall_discount' => $discountPerItem,
                        'net_price' => $netPrice,
                        'total' => $item->received_quantity * $netPrice,
                    ];
                })->toArray()
            ];
            $this->showPurchaseOrderModal = true;
            $this->dispatch('show-purchase-order-modal');
        }
    }

    /** ❌ Close Purchase Order Modal */
    public function closePurchaseOrderModal()
    {
        $this->showPurchaseOrderModal = false;
        $this->purchaseOrderModalData = null;
    }

    /** 📦 Load Products from Selected Purchase Order for Search */
    private function loadPurchaseOrderProductsForSearch()
    {
        if (empty($this->selectedPurchaseOrders)) {
            $this->purchaseOrderProductsForSearch = [];
            return;
        }

        $allProducts = collect();

        foreach ($this->selectedPurchaseOrders as $purchaseOrderId) {
            $purchaseOrder = PurchaseOrder::with(['items.product'])->find($purchaseOrderId);
            if ($purchaseOrder) {
                $products = $purchaseOrder->items->map(function ($item) use ($purchaseOrder) {
                    $alreadyReturned = $this->getAlreadyReturnedQuantity($item->product->id, $item->variant_value);
                    $remainingQty = $item->received_quantity - $alreadyReturned;

                    // Build display name with variant
                    $displayName = $item->product->name;
                    if ($item->variant_value) {
                        $displayName .= ' (' . $item->variant_value . ')';
                    }

                    return [
                        'id' => $item->product->id,
                        'variant_id' => $item->variant_id,
                        'variant_value' => $item->variant_value,
                        'name' => $displayName,
                        'code' => $item->product->code,
                        'image' => $item->product->image,
                        'unit_price' => $item->unit_price,
                        'purchase_order_id' => $purchaseOrder->id,
                        'order_code' => $purchaseOrder->order_code,
                        'max_qty' => $remainingQty,
                    ];
                });
                $allProducts = $allProducts->merge($products);
            }
        }

        // Use unique key combining product_id and variant_id
        $this->purchaseOrderProductsForSearch = $allProducts->unique(function ($item) {
            return $item['id'] . '_' . ($item['variant_id'] ?? 'no_variant');
        })->values()->toArray();
    }

    /** ❌ Remove Product from Return Cart */
    public function removeFromReturn($index)
    {
        unset($this->returnItems[$index]);
        $this->returnItems = array_values($this->returnItems);
        $this->calculateTotalReturnValue();
    }

    /** 🧹 Clear Cart */
    public function clearReturnCart()
    {
        $this->returnItems = [];
        $this->totalReturnValue = 0;
    }

    /** ♻️ Auto-update total when quantities change */
    public function updatedReturnItems()
    {
        $this->calculateTotalReturnValue();
    }

    /** 💰 Calculate Total Return Value */
    private function calculateTotalReturnValue()
    {
        $this->totalReturnValue = collect($this->returnItems)->sum(
            fn($item) => $item['return_qty'] * $item['net_unit_price']
        );
    }

    /** ✅ Validate before showing confirmation */
    public function processReturn()
    {
        $this->calculateTotalReturnValue();

        if (empty($this->returnItems) || !$this->selectedPurchaseOrder) {
            $this->js("Swal.fire('Error!', 'Please select items for return.', 'error')");
            return;
        }

        $hasReturnItems = false;
        foreach ($this->returnItems as $item) {
            if ($item['return_qty'] < 0) {
                $this->js("Swal.fire('Error!', 'Return quantity cannot be negative for " . $item['name'] . "', 'error')");
                return;
            }

            if (isset($item['return_qty']) && $item['return_qty'] > 0) {
                if ($item['return_qty'] > $item['max_qty']) {
                    $this->js("Swal.fire('Error!', 'Invalid return quantity for " . $item['name'] . ". Maximum available: " . $item['max_qty'] . "', 'error')");
                    return;
                }
                $hasReturnItems = true;
            }
        }

        if (!$hasReturnItems) {
            $this->dispatch('alert', ['message' => 'Please enter at least one return quantity.']);
            return;
        }

        $this->dispatch('show-return-modal');
    }

    /** 💾 Confirm Return & Save to Database */
    public function confirmReturn()
    {
        $this->calculateTotalReturnValue();

        if (empty($this->returnItems) || !$this->selectedSupplier || !$this->selectedPurchaseOrder) return;

        $itemsToReturn = array_filter($this->returnItems, function ($item) {
            return isset($item['return_qty']) && $item['return_qty'] > 0;
        });

        if (empty($itemsToReturn)) {
            $this->dispatch('alert', ['message' => 'No valid return quantities entered.']);
            return;
        }

        DB::transaction(function () use ($itemsToReturn) {
            $totalReturnAmount = 0;

            foreach ($itemsToReturn as $item) {
                $returnAmount = $item['return_qty'] * $item['net_unit_price'];
                $totalReturnAmount += $returnAmount;

                ReturnSupplierModel::create([
                    'purchase_order_id' => $this->selectedPurchaseOrder->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'variant_value' => $item['variant_value'] ?? null,
                    'return_quantity' => $item['return_qty'],
                    'unit_price' => $item['net_unit_price'],
                    'total_amount' => $returnAmount,
                    'return_reason' => $item['return_reason'] ?? 'damaged',
                    'notes' => 'Supplier return processed via system',
                ]);

                $this->updateProductStock(
                    $item['product_id'],
                    $item['variant_value'] ?? null,
                    $item['return_qty'],
                    $item['return_reason']
                );
            }

            // Handle return amount - reduce dues or add to overpayment
            $this->processReturnPaymentAdjustment($totalReturnAmount);
        });

        $this->clearReturnCart();
        $this->dispatch('alert', ['message' => 'Supplier return processed successfully!']);
        $this->dispatch('close-return-modal');
        $this->dispatch('reload-page');
    }

    /** 💰 Process Return Payment Adjustment - Reduce dues or add overpayment */
    private function processReturnPaymentAdjustment($totalReturnAmount)
    {
        if (!$this->selectedPurchaseOrder || $totalReturnAmount <= 0) return;

        $order = PurchaseOrder::find($this->selectedPurchaseOrder->id);
        if (!$order) return;

        $dueAmount = $order->due_amount ?? 0;
        $remainingReturnAmount = $totalReturnAmount;

        // First, reduce the due amount of this order
        if ($dueAmount > 0) {
            $reduction = min($dueAmount, $remainingReturnAmount);
            $order->due_amount = max(0, $dueAmount - $reduction);
            $order->save();
            $remainingReturnAmount -= $reduction;
        }

        // If there's still remaining return amount, add it as supplier overpayment
        if ($remainingReturnAmount > 0) {
            $supplier = ProductSupplier::find($order->supplier_id);
            if ($supplier) {
                $supplier->addOverpayment($remainingReturnAmount);
            }
        }
    }

    /** 📈 Update Product Stock (Reduce stock for supplier returns) */
    private function updateProductStock($productId, $variantValue, $quantity, $reason)
    {
        // Find the correct stock record based on product and variant_value
        $query = ProductStock::where('product_id', $productId);

        if ($variantValue) {
            // Match by variant_value which is the actual variant identifier ("4", "4.5", etc)
            $query->where('variant_value', $variantValue);
        } else {
            // For non-variant products
            $query->whereNull('variant_value');
        }

        $stock = $query->first();

        if ($stock) {
            // Validate available stock before reduction
            if ($stock->available_stock < $quantity) {
                throw new \Exception("Insufficient stock available for this variant. Available: {$stock->available_stock}, Requested: {$quantity}");
            }

            // For supplier returns, we reduce the stock
            $stock->available_stock = max(0, $stock->available_stock - $quantity);

            // Track damage stock if reason is damage
            if ($reason === 'damaged') {
                $stock->damage_stock += $quantity;
            }

            $stock->updateTotals(); // Recalculate total_stock = available_stock + damage_stock
        } else {
            throw new \Exception("Stock record not found for product ID: {$productId}, variant: " . ($variantValue ?? 'none'));
        }
    }

    /** 🔄 Reset Return Data */
    private function resetReturnData()
    {
        $this->selectedPurchaseOrder = null;
        $this->selectedPurchaseOrders = [];
        $this->purchaseOrderProducts = [];
        $this->returnItems = [];
        $this->selectedProducts = [];
        $this->showReturnSection = false;
        $this->searchReturnProduct = '';
        $this->availableProducts = [];
        $this->purchaseOrderProductsForSearch = [];
        $this->totalReturnValue = 0;
        $this->overallDiscountPerItem = 0;
        $this->previousReturns = [];
        $this->returnReason = 'damaged';
    }

    public function render()
    {
        return view('livewire.admin.return-supplier')->layout($this->layout);
    }
}
