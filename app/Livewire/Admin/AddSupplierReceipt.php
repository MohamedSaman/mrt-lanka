<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\PurchasePaymentAllocation;
use App\Models\POSSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\WithDynamicLayout;

#[Title("Add Supplier Receipt")]
class AddSupplierReceipt extends Component
{
    use WithDynamicLayout;

    use WithPagination;

    public $search = '';
    public $selectedSupplier = null;
    public $supplierOrders = [];
    public $selectedOrders = [];
    public $totalDueAmount = 0;
    public $totalPaymentAmount = 0;
    public $remainingAmount = 0;
    public $showPaymentModal = false;
    public $showReceiptModal = false;
    public $lastPayment = null;
    public $selectedOrderForView = null;
    public $showOrderDetailsModal = false;

    // Overpayment tracking
    public $supplierOverpayment = 0;
    public $useOverpayment = false;
    public $overpaymentToApply = 0;

    // Payment modal fields
    public $paymentData = [
        'payment_date' => '',
        'payment_method' => 'cash',
        'reference_number' => '',
        'notes' => ''
    ];

    public $cheque = [
        'cheque_number' => '',
        'bank_name' => '',
        'cheque_date' => '',
        'amount' => 0
    ];

    public $bankTransfer = [
        'bank_name' => '',
        'transfer_date' => '',
        'reference_number' => ''
    ];

    public $allocations = [];

    public function mount()
    {
        $this->paymentData['payment_date'] = now()->format('Y-m-d');
        $this->cheque['cheque_date'] = now()->format('Y-m-d');
        $this->bankTransfer['transfer_date'] = now()->format('Y-m-d');
    }

    public function updatedSearch($value)
    {
        $this->resetPage();
        $this->selectedSupplier = null;
        $this->supplierOrders = [];
        $this->resetPaymentData();
    }

    public function updatedTotalPaymentAmount()
    {
        $maxPayment = $this->totalDueAmount - $this->overpaymentToApply;
        if ($this->totalPaymentAmount > $maxPayment) {
            $this->totalPaymentAmount = $maxPayment;
        }
        if ($this->totalPaymentAmount < 0) {
            $this->totalPaymentAmount = 0;
        }

        $this->calculateRemainingAmount();
        $this->autoAllocatePayment();

        // Sync cheque amount
        if ($this->paymentData['payment_method'] === 'cheque') {
            $this->cheque['amount'] = $this->totalPaymentAmount;
        }
    }

    public function updatedPaymentDataPaymentMethod($value)
    {
        // Reset method-specific fields
        if ($value === 'cheque') {
            $this->cheque['cheque_date'] = now()->format('Y-m-d');
            $this->cheque['amount'] = $this->totalPaymentAmount;
        } else {
            $this->cheque = [
                'cheque_number' => '',
                'bank_name' => '',
                'cheque_date' => now()->format('Y-m-d'),
                'amount' => 0
            ];
        }

        if ($value === 'bank_transfer') {
            $this->bankTransfer['transfer_date'] = now()->format('Y-m-d');
        } else {
            $this->bankTransfer = [
                'bank_name' => '',
                'transfer_date' => now()->format('Y-m-d'),
                'reference_number' => ''
            ];
        }
    }

    public function selectSupplier($supplierId)
    {
        $this->selectedSupplier = ProductSupplier::find($supplierId);
        $this->loadSupplierOrders();
        $this->selectedOrders = [];
        $this->totalPaymentAmount = 0;
        $this->totalDueAmount = 0;
        $this->initializeAllocations();
        
        // Load supplier overpayment
        $this->supplierOverpayment = $this->selectedSupplier->getAvailableOverpayment();
        $this->useOverpayment = false;
        $this->overpaymentToApply = 0;
    }

    public function clearSelectedSupplier()
    {
        $this->selectedSupplier = null;
        $this->supplierOrders = [];
        $this->selectedOrders = [];
        $this->allocations = [];
        $this->totalDueAmount = 0;
        $this->totalPaymentAmount = 0;
        $this->remainingAmount = 0;
        $this->supplierOverpayment = 0;
        $this->useOverpayment = false;
        $this->overpaymentToApply = 0;
        $this->resetPaymentData();
    }

