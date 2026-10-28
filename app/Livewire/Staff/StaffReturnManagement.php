<?php

namespace App\Livewire\Staff;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\StaffProduct;
use App\Models\StaffReturn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff')]
#[Title('Customer Returns')]
class StaffReturnManagement extends Component
{
    use WithPagination;

    // Search and Selection
    public $searchCustomer = '';

    public $selectedCustomer = null;

    public $selectedInvoice = null;

    public $showReturnSection = false;

    // Return Data
    public $returnItems = [];

    public $totalReturnValue = 0;

    public $previousReturns = [];

    // Modal Data
    public $invoiceModalData = null;

    // Customers and Invoices
    public $customers = [];

    public $customerInvoices = [];

    /** 🔍 Search Customer or Invoice - Livewire lifecycle hook */
    public function updatedSearchCustomer()
    {
        if (strlen($this->searchCustomer) >= 2) {
            $staffId = Auth::id();

            $this->customers = Customer::where('user_id', $staffId)
                ->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->searchCustomer.'%')
                        ->orWhere('phone', 'like', '%'.$this->searchCustomer.'%')
                        ->orWhere('email', 'like', '%'.$this->searchCustomer.'%');
                })
                ->limit(10)
                ->get();

            $this->customerInvoices = Sale::where('user_id', $staffId)
                ->where('invoice_number', 'like', '%'.$this->searchCustomer.'%')
                ->latest()
                ->limit(5)
                ->get();
        } else {
            $this->customers = [];
            $this->customerInvoices = [];
        }
    }

    /** ♻️ Auto-update total when quantities change */
    public function updatedReturnItems()
    {
        $this->calculateTotalReturnValue();
    }

    public function selectCustomer($customerId)
    {
        $this->selectedCustomer = Customer::find($customerId);
        $this->searchCustomer = '';
        $this->loadCustomerInvoices();
    }

    public function loadCustomerInvoices()
    {
        if ($this->selectedCustomer) {
            $this->customerInvoices = Sale::where('customer_id', $this->selectedCustomer->id)
                ->where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->get();
        }
    }

    public function selectInvoiceForReturn($invoiceId)
    {
        $this->selectedInvoice = Sale::find($invoiceId);

        if ($this->selectedInvoice) {
            $this->loadReturnItems();
            $this->loadPreviousReturns();
            $this->showReturnSection = true;
        }
    }

    public function loadReturnItems()
    {
        if (! $this->selectedInvoice) {
            return;
        }

        $this->returnItems = [];
        $this->totalReturnValue = 0;

        foreach ($this->selectedInvoice->items as $item) {
            // Calculate already returned quantity
            $alreadyReturned = StaffReturn::where('sale_id', $this->selectedInvoice->id)
                ->where('product_id', $item->product_id)
                ->sum('quantity');

            $maxReturnQty = $item->quantity - $alreadyReturned;

            $unitPrice = (float) $item->unit_price;
            $discountPerUnit = (float) ($item->discount_per_unit ?? 0);
            $soldPrice = $item->quantity > 0
                ? (float) ($item->total / $item->quantity)
                : max(0, $unitPrice - $discountPerUnit);

            $this->returnItems[] = [
                'product_id' => $item->product_id,
                'name' => $item->product->name,
                'code' => $item->product_code,
                'unit_price' => $soldPrice,
                'original_unit_price' => $unitPrice,
                'discount_per_unit' => $discountPerUnit,
                'original_qty' => $item->quantity,
                'already_returned' => $alreadyReturned,
                'max_qty' => $maxReturnQty,
                'return_qty' => 0,
            ];
        }
    }

    public function loadPreviousReturns()
    {
        if (! $this->selectedInvoice) {
            return;
        }

        $returns = StaffReturn::where('sale_id', $this->selectedInvoice->id)
            ->with('product')
            ->get();

        $this->previousReturns = [];

        foreach ($returns as $return) {
            $productId = $return->product_id;

            if (! isset($this->previousReturns[$productId])) {
                $this->previousReturns[$productId] = [
                    'product_name' => $return->product->name,
                    'total_returned' => 0,
                    'total_amount' => 0,
                    'returns' => [],
                ];
            }

            $this->previousReturns[$productId]['total_returned'] += $return->quantity;
            $this->previousReturns[$productId]['total_amount'] += $return->quantity * $return->unit_price;
            $this->previousReturns[$productId]['returns'][] = [
                'quantity' => $return->quantity,
                'amount' => $return->quantity * $return->unit_price,
                'date' => $return->created_at->format('Y-m-d'),
            ];
        }
    }

    /** 💰 Calculate Total Return Value */
    private function calculateTotalReturnValue()
    {
        $this->totalReturnValue = 0;
        foreach ($this->returnItems as $item) {
            $returnQty = (int) ($item['return_qty'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $this->totalReturnValue += $returnQty * $unitPrice;
        }
    }

    public function viewInvoice($invoiceId)
    {
        $invoice = Sale::find($invoiceId);

        if ($invoice) {
            $this->invoiceModalData = [
                'invoice_number' => $invoice->invoice_number,
                'customer_name' => $invoice->customer->name,
                'date' => $invoice->created_at->format('Y-m-d'),
                'total_amount' => $invoice->total_amount,
                'items' => $invoice->items->map(function ($item) {
                    return [
                        'product_name' => $item->product_name,
                        'product_code' => $item->product_code,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total' => $item->quantity * $item->unit_price,
                    ];
                })->toArray(),
            ];

            $this->dispatch('show-invoice-modal');
        }
    }

    public function processReturn()
    {
        try {
            // Validate that we have a selected invoice
            if (! $this->selectedInvoice) {
                session()->flash('error', 'Please select an invoice first');

                return;
            }

            // Check if there are any items with return quantity > 0
            $hasReturnItems = false;
            foreach ($this->returnItems as $item) {
                $returnQty = (int) ($item['return_qty'] ?? 0);

                if ($returnQty < 0) {
                    session()->flash('error', 'Return quantity cannot be negative for '.$item['name']);

                    return;
                }

                if ($returnQty > 0) {
                    if ($returnQty > $item['max_qty']) {
                        session()->flash('error', 'Invalid return quantity for '.$item['name'].'. Maximum available: '.$item['max_qty']);

                        return;
                    }
                    $hasReturnItems = true;
                }
            }

            if (! $hasReturnItems) {
                session()->flash('error', 'Please enter a return quantity for at least one item');

                return;
            }

            // Calculate total
            $this->calculateTotalReturnValue();

            // Show confirmation modal
            $this->dispatch('show-return-modal');
        } catch (\Exception $e) {
            session()->flash('error', 'Error: '.$e->getMessage());
            Log::error('Process return error', ['message' => $e->getMessage()]);
        }
    }

    public function confirmReturn()
    {
        $this->calculateTotalReturnValue();

        if (empty($this->returnItems) || ! $this->selectedInvoice) {
            session()->flash('error', 'Invalid return data.');

            return;
        }

        $itemsToReturn = array_filter($this->returnItems, function ($item) {
            return isset($item['return_qty']) && (int) $item['return_qty'] > 0;
        });

        if (empty($itemsToReturn)) {
            session()->flash('error', 'No valid return quantities entered.');

            return;
        }

        DB::beginTransaction();
        try {
            $staffId = Auth::id();

            foreach ($itemsToReturn as $item) {
                $returnQty = (int) $item['return_qty'];
                $unitPrice = (float) $item['unit_price'];
                $totalAmount = $returnQty * $unitPrice;

                // Create staff return record
                StaffReturn::create([
                    'staff_id' => $staffId,
                    'sale_id' => $this->selectedInvoice->id,
                    'customer_id' => $this->selectedInvoice->customer_id,
                    'product_id' => $item['product_id'],
                    'quantity' => $returnQty,
                    'unit_price' => $unitPrice,
                    'total_amount' => $totalAmount,
                    'is_damaged' => false,
                    'reason' => 'Customer return from invoice #'.$this->selectedInvoice->invoice_number,
                    'status' => 'approved',
                ]);

                // Add stock back to staff allocated products (StaffProduct)
                $staffProduct = StaffProduct::where('staff_id', $staffId)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if ($staffProduct) {
                    $previousQty = $staffProduct->quantity;
                    $previousSoldQty = $staffProduct->sold_quantity;

                    // Increase available quantity
                    $staffProduct->quantity += $returnQty;

                    // Decrease sold quantity
                    $staffProduct->sold_quantity = max(0, $staffProduct->sold_quantity - $returnQty);

                    // Recalculate sold value
                    $staffProduct->sold_value = $staffProduct->sold_quantity * $staffProduct->unit_price;

                    // Update status based on quantities
                    if ($staffProduct->sold_quantity == 0) {
                        $staffProduct->status = 'assigned';
                    } else {
                        $staffProduct->status = 'partial';
                    }

                    $staffProduct->save();

                    // Log the stock change
                    $this->logStockChange(
                        $staffId,
                        $item['product_id'],
                        'return',
                        $returnQty,
                        $previousQty,
                        $staffProduct->quantity,
                        $previousSoldQty,
                        $staffProduct->sold_quantity,
                        "Product returned from invoice #{$this->selectedInvoice->invoice_number}"
                    );
                } else {
                    // If no staff product allocation exists, log a warning
                    \Illuminate\Support\Facades\Log::warning('Staff product allocation not found for return', [
                        'staff_id' => $staffId,
                        'product_id' => $item['product_id'],
                        'return_qty' => $returnQty,
                    ]);
                }
            }

            DB::commit();

            $this->dispatch('close-return-modal');
            session()->flash('message', 'Return processed successfully. Stock has been added back to your allocation.');

            // Reset
            $this->selectedInvoice = null;
            $this->showReturnSection = false;
            $this->returnItems = [];
            $this->totalReturnValue = 0;
            $this->previousReturns = [];
            $this->selectedCustomer = null;
            $this->searchCustomer = '';
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error processing return: '.$e->getMessage());
            \Illuminate\Support\Facades\Log::error('Return processing error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function logStockChange($staffId, $productId, $type, $quantity, $previousStock, $newStock, $previousSoldQty, $newSoldQty, $reason)
    {
        \Illuminate\Support\Facades\Log::info('Stock Change', [
            'staff_id' => $staffId,
            'product_id' => $productId,
            'type' => $type,
            'quantity_returned' => $quantity,
            'previous_available_stock' => $previousStock,
            'new_available_stock' => $newStock,
            'previous_sold_quantity' => $previousSoldQty,
            'new_sold_quantity' => $newSoldQty,
            'reason' => $reason,
        ]);
    }

    public function render()
    {
        return view('livewire.staff.staff-return-management', [
            'searchCustomer' => $this->searchCustomer ?? '',
            'customers' => $this->customers ?? [],
            'customerInvoices' => $this->customerInvoices ?? [],
            'selectedCustomer' => $this->selectedCustomer,
            'selectedInvoice' => $this->selectedInvoice,
            'showReturnSection' => $this->showReturnSection ?? false,
            'returnItems' => $this->returnItems ?? [],
            'totalReturnValue' => $this->totalReturnValue ?? 0,
            'previousReturns' => $this->previousReturns ?? [],
            'invoiceModalData' => $this->invoiceModalData,
        ]);
    }
}
