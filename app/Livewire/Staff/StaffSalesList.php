<?php

namespace App\Livewire\Staff;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\ProductStock;
use App\Models\ReturnsProduct;
use App\Models\Sale;
use App\Models\SaleItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff')]
#[Title('My Sales Management')]
class StaffSalesList extends Component
{
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
        // Only show sales created by this staff member
        $this->selectedSale = Sale::with([
            'customer',
            'items',
            'user',
            'returns' => function ($q) {
                $q->with('product');
            },
        ])
            ->where('user_id', Auth::id())
            ->find($saleId);

        if ($this->selectedSale) {
            $this->showViewModal = true;
            $this->dispatch('showModal', 'viewModal');
        }
    }

    public function editSale($saleId)
    {
        // Only allow editing sales created by this staff member
        $sale = Sale::with(['customer'])
            ->where('user_id', Auth::id())
            ->find($saleId);

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

    public function updateSale()
    {
        $this->validate([
            'editCustomerId' => 'required|exists:customers,id',
            'editPaymentStatus' => 'required|in:paid,partial,pending',
            'editPayBalanceAmount' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::transaction(function () {
                $sale = Sale::where('user_id', Auth::id())->find($this->editSaleId);

                if ($sale) {
                    $newDueAmount = max(0, $this->editDueAmount - $this->editPayBalanceAmount);

                    $sale->update([
                        'customer_id' => $this->editCustomerId,
                        'payment_status' => $this->editPaymentStatus,
                        'notes' => $this->editNotes,
                        'due_amount' => $newDueAmount,
                    ]);

                    $this->showEditModal = false;
                    $this->dispatch('closeModal', 'editModal');
                    $this->dispatch('showToast', ['type' => 'success', 'message' => 'Sale updated successfully!']);
                }
            });
        } catch (\Exception $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Error updating sale: '.$e->getMessage()]);
        }
    }

    // Return Product Functionality
    public function returnSale($saleId)
    {
        $this->selectedSale = Sale::with(['items.product', 'customer'])
            ->where('user_id', Auth::id())
            ->find($saleId);

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

                foreach ($itemsToReturn as $returnItem) {
                    $returnAmount = $returnItem['return_qty'] * $returnItem['unit_price'];

                    // Create return record
                    ReturnsProduct::create([
                        'sale_id' => $this->selectedSale->id,
                        'product_id' => $returnItem['product_id'],
                        'return_quantity' => $returnItem['return_qty'],
                        'selling_price' => $returnItem['unit_price'],
                        'total_amount' => $returnAmount,
                        'notes' => 'Customer return',
                    ]);

                    // Update stock
                    $productStock = ProductStock::where('product_id', $returnItem['product_id'])->first();
                    if ($productStock) {
                        $productStock->available_stock += $returnItem['return_qty'];
                        $productStock->updateTotals();
                    }
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

                $this->showReturnModal = false;
                $this->dispatch('closeModal', 'returnModal');
                $this->dispatch('showToast', ['type' => 'success', 'message' => 'Product returned successfully!']);

                $this->returnItems = [];
                $this->totalReturnValue = 0;
            });
        } catch (\Exception $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Error processing return: '.$e->getMessage()]);
        }
    }

    public function deleteSale($saleId)
    {
        $sale = Sale::where('user_id', Auth::id())->find($saleId);

        if ($sale) {
            $this->selectedSale = $sale;
            $this->showDeleteModal = true;
            $this->dispatch('showModal', 'deleteModal');
        }
    }

    public function confirmDelete()
    {
        try {
            DB::transaction(function () {
                if ($this->selectedSale) {
                    // Store sale details before deletion
                    $saleDueAmount = $this->selectedSale->due_amount ?? 0;
                    $customerId = $this->selectedSale->customer_id;

                    // Restore stock for all items
                    foreach ($this->selectedSale->items as $item) {
                        $productStock = ProductStock::where('product_id', $item->product_id)->first();
                        if ($productStock) {
                            $productStock->increment('quantity', $item->quantity);
                        }
                    }

                    // Delete sale items
                    $this->selectedSale->items()->delete();

                    // Delete returns if any
                    $this->selectedSale->returns()->delete();

                    // Delete payments
                    Payment::where('sale_id', $this->selectedSale->id)->delete();

                    // Delete sale
                    $this->selectedSale->delete();

                    // Update customer's due amount and total due
                    if ($customerId && $saleDueAmount > 0) {
                        $customer = \App\Models\Customer::find($customerId);
                        if ($customer) {
                            // Reduce due amount
                            $customer->due_amount = max(0, ($customer->due_amount ?? 0) - $saleDueAmount);
                            // Recalculate total due
                            $customer->total_due = ($customer->opening_balance ?? 0) + $customer->due_amount;
                            $customer->save();
                        }
                    }

                    $this->showDeleteModal = false;
                    $this->dispatch('closeModal', 'deleteModal');
                    $this->dispatch('showToast', ['type' => 'success', 'message' => 'Sale deleted successfully and customer due amount updated!']);
                }
            });
        } catch (\Exception $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Error deleting sale: '.$e->getMessage()]);
        }
    }

    public function printInvoice($saleId)
    {
        $sale = Sale::with(['customer', 'items', 'payments', 'returns' => function ($q) {
            $q->with('product');
        }])->where('user_id', Auth::id())->find($saleId);

        if (! $sale) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Sale not found.']);

            return;
        }

        // Open print page in new window
        $printUrl = '/staff/print/sale/'.$sale->id;
        $this->js("window.open('$printUrl', '_blank', 'width=800,height=600');");
    }

    public function downloadInvoice($saleId)
    {
        $sale = Sale::with(['customer', 'items', 'user', 'returns' => function ($q) {
            $q->with('product');
        }])
            ->where('user_id', Auth::id())
            ->find($saleId);

        if ($sale) {
            $pdf = Pdf::loadView('admin.sales.invoice', compact('sale'));
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('dpi', 150);
            $pdf->setOption('defaultFont', 'sans-serif');

            return response()->streamDownload(
                fn () => print ($pdf->output()),
                'invoice-'.$sale->invoice_number.'.pdf'
            );
        }
    }

    public function closeModal()
    {
        $this->showViewModal = false;
        $this->showEditModal = false;
        $this->showDeleteModal = false;
        $this->showReturnModal = false;
        $this->selectedSale = null;
        $this->returnItems = [];
        $this->totalReturnValue = 0;
    }

    public function markAsPaid($saleId)
    {
        try {
            $sale = Sale::where('user_id', Auth::id())->find($saleId);

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
            $sale = Sale::where('user_id', Auth::id())->find($saleId);

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

    public function getSalesProperty()
    {
        $query = Sale::with(['customer', 'user'])
            ->where('user_id', Auth::id()); // Only this staff member's sales

        // Apply search filter
        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('invoice_number', 'like', '%'.$this->search.'%')
                    ->orWhereHas('customer', function ($q) {
                        $q->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('phone', 'like', '%'.$this->search.'%');
                    });
            });
        }

        // Apply payment status filter
        if ($this->paymentStatusFilter !== 'all') {
            $query->where('payment_status', $this->paymentStatusFilter);
        }

        // Apply date filter
        if (! empty($this->dateFilter)) {
            $query->whereDate('created_at', $this->dateFilter);
        }

        return $query->orderBy('created_at', 'desc')->paginate($this->perPage);
    }

    public function getSalesStatsProperty()
    {
        $userId = Auth::id();

        return [
            'total_sales' => Sale::where('user_id', $userId)->count(),
            'total_amount' => Sale::where('user_id', $userId)->sum('total_amount'),
            'pending_payments' => Sale::where('user_id', $userId)
                ->where('payment_status', '!=', 'paid')
                ->sum('due_amount'),
            'today_sales' => Sale::where('user_id', $userId)
                ->whereDate('created_at', today())
                ->count(),
        ];
    }

    public function getCustomersProperty()
    {
        // Get customers who have purchased from this staff member
        return Customer::whereHas('sales', function ($q) {
            $q->where('user_id', Auth::id());
        })->orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.staff.staff-sales-list', [
            'sales' => $this->sales,
            'stats' => $this->salesStats,
            'customers' => $this->customers,
        ]);
    }
}