    private function loadSupplierOrders()
    {
        if (!$this->selectedSupplier) return;

        $orders = PurchaseOrder::where('supplier_id', $this->selectedSupplier->id)
            ->where('due_amount', '>', 0)
            ->orderBy('order_date', 'asc')
            ->get();

        $this->supplierOrders = $orders;
    }

    public function toggleOrderSelection($orderId)
    {
        if (in_array($orderId, $this->selectedOrders)) {
            $this->selectedOrders = array_values(array_diff($this->selectedOrders, [$orderId]));
        } else {
            $this->selectedOrders[] = $orderId;
        }
        
        $this->calculateTotalDue();
        $this->totalPaymentAmount = 0;
        $this->remainingAmount = $this->totalDueAmount;
        $this->initializeAllocations();
    }

    public function selectAllOrders()
    {
        $this->selectedOrders = collect($this->supplierOrders)->pluck('id')->toArray();
    $this->calculateTotalDue();
    $this->totalPaymentAmount = 0;
    $this->remainingAmount = $this->totalDueAmount;
    $this->initializeAllocations();
    }

    public function clearOrderSelection()
    {
        $this->selectedOrders = [];
        $this->totalDueAmount = 0;
        $this->totalPaymentAmount = 0;
        $this->remainingAmount = 0;
        $this->allocations = [];
    }

    private function calculateTotalDue()
    {
        $this->totalDueAmount = collect($this->supplierOrders)
            ->whereIn('id', $this->selectedOrders)
            ->sum('due_amount');
        $this->remainingAmount = $this->totalDueAmount;
        
        // Reset overpayment application when orders change
        $this->useOverpayment = false;
        $this->overpaymentToApply = 0;
    }

    public function toggleOverpayment()
    {
        $this->useOverpayment = !$this->useOverpayment;
        
        if ($this->useOverpayment && $this->supplierOverpayment > 0) {
            // Apply overpayment (max of available overpayment or total due)
            $this->overpaymentToApply = min($this->supplierOverpayment, $this->totalDueAmount);
        } else {
            $this->overpaymentToApply = 0;
        }
        
        $this->calculateRemainingAmount();
    }

    public function updatedOverpaymentToApply()
    {
        // Validate overpayment amount
        if ($this->overpaymentToApply > $this->supplierOverpayment) {
            $this->overpaymentToApply = $this->supplierOverpayment;
        }
        if ($this->overpaymentToApply > $this->totalDueAmount) {
            $this->overpaymentToApply = $this->totalDueAmount;
        }
        if ($this->overpaymentToApply < 0) {
            $this->overpaymentToApply = 0;
        }
        
        $this->calculateRemainingAmount();
    }

    private function calculateRemainingAmount()
    {
        $this->remainingAmount = $this->totalDueAmount - $this->totalPaymentAmount - $this->overpaymentToApply;
        if ($this->remainingAmount < 0) {
            $this->remainingAmount = 0;
        }
    }

    private function initializeAllocations()
    {
        $this->allocations = [];
        foreach ($this->supplierOrders as $order) {
            if (in_array($order->id, $this->selectedOrders)) {
                $this->allocations[$order->id] = [
                    'order_code' => $order->order_code,
                    'due_amount' => $order->due_amount,
                    'payment_amount' => 0,
                    'is_fully_paid' => false
                ];
            }
        }
    }

    private function autoAllocatePayment()
    {
        // Total payment includes both cash payment and overpayment credit
        $remainingPayment = $this->totalPaymentAmount + $this->overpaymentToApply;

        foreach ($this->supplierOrders as $order) {
            $orderId = $order->id;
            
            // Only allocate to selected orders
            if (!in_array($orderId, $this->selectedOrders)) {
                continue;
            }

            $dueAmount = $order->due_amount;

            if ($remainingPayment <= 0) {
                $this->allocations[$orderId]['payment_amount'] = 0;
                $this->allocations[$orderId]['is_fully_paid'] = false;
            } elseif ($remainingPayment >= $dueAmount) {
                $this->allocations[$orderId]['payment_amount'] = $dueAmount;
                $this->allocations[$orderId]['is_fully_paid'] = true;
                $remainingPayment -= $dueAmount;
            } else {
                $this->allocations[$orderId]['payment_amount'] = $remainingPayment;
                $this->allocations[$orderId]['is_fully_paid'] = false;
                $remainingPayment = 0;
            }
        }
    }

