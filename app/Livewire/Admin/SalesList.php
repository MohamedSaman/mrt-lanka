<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithDynamicLayout;
use App\Models\Customer;
use App\Models\ProductStock;
use App\Models\ReturnsProduct;
use App\Models\Sale;
use App\Models\SaleItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Sales Management')]
class SalesList extends Component
{
    use WithDynamicLayout;
    use WithPagination;

    public $search = '';

    public $selectedSale = null;

    public $paymentStatusFilter = 'all';

    public $dateFilter = '';

    public $showViewModal = false;

    public $showEditModal = false;

    public $showDeleteModal = false;

    public $showReturnModal = false;

    // Edit form properties
    public $editSaleId;

    public $editCustomerId;

    public $editPaymentStatus;

    public $editNotes;

    public $editDueAmount;

    public $editPaidAmount;

    public $editPayBalanceAmount = 0;

    // Return properties
    public $returnItems = [];

    public $totalReturnValue = 0;

    public $perPage = 10;

    public function mount()
    {
        // Initialize component
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedPaymentStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedDateFilter()
    {
        $this->resetPage();
    }

    public function viewSale($saleId)
    {
        $query = Sale::with([
            'customer',
            'items',
            'user',
            'returns' => function ($q) {
                $q->with('product');
            },
        ])->where('sale_type', $this->getSaleType());

        if ($this->isStaff()) {
            $query->where('user_id', Auth::id());
        }

        $this->selectedSale = $query->find($saleId);

        $this->showViewModal = true;
        $this->dispatch('showModal', 'viewModal');
    }

    public function editSale($saleId)
    {
        $query = Sale::with(['customer'])->where('sale_type', $this->getSaleType());
        if ($this->isStaff()) {
            $query->where('user_id', Auth::id());
        }
        $sale = $query->find($saleId);

        if ($sale) {
            $this->editSaleId = $sale->id;
            $this->editCustomerId = $sale->customer_id;
            $this->editPaymentStatus = $sale->payment_status;
            $this->editNotes = $sale->notes;
            $this->editDueAmount = $sale->due_amount;
            $this->editPaidAmount = $sale->total_amount - $sale->due_amount;
            $this->editPayBalanceAmount = 0;

            $this->showEditModal = true;
            $this->dispatch('showModal', 'editModal');
        }
    }

    // Return Product Functionality
    public function returnSale($saleId)
    {
        $query = Sale::with(['items.product', 'customer'])->where('sale_type', $this->getSaleType());
        if ($this->isStaff()) {
            $query->where('user_id', Auth::id());
        }
        $this->selectedSale = $query->find($saleId);

        if ($this->selectedSale) {
            // Initialize return items from sale items
            $this->returnItems = [];
            foreach ($this->selectedSale->items as $item) {
                $alreadyReturned = ReturnsProduct::where('sale_id', $this->selectedSale->id)
                    ->where('product_id', $item->product_id)
                    ->sum('return_quantity');

                $maxQty = max(0, $item->quantity - $alreadyReturned);

                $unitPrice = (float) $item->unit_price;
                $discountPerUnit = (float) ($item->discount_per_unit ?? 0);
                $soldPrice = $item->quantity > 0
                    ? (float) ($item->total / $item->quantity)
                    : max(0, $unitPrice - $discountPerUnit);

                $this->returnItems[] = [
                    'product_id' => $item->product_id,
                    'name' => $item->product?->name ?? $item->product_name,
                    'unit_price' => $soldPrice,
                    'original_unit_price' => $unitPrice,
                    'discount_per_unit' => $discountPerUnit,
                    'max_qty' => $maxQty,
                    'return_qty' => 0,
                ];
            }

            $this->showReturnModal = true;
            $this->dispatch('showModal', 'returnModal');
        }
    }

    public function updatedReturnItems()
    {
        $this->calculateTotalReturnValue();
    }

    private function calculateTotalReturnValue()
    {
        $this->totalReturnValue = collect($this->returnItems)->sum(
            fn ($item) => $item['return_qty'] * $item['unit_price']
        );
    }

    public function removeFromReturn($index)
    {
        unset($this->returnItems[$index]);
        $this->returnItems = array_values($this->returnItems);
        $this->calculateTotalReturnValue();
    }

    public function clearReturnCart()
    {
        $this->returnItems = [];
        $this->totalReturnValue = 0;
    }

    public function processReturn()
    {
        $this->calculateTotalReturnValue();

        if (empty($this->returnItems) || ! $this->selectedSale) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Please select items for return.']);

            return;
        }

        // Check if at least one item has a return quantity > 0
        $hasReturnItems = false;
        foreach ($this->returnItems as $item) {
            if (isset($item['return_qty']) && $item['return_qty'] > 0) {
                if ($item['return_qty'] > $item['max_qty']) {
                    $this->dispatch('showToast', ['type' => 'error', 'message' => 'Invalid return quantity for '.$item['name']]);

                    return;
                }
                $hasReturnItems = true;
            }
        }

        if (! $hasReturnItems) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Please enter at least one return quantity.']);

            return;
        }

