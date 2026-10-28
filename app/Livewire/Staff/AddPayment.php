<?php

namespace App\Livewire\Staff;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\Customer;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Layout('components.layouts.staff')]
#[Title('Add Payment')]
class AddPayment extends Component
{
    use WithFileUploads;

    public $search = '';
    public $selectedCustomerId = null;
    public $selectedSaleId = null;
    public $pendingSales = [];

    // Payment form fields
    public $paymentAmount = '';
    public $paymentMethod = '';
    public $paymentNote = '';
    public $paymentAttachment = null;
    public $attachmentPreview = null;

    // Modal state
    public $showPaymentModal = false;
    public $selectedSale = null;

    protected $rules = [
        'paymentAmount' => 'required|numeric|min:0.01',
        'paymentMethod' => 'required|string',
        'paymentNote' => 'nullable|string|max:500',
        'paymentAttachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
    ];

    public function updatedSearch()
    {
        $this->resetPaymentForm();
    }

    public function updatedPaymentAttachment()
    {
        $this->validateOnly('paymentAttachment');

        if ($this->paymentAttachment) {
            $ext = strtolower($this->paymentAttachment->getClientOriginalExtension());
            $this->attachmentPreview = [
                'name' => $this->paymentAttachment->getClientOriginalName(),
                'type' => in_array($ext, ['jpg', 'jpeg', 'png']) ? 'image' : 'pdf',
                'url' => in_array($ext, ['jpg', 'jpeg', 'png']) ? $this->paymentAttachment->temporaryUrl() : null,
            ];
        } else {
            $this->attachmentPreview = null;
        }
    }

    public function selectCustomer($customerId)
    {
        $this->selectedCustomerId = $customerId;
        $this->loadPendingSales();
        $this->resetPaymentForm();
    }

    public function loadPendingSales()
    {
        if (!$this->selectedCustomerId) {
            $this->pendingSales = [];
            return;
        }

        $this->pendingSales = Sale::where('customer_id', $this->selectedCustomerId)
            ->where('user_id', Auth::id())
            ->where('sale_type', 'staff')
            ->where('due_amount', '>', 0)
            ->with(['items', 'payments'])
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    public function openPaymentModal($saleId)
    {
        $this->resetValidation();
        $this->selectedSaleId = $saleId;

        $this->selectedSale = Sale::with(['customer', 'items'])->findOrFail($saleId);

        $this->paymentAmount = number_format($this->selectedSale->due_amount, 2, '.', '');
        $this->paymentMethod = '';
        $this->paymentNote = '';
        $this->paymentAttachment = null;
        $this->attachmentPreview = null;

        $this->showPaymentModal = true;
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
        $this->resetPaymentForm();
    }

    public function resetPaymentForm()
    {
        $this->reset([
            'selectedSaleId',
            'selectedSale',
            'paymentAmount',
            'paymentMethod',
            'paymentNote',
            'paymentAttachment',
            'attachmentPreview'
        ]);
        $this->resetValidation();
    }

    public function submitPayment()
    {
        $this->validate();

        DB::beginTransaction();

        try {
            $sale = Sale::lockForUpdate()->findOrFail($this->selectedSaleId);

            // Validate payment amount
            if ((float)$this->paymentAmount > $sale->due_amount) {
                $this->addError('paymentAmount', 'Payment amount cannot exceed due amount.');
                DB::rollBack();
                return;
            }

            // Handle file upload
            $attachmentPath = null;
            if ($this->paymentAttachment) {
                $fileExt = $this->paymentAttachment->getClientOriginalExtension();
                $fileName = now()->timestamp . '-sale-' . $sale->id . '-' . Str::random(6) . '.' . $fileExt;
                $this->paymentAttachment->storeAs('public/due-receipts', $fileName);
                $attachmentPath = 'due-receipts/' . $fileName;
            }

            // Find or create pending payment record
            $payment = Payment::where('sale_id', $this->selectedSaleId)
                ->where('is_completed', false)
                ->whereNull('status')
                ->first();

            if (!$payment) {
                // Create new payment record with pending status
                $payment = Payment::create([
                    'sale_id' => $this->selectedSaleId,
                    'amount' => 0,
                    'is_completed' => false,
                    'status' => 'pending',  // Set initial status
                    'payment_date' => now(),
                ]);
            }

            // Update payment with submission details
            $payment->update([
                'amount' => $this->paymentAmount,
                'due_payment_method' => $this->paymentMethod,
                'due_payment_attachment' => $attachmentPath,
                'status' => 'pending',
                'payment_date' => now(),
                'customer_id' => $sale->customer_id,
            ]);

            // Create payment allocation record
            DB::table('payment_allocations')->insert([
                'payment_id' => $payment->id,
                'sale_id' => $this->selectedSaleId,
                'allocated_amount' => $this->paymentAmount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update sale due amount
            $remaining = round($sale->due_amount - (float)$this->paymentAmount, 2);
            $sale->update(['due_amount' => $remaining]);

            // Add note to sale
            if (!empty($this->paymentNote)) {
                $existing = $sale->notes ?? '';
                $noteLine = "\nPayment of Rs. {$this->paymentAmount} submitted on " . now()->format('Y-m-d H:i') . ": " . $this->paymentNote;
                $sale->update(['notes' => trim($existing . $noteLine)]);
            }

            DB::commit();

            $this->dispatch('showToast', [
                'type' => 'success',
                'message' => 'Payment submitted successfully and sent for admin approval!'
            ]);

            $this->closePaymentModal();
            $this->loadPendingSales();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('showToast', [
                'type' => 'error',
                'message' => 'Failed to submit payment: ' . $e->getMessage()
            ]);
        }
    }

    public function render()
    {
        $staffId = Auth::id();

        // Get customers with due amounts (only customers created by this staff)
        $query = Customer::query()
            ->where('customers.user_id', $staffId)
            ->whereHas('sales', function ($saleQuery) use ($staffId) {
                $saleQuery->where('sales.user_id', $staffId)
                    ->where('sales.sale_type', 'staff')
                    ->where('sales.due_amount', '>', 0);
            })
            ->withSum(['sales as total_due' => function ($q) use ($staffId) {
                $q->where('sales.user_id', $staffId)
                    ->where('sales.sale_type', 'staff');
            }], 'sales.due_amount');

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('customers.name', 'like', "%{$this->search}%")
                    ->orWhere('customers.phone', 'like', "%{$this->search}%")
                    ->orWhere('customers.email', 'like', "%{$this->search}%");
            });
        }

        $customers = $query->orderBy('customers.name')->get();

        // Calculate statistics
        $stats = [
            'total_customers' => Customer::where('customers.user_id', $staffId)
                ->whereHas('sales', function ($q) use ($staffId) {
                    $q->where('sales.user_id', $staffId)
                        ->where('sales.sale_type', 'staff')
                        ->where('sales.due_amount', '>', 0);
                })->count(),

            'total_due_amount' => Sale::where('sales.user_id', $staffId)
                ->where('sales.sale_type', 'staff')
                ->where('sales.due_amount', '>', 0)
                ->sum('sales.due_amount'),

            'pending_approvals' => Payment::whereHas('sale', function ($q) use ($staffId) {
                $q->where('sales.user_id', $staffId);
            })
                ->where('status', 'pending')
                ->count(),
        ];

        return view('livewire.staff.add-payment', [
            'customers' => $customers,
            'stats' => $stats,
        ]);
    }
}