    public $payments = [];
    public $createdPayments = [];
    public $createdPaymentIds = [];

    protected function rules()
    {
        $minPayment = $this->overpaymentToApply > 0 ? 0 : 0.01;

        $rules = [
            'paymentData.payment_date' => 'required|date',
            'totalPaymentAmount' => "required|numeric|min:{$minPayment}",
        ];

        if (floatval($this->totalPaymentAmount) > 0) {
            $rules['payments'] = 'required|array|min:1';
            $rules['payments.*.payment_method'] = 'required|in:cash,cheque,bank_transfer,others';
            $rules['payments.*.amount'] = 'required|numeric|min:0.01';
            $rules['payments.*.notes'] = 'nullable|string|max:500';

            foreach ($this->payments as $index => $payment) {
                if (($payment['payment_method'] ?? '') === 'cheque') {
                    $rules["payments.{$index}.cheque_number"] = 'required|string|max:50';
                    $rules["payments.{$index}.bank_name"] = 'required|string|max:100';
                    $rules["payments.{$index}.cheque_date"] = 'required|date';
                } elseif (($payment['payment_method'] ?? '') === 'bank_transfer') {
                    $rules["payments.{$index}.bank_name"] = 'required|string|max:100';
                    $rules["payments.{$index}.transfer_date"] = 'required|date';
                    $rules["payments.{$index}.reference_number"] = 'required|string|max:100';
                }
            }
        }

        return $rules;
    }

    protected function messages()
    {
        $messages = [
            'paymentData.payment_date.required' => 'Payment date is required.',
            'totalPaymentAmount.required' => 'Payment amount is required.',
            'payments.required' => 'At least one payment method is required.',
        ];

        foreach ($this->payments as $index => $payment) {
            $num = $index + 1;
            $messages["payments.{$index}.payment_method.required"] = "Payment method is required for payment #{$num}.";
            $messages["payments.{$index}.amount.required"] = "Amount is required for payment #{$num}.";
            $messages["payments.{$index}.amount.min"] = "Amount for payment #{$num} must be at least Rs. 0.01.";
            
            $messages["payments.{$index}.cheque_number.required"] = "Cheque number is required for payment #{$num}.";
            $messages["payments.{$index}.bank_name.required"] = "Bank name is required for payment #{$num}.";
            $messages["payments.{$index}.cheque_date.required"] = "Cheque date is required for payment #{$num}.";
            
            $messages["payments.{$index}.transfer_date.required"] = "Transfer date is required for payment #{$num}.";
            $messages["payments.{$index}.reference_number.required"] = "Reference number is required for payment #{$num}.";
        }

        return $messages;
    }

    public function updatedPayments($value, $key)
    {
        $parts = explode('.', $key);
        if (count($parts) >= 2) {
            $index = $parts[0];
            $field = $parts[1];

            if ($field === 'amount') {
                if ($value === '' || $value === null) {
                    // Let it be empty/null so they can type
                } elseif (floatval($value) < 0) {
                    $this->payments[$index]['amount'] = 0;
                }
            }
        }
    }

    public function addPaymentMethod()
    {
        $totalAllocated = collect($this->payments)->sum(function ($payment) {
            return floatval($payment['amount'] ?? 0);
        });
        $remaining = max(0, floatval($this->totalPaymentAmount) - $totalAllocated);

        $this->payments[] = [
            'payment_method' => 'cash',
            'amount' => $remaining,
            'cheque_number' => '',
            'bank_name' => '',
            'cheque_date' => now()->format('Y-m-d'),
            'transfer_date' => now()->format('Y-m-d'),
            'reference_number' => '',
            'reference_number_opt' => '',
            'notes' => ''
        ];
    }

    public function removePaymentMethod($index)
    {
        if (count($this->payments) > 1) {
            unset($this->payments[$index]);
            $this->payments = array_values($this->payments);
        }
    }