        $this->confirmReturn();
    }

    public function confirmReturn()
    {
        try {
            DB::transaction(function () {
                // Filter only items with return_qty > 0
                $itemsToReturn = array_filter($this->returnItems, function ($item) {
                    return isset($item['return_qty']) && $item['return_qty'] > 0;
                });

                foreach ($itemsToReturn as $item) {
                    ReturnsProduct::create([
                        'sale_id' => $this->selectedSale->id,
                        'product_id' => $item['product_id'],
                        'return_quantity' => $item['return_qty'],
                        'selling_price' => $item['unit_price'],
                        'total_amount' => $item['return_qty'] * $item['unit_price'],
                        'notes' => 'Customer return processed via system',
                    ]);

                    // Update stock (increase available stock)
                    $this->updateProductStock($item['product_id'], $item['return_qty']);
                }

                // Recalculate sale totals based on actual sold prices
                $originalSubtotal = SaleItem::where('sale_id', $this->selectedSale->id)
                    ->get()
                    ->sum(function ($item) {
                        return (float) $item->total;
                    });

                $allReturnsAmount = ReturnsProduct::where('sale_id', $this->selectedSale->id)->sum('total_amount');
                $newSubtotal = max(0, $originalSubtotal - $allReturnsAmount);

                $additionalDiscountAmount = 0;
                if ($this->selectedSale->additional_discount_type === 'percentage' && $this->selectedSale->additional_discount_percentage > 0) {
                    $additionalDiscountAmount = ($newSubtotal * $this->selectedSale->additional_discount_percentage) / 100;
                } elseif ($this->selectedSale->additional_discount_type === 'fixed') {
                    if ($originalSubtotal > 0) {
                        $origAdditionalDiscount = max(0, (float) $this->selectedSale->subtotal - (float) $this->selectedSale->total_amount);
                        $additionalDiscountAmount = min($newSubtotal, round(($origAdditionalDiscount * $newSubtotal) / $originalSubtotal, 2));
                    }
                }

                $newTotal = max(0, $newSubtotal - $additionalDiscountAmount);
                $previousTotal = (float) $this->selectedSale->total_amount;
                $totalReduction = max(0, $previousTotal - $newTotal);
                $newDue = max(0, (float) $this->selectedSale->due_amount - $totalReduction);

                $returnedQtyByProduct = ReturnsProduct::where('sale_id', $this->selectedSale->id)
                    ->get()
                    ->groupBy('product_id');

                $remainingItemDiscounts = SaleItem::where('sale_id', $this->selectedSale->id)
                    ->get()
                    ->sum(function ($item) use ($returnedQtyByProduct) {
                        $returned = $returnedQtyByProduct->has($item->product_id)
                            ? $returnedQtyByProduct->get($item->product_id)->sum('return_quantity')
                            : 0;
                        $remQty = max(0, $item->quantity - $returned);

                        return (float) ($item->discount_per_unit ?? 0) * $remQty;
                    });

                $this->selectedSale->update([
                    'subtotal' => $newSubtotal,
                    'discount_amount' => $remainingItemDiscounts + $additionalDiscountAmount,
                    'total_amount' => $newTotal,
                    'due_amount' => $newDue,
                    'payment_status' => $newTotal <= 0.01 ? 'paid' : ($newDue <= 0.01 ? 'paid' : ($newDue < $newTotal ? 'partial' : 'pending')),
                ]);

                if ($this->selectedSale->customer_id) {
                    \App\Services\CustomerBalanceService::syncCustomerBalance($this->selectedSale->customer_id);
                }
            });

            $this->showReturnModal = false;
            $this->clearReturnCart();
            $this->dispatch('hideModal', 'returnModal');
            $this->dispatch('showToast', ['type' => 'success', 'message' => 'Return processed successfully!']);

            // Refresh the selected sale to show updated returns
            if ($this->selectedSale) {
                $this->selectedSale->refresh();
            }
        } catch (\Exception $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Error processing return: '.$e->getMessage()]);
        }
    }

    private function updateProductStock($productId, $quantity)
    {
        $stock = ProductStock::where('product_id', $productId)->first();

        if ($stock) {
            $stock->available_stock += $quantity;
            $stock->updateTotals();
        } else {
            ProductStock::create([
                'product_id' => $productId,
                'available_stock' => $quantity,
                'damage_stock' => 0,
                'total_stock' => $quantity,
                'sold_count' => 0,
                'restocked_quantity' => 0,
            ]);
        }
    }

    public function updatedEditPaymentStatus($value)
    {
        if ($this->editSaleId) {
            $sale = Sale::find($this->editSaleId);
            if ($sale) {
                if ($value === 'paid') {
                    $this->editPaidAmount = $sale->total_amount;
                    $this->editDueAmount = 0;
                    $this->editPayBalanceAmount = $sale->due_amount;
                } elseif ($value === 'pending') {
                    $this->editPaidAmount = 0;
                    $this->editDueAmount = $sale->total_amount;
                    $this->editPayBalanceAmount = 0;
                } else {
                    $this->editPayBalanceAmount = 0;
                }
            }
        }
    }

    public function updatedEditPayBalanceAmount($value)
    {
        if ($this->editSaleId) {
            $sale = Sale::find($this->editSaleId);
            if ($sale) {
                $value = floatval($value);
                $maxPayable = $sale->due_amount;

                if ($value > $maxPayable) {
                    $this->editPayBalanceAmount = $maxPayable;
                    $value = $maxPayable;
                }

                if ($value < 0) {
                    $this->editPayBalanceAmount = 0;
                    $value = 0;
                }

                $this->editPaidAmount = $sale->total_amount - $sale->due_amount + $value;
                $this->editDueAmount = $sale->due_amount - $value;

                if ($this->editDueAmount <= 0) {
                    $this->editPaymentStatus = 'paid';
                } elseif ($value > 0) {
                    $this->editPaymentStatus = 'partial';
                } else {
                    $this->editPaymentStatus = 'pending';
                }
            }
        }
    }

    public function updateSale()
    {
        $this->validate([
            'editPaymentStatus' => 'required|in:paid,partial,pending',
            'editDueAmount' => 'required|numeric|min:0',
            'editPaidAmount' => 'required|numeric|min:0',
            'editPayBalanceAmount' => 'required|numeric|min:0',
        ]);

        try {
            $sale = Sale::find($this->editSaleId);

            if ($sale) {
                $totalAmount = $sale->total_amount;
                $paidAmount = $this->editPaidAmount;
                $dueAmount = $this->editDueAmount;

                if (($paidAmount + $dueAmount) != $totalAmount) {
                    $dueAmount = $totalAmount - $paidAmount;
                }

                $sale->update([
                    'customer_id' => $this->editCustomerId,
                    'payment_status' => $this->editPaymentStatus,
                    'notes' => $this->editNotes,
                    'due_amount' => $dueAmount,
                    'payment_type' => $this->editPaymentStatus === 'paid' ? 'full' : 'partial',
                ]);

                $this->showEditModal = false;
                $this->resetEditForm();
                $this->dispatch('hideModal', 'editModal');
                $this->dispatch('showToast', ['type' => 'success', 'message' => 'Sale updated successfully!']);
            }
        } catch (\Exception $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Error updating sale: '.$e->getMessage()]);
        }
    }

    public function payFullBalance()
    {
        if ($this->editSaleId) {
            $sale = Sale::find($this->editSaleId);
            if ($sale) {
                $this->editPayBalanceAmount = $sale->due_amount;
                $this->updatedEditPayBalanceAmount($sale->due_amount);
            }
        }
    }

    public function resetPayBalance()
    {
        $this->editPayBalanceAmount = 0;
        $this->updatedEditPayBalanceAmount(0);
    }

    public function deleteSale($saleId)
    {
        $query = Sale::where('sale_type', $this->getSaleType());
        if ($this->isStaff()) {
            $query->where('user_id', Auth::id());
        }
        $this->selectedSale = $query->find($saleId);
        $this->showDeleteModal = true;
        $this->dispatch('showModal', 'deleteModal');
    }

    public function confirmDelete()
    {
        try {
            DB::transaction(function () {
                $saleItems = SaleItem::where('sale_id', $this->selectedSale->id)->get();

                foreach ($saleItems as $item) {
                    $productStock = ProductStock::where('product_id', $item->product_id)->first();
                    if ($productStock) {
                        $productStock->available_stock += $item->quantity;
                        if ($productStock->sold_count >= $item->quantity) {
                            $productStock->sold_count -= $item->quantity;
                        }
                        $productStock->save();
                    }
                }

                \App\Models\Payment::where('sale_id', $this->selectedSale->id)->delete();
                SaleItem::where('sale_id', $this->selectedSale->id)->delete();

                $this->selectedSale->delete();
            });

            $this->showDeleteModal = false;
            $this->selectedSale = null;
            $this->dispatch('hideModal', 'deleteModal');
            $this->dispatch('showToast', ['type' => 'success', 'message' => 'Sale deleted successfully!']);
        } catch (\Exception $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Error deleting sale: '.$e->getMessage()]);
        }
    }

    public function printInvoice($saleId)
    {
        $sale = \App\Models\Sale::with(['customer', 'items', 'payments', 'returns' => function ($q) {
            $q->with('product');
        }])->find($saleId);
        if (! $sale) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Sale not found.']);

            return;
        }
        // Store sale ID in session for print route
        session(['print_sale_id' => $sale->id]);
        // Open print page in new window
        $printUrl = route('admin.print.sale', $sale->id);
        $this->js("window.open('$printUrl', '_blank', 'width=800,height=600');");
    }

    public function downloadInvoice($saleId)
    {
        $query = Sale::with(['customer', 'items', 'returns' => function ($q) {
            $q->with('product');
        }])->where('sale_type', $this->getSaleType());

        if ($this->isStaff()) {
            $query->where('user_id', Auth::id());
        }

        $sale = $query->find($saleId);

        if (! $sale) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Sale not found.']);

            return;
        }

        try {
            $sale->paid_amount = $sale->total_amount - $sale->due_amount;
            $sale->balance_amount = $sale->due_amount;

            $pdf = PDF::loadView('admin.sales.invoice', compact('sale'));

            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('dpi', 150);
            $pdf->setOption('defaultFont', 'sans-serif');

            return response()->streamDownload(
                function () use ($pdf) {
                    echo $pdf->output();
                },
                'invoice-'.$sale->invoice_number.'.pdf'
            );
        } catch (\Exception $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Failed to generate PDF: '.$e->getMessage()]);
        }
    }

    public function closeModals()
    {
        $this->showViewModal = false;
        $this->showEditModal = false;
        $this->showDeleteModal = false;
        $this->showReturnModal = false;
        $this->selectedSale = null;
        $this->resetEditForm();
        $this->clearReturnCart();

        $this->dispatch('hideModal', 'viewModal');
        $this->dispatch('hideModal', 'editModal');
        $this->dispatch('hideModal', 'deleteModal');
        $this->dispatch('hideModal', 'returnModal');
    }

    private function resetEditForm()
    {
        $this->editSaleId = null;
        $this->editCustomerId = null;
        $this->editPaymentStatus = '';
        $this->editNotes = '';
        $this->editDueAmount = 0;
        $this->editPaidAmount = 0;
        $this->editPayBalanceAmount = 0;
    }

    public function getSalesProperty()
    {
        $query = Sale::with(['customer', 'user', 'items', 'returns']);

        // Filter by sale_type and user_id based on role
        if ($this->isStaff()) {
            // Staff sees only their own sales
            $query->where('user_id', Auth::id())
                ->where('sale_type', 'staff');
        } else {
            // Admin sees admin sales
            $query->where('sale_type', 'admin');
        }

        return $query->when($this->search, function ($query) {
            $query->where(function ($q) {
                $q->where('invoice_number', 'like', '%'.$this->search.'%')
                    ->orWhere('sale_id', 'like', '%'.$this->search.'%')
                    ->orWhereHas('customer', function ($customerQuery) {
                        $customerQuery->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('phone', 'like', '%'.$this->search.'%');
                    });
            });
        })
            ->when($this->paymentStatusFilter !== 'all', function ($query) {
                $query->where('payment_status', $this->paymentStatusFilter);
            })
            ->when($this->dateFilter, function ($query) {
                $query->whereDate('created_at', $this->dateFilter);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function getSalesStatsProperty()
    {
        // Base query filtered by role
        if ($this->isStaff()) {
            $baseSales = Sale::where('sale_type', 'staff')->where('user_id', Auth::id());
            $todaySales = Sale::where('sale_type', 'staff')->where('user_id', Auth::id())->whereDate('created_at', today());
        } else {
            $baseSales = Sale::where('sale_type', 'admin');
            $todaySales = Sale::where('sale_type', 'admin')->whereDate('created_at', today());
        }

        return [
            'total_sales' => (clone $baseSales)->count(),
            'total_amount' => (clone $baseSales)->sum('total_amount'),
            'pending_payments' => (clone $baseSales)->where('payment_status', 'pending')->sum('due_amount'),
            'partial_payments' => (clone $baseSales)->where('payment_status', 'partial')->sum('due_amount'),
            'paid_amount' => (clone $baseSales)->where('payment_status', 'paid')->sum('total_amount'),
            'today_sales' => $todaySales->count(),
            'today_amount' => (clone $todaySales)->sum('total_amount'),
        ];
    }

    public function getCustomersProperty()
    {
        return Customer::orderBy('name')->get();
    }

    public function markAsPaid($saleId)
    {
        try {
            $query = Sale::where('sale_type', $this->getSaleType());
            if ($this->isStaff()) {
                $query->where('user_id', Auth::id());
            }
            $sale = $query->find($saleId);

            if ($sale) {
                $sale->update([
                    'payment_status' => 'paid',
                    'due_amount' => 0,
                    'payment_type' => 'full',
                ]);

                $this->dispatch('showToast', ['type' => 'success', 'message' => 'Sale marked as paid successfully!']);
            }
        } catch (\Exception $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Error updating sale: '.$e->getMessage()]);
        }
    }

    public function markAsPending($saleId)
    {
        try {
            $query = Sale::where('sale_type', $this->getSaleType());
            if ($this->isStaff()) {
                $query->where('user_id', Auth::id());
            }
            $sale = $query->find($saleId);

            if ($sale) {
                $sale->update([
                    'payment_status' => 'pending',
                    'due_amount' => $sale->total_amount,
                    'payment_type' => 'partial',
                ]);

                $this->dispatch('showToast', ['type' => 'success', 'message' => 'Sale marked as pending successfully!']);
            }
        } catch (\Exception $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Error updating sale: '.$e->getMessage()]);
        }
    }

    public function render()
    {
        return view('livewire.admin.sales-list', [
            'sales' => $this->sales,
            'stats' => $this->salesStats,
            'customers' => $this->customers,
        ])->layout($this->layout);
    }
}