    public function openPaymentModal()
    {
        if (empty($this->selectedOrders)) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Please select at least one order to make a payment.'
            ]);
            return;
        }

        // Allow payment if either payment amount or overpayment is being applied
        if ($this->totalPaymentAmount <= 0 && $this->overpaymentToApply <= 0) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Please enter a payment amount or apply overpayment credit.'
            ]);
            return;
        }

        $totalPaymentWithOverpayment = $this->totalPaymentAmount + $this->overpaymentToApply;
        if ($totalPaymentWithOverpayment > $this->totalDueAmount + 0.01) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Total payment amount cannot exceed total due amount.'
            ]);
            return;
        }

        $this->autoAllocatePayment();

        // Initialize payments array with a single default payment (Cash)
        $this->payments = [
            [
                'payment_method' => 'cash',
                'amount' => $this->totalPaymentAmount,
                'cheque_number' => '',
                'bank_name' => '',
                'cheque_date' => now()->format('Y-m-d'),
                'transfer_date' => now()->format('Y-m-d'),
                'reference_number' => '',
                'reference_number_opt' => '',
                'notes' => ''
            ]
        ];

        $this->showPaymentModal = true;
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function closeReceiptModal()
    {
        $this->showReceiptModal = false;
        $this->lastPayment = null;
        $this->createdPayments = [];
        $this->createdPaymentIds = [];

        $this->clearSelectedSupplier();
        $this->dispatch('refreshPage');
    }

    public function closeOrderDetailsModal()
    {
        $this->showOrderDetailsModal = false;
        $this->selectedOrderForView = null;
    }

    public function viewOrderDetails($orderId)
    {
        $this->selectedOrderForView = PurchaseOrder::with(['supplier', 'items.product.variant', 'returns.product'])
            ->find($orderId);
        $this->showOrderDetailsModal = true;
    }

    private function resetPaymentData()
    {
        $this->paymentData = [
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
            'reference_number' => '',
            'notes' => ''
        ];
        $this->totalPaymentAmount = 0;
        $this->payments = [];
        $this->createdPayments = [];
        $this->createdPaymentIds = [];
        $this->cheque = [
            'cheque_number' => '',
            'bank_name' => '',
            'cheque_date' => now()->format('Y-m-d'),
            'amount' => 0
        ];
        $this->bankTransfer = [
            'bank_name' => '',
            'transfer_date' => now()->format('Y-m-d'),
            'reference_number' => ''
        ];
    }

    public function processPayment()
    {
        if (floatval($this->totalPaymentAmount) > 0) {
            $sumPayments = collect($this->payments)->sum(function ($payment) {
                return floatval($payment['amount'] ?? 0);
            });

            if (abs(floatval($this->totalPaymentAmount) - floatval($sumPayments)) > 0.01) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'The sum of payment methods must exactly equal the total cash payment amount of Rs. ' . number_format($this->totalPaymentAmount, 2) . '.'
                ]);
                return;
            }
        }

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => $firstError ?? 'Please fill all required payment fields correctly.'
            ]);
            return;
        }

        if (floatval($this->totalPaymentAmount) > 0) {
            $chequeNumbers = [];
            foreach ($this->payments as $paymentItem) {
                if (($paymentItem['payment_method'] ?? '') === 'cheque') {
                    $chkNo = $paymentItem['cheque_number'];
                    if (in_array($chkNo, $chequeNumbers)) {
                        $this->dispatch('show-toast', [
                            'type' => 'error',
                            'message' => "Duplicate cheque number '{$chkNo}' entered in the form."
                        ]);
                        return;
                    }
                    $chequeNumbers[] = $chkNo;

                    $exists = PurchasePayment::where('payment_method', 'cheque')
                        ->where('cheque_number', $chkNo)
                        ->exists();
                    if ($exists) {
                        $this->dispatch('show-toast', [
                            'type' => 'error',
                            'message' => "Cheque number '{$chkNo}' already exists in the system. Please use a different cheque number."
                        ]);
                        return;
                    }
                }
            }
        }

        DB::beginTransaction();
        try {
            $this->createdPaymentIds = [];
            $overpaymentUsed = $this->overpaymentToApply;

            // Prepare orders to allocate payment
            $ordersToAllocate = [];
            foreach ($this->supplierOrders as $order) {
                if (in_array($order->id, $this->selectedOrders)) {
                    $ordersToAllocate[] = [
                        'id' => $order->id,
                        'order_code' => $order->order_code,
                        'due_amount' => $order->due_amount,
                        'allocated' => 0,
                    ];
                }
            }

            // Allocate overpayment credit first if used
            $remainingOverpaymentToAllocate = $overpaymentUsed;
            if ($remainingOverpaymentToAllocate > 0) {
                foreach ($ordersToAllocate as &$ord) {
                    if ($remainingOverpaymentToAllocate <= 0) break;
                    $ordRemainingDue = $ord['due_amount'] - $ord['allocated'];
                    if ($ordRemainingDue <= 0.001) continue;

                    $allocAmt = min($remainingOverpaymentToAllocate, $ordRemainingDue);
                    $ord['allocated'] += $allocAmt;
                    $remainingOverpaymentToAllocate -= $allocAmt;
                }
                unset($ord);
            }

            // Process each payment method
            if (floatval($this->totalPaymentAmount) > 0) {
                foreach ($this->payments as $pIdx => $paymentItem) {
                    $paymentAmount = floatval($paymentItem['amount']);
                    if ($paymentAmount <= 0) continue;

                    $paymentRecord = [
                        'supplier_id' => $this->selectedSupplier->id,
                        'amount' => $paymentAmount,
                        'payment_method' => $paymentItem['payment_method'],
                        'payment_reference' => $paymentItem['reference_number_opt'] ?? $this->paymentData['reference_number'] ?? null,
                        'payment_date' => $this->paymentData['payment_date'],
                        'notes' => $paymentItem['notes'] ?? null,
                        'status' => $paymentItem['payment_method'] === 'cash' ? 'paid' : 'pending',
                        'is_completed' => $paymentItem['payment_method'] === 'cash' ? 1 : 0,
                        'overpayment_used' => ($pIdx === 0) ? $overpaymentUsed : 0,
                    ];

                    if ($paymentItem['payment_method'] === 'cheque') {
                        $paymentRecord['cheque_number'] = $paymentItem['cheque_number'];
                        $paymentRecord['bank_name'] = $paymentItem['bank_name'];
                        $paymentRecord['cheque_date'] = $paymentItem['cheque_date'];
                        $paymentRecord['cheque_status'] = 'pending';
                    }

                    if ($paymentItem['payment_method'] === 'bank_transfer') {
                        $paymentRecord['bank_name'] = $paymentItem['bank_name'];
                        $paymentRecord['bank_transaction'] = $paymentItem['reference_number'];
                    }

                    $payment = PurchasePayment::create($paymentRecord);
                    $this->createdPaymentIds[] = $payment->id;

                    // Allocate payment amount to orders
                    $remainingPaymentToAllocate = $paymentAmount;
                    foreach ($ordersToAllocate as &$ord) {
                        if ($remainingPaymentToAllocate <= 0) break;
                        $ordRemainingDue = $ord['due_amount'] - $ord['allocated'];
                        if ($ordRemainingDue <= 0.001) continue;

                        $allocAmt = min($remainingPaymentToAllocate, $ordRemainingDue);
                        $ord['allocated'] += $allocAmt;
                        $remainingPaymentToAllocate -= $allocAmt;

                        PurchasePaymentAllocation::create([
                            'purchase_payment_id' => $payment->id,
                            'purchase_order_id' => $ord['id'],
                            'allocated_amount' => $allocAmt,
                        ]);

                        $orderModel = PurchaseOrder::find($ord['id']);
                        if ($orderModel) {
                            $orderModel->due_amount = max(0, round($orderModel->due_amount - $allocAmt, 2));
                            $orderModel->save();
                        }
                    }
                    unset($ord);

                    // Update POS Session if cash
                    if ($paymentItem['payment_method'] === 'cash') {
                        $activeSession = POSSession::where('user_id', auth()->id())
                            ->orderBy('created_at', 'desc')
                            ->first();
                        if ($activeSession) {
                            $activeSession->supplier_payment += $paymentAmount;
                            $activeSession->save();
                        }
                    }
                }
            } else {
                // Payment made 100% using overpayment credit
                $payment = PurchasePayment::create([
                    'supplier_id' => $this->selectedSupplier->id,
                    'amount' => 0,
                    'payment_method' => 'overpayment_credit',
                    'payment_date' => $this->paymentData['payment_date'],
                    'notes' => "Payment made using overpayment credit: " . number_format($overpaymentUsed, 2),
                    'status' => 'paid',
                    'is_completed' => 1,
                    'overpayment_used' => $overpaymentUsed,
                ]);
                $this->createdPaymentIds[] = $payment->id;

                foreach ($ordersToAllocate as $ord) {
                    if ($ord['allocated'] > 0) {
                        PurchasePaymentAllocation::create([
                            'purchase_payment_id' => $payment->id,
                            'purchase_order_id' => $ord['id'],
                            'allocated_amount' => $ord['allocated'],
                        ]);
                        $orderModel = PurchaseOrder::find($ord['id']);
                        if ($orderModel) {
                            $orderModel->due_amount = max(0, round($orderModel->due_amount - $ord['allocated'], 2));
                            $orderModel->save();
                        }
                    }
                }
            }

            // Deduct overpayment from supplier if used
            if ($overpaymentUsed > 0) {
                $this->selectedSupplier->useOverpayment($overpaymentUsed);
            }

            DB::commit();

            // Load created payments for display in receipt modal
            $this->createdPayments = PurchasePayment::with(['supplier', 'allocations.order'])
                ->whereIn('id', $this->createdPaymentIds)
                ->get();
            $this->lastPayment = $this->createdPayments->first();

            $this->showPaymentModal = false;
            $this->showReceiptModal = true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment processing error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Failed to save payment: ' . $e->getMessage()
            ]);
        }
    }

    public function printReceipt()
    {
        $this->dispatch('print-receipt', ['paymentId' => $this->lastPayment->id]);
    }

    public function downloadReceipt()
    {
        if (empty($this->createdPaymentIds) && !$this->lastPayment) {
            return;
        }

        $paymentIds = !empty($this->createdPaymentIds) ? $this->createdPaymentIds : [$this->lastPayment->id];
        $payments = PurchasePayment::with(['supplier', 'allocations.order'])->whereIn('id', $paymentIds)->get();
        $payment = $payments->first();

        $pdf = Pdf::loadView('components.payment-receipt', [
            'payment' => $payment,
            'payments' => $payments
        ]);
        
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, 'supplier-payment-receipt-' . $payment->id . '.pdf');
    }
    
    private function generateReceiptPDF()
    {
        // This is a simplified version - you might want to use DomPDF or similar
        $receipt = view('pdf.payment-receipt', ['payment' => $this->lastPayment])->render();
        // PDF generation logic would go here
    }

    public function getSuppliersProperty()
    {
        return ProductSupplier::with(['orders' => function ($query) {
            $query->where('due_amount', '>', 0);
        }])
            ->whereHas('orders', function ($query) {
                $query->where('due_amount', '>', 0);
            })
            ->when($this->search, function ($query) {
                $query->where('name', 'like', "%{$this->search}%");
            })
            ->orderBy('name')
            ->paginate(10);
    }

    /**
     * Format product name and append variant value if available. Tries to match by supplier_price or selling_price.
     */
    public function formatProductName($product, $unitPrice = null)
    {
        if (!$product) return 'N/A';
        $name = trim($product->name ?? '');

        if (!empty($product->variant_id) && $product->variant) {
            $variantName = $product->variant->variant_name ?? null;
            $variantValue = null;

            if ($unitPrice !== null) {
                $price = \App\Models\ProductPrice::where('product_id', $product->id)
                    ->whereNotNull('variant_value')
                    ->where('supplier_price', $unitPrice)
                    ->first();

                if (!$price) {
                    $price = \App\Models\ProductPrice::where('product_id', $product->id)
                        ->whereNotNull('variant_value')
                        ->where('selling_price', $unitPrice)
                        ->first();
                }

                if ($price && !empty($price->variant_value)) {
                    $variantValue = trim($price->variant_value);
                }
            }

            if (!$variantValue) {
                $fallback = \App\Models\ProductPrice::where('product_id', $product->id)
                    ->whereNotNull('variant_value')
                    ->where('variant_value', '!=', '')
                    ->first();
                if ($fallback) $variantValue = trim($fallback->variant_value);
            }

            if (!empty($variantValue)) {
                if (!empty($variantName)) {
                    $display = trim($name . ' - ' . $variantName . ': ' . $variantValue);
                } else {
                    $display = trim($name . ' - ' . $variantValue);
                }
                // Remove accidental trailing punctuation/hyphens
                return rtrim($display, " -:");
            }
        }

        return rtrim($name, " -:");
    }

    public function render()
    {
        return view('livewire.admin.add-supplier-receipt', [
            'suppliers' => $this->suppliers
        ])->layout($this->layout);
    }
}