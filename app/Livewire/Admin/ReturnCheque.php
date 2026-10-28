<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Cheque;
use App\Models\Customer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\WithDynamicLayout;

#[Title('Return Cheque')]
class ReturnCheque extends Component
{
    use WithDynamicLayout;

    use WithPagination;

    public $selectedChequeId = null;
    public $perPage = 10;
    public $search = '';
    public $statusFilter = 'all';
    public $fromMonth = '';
    public $toMonth = '';
    public $activeTab = 'customer';

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    // New cheque fields
    public $newChequeNumber;
    public $newBankName;
    public $newChequeDate;
    public $newChequeAmount;

    protected $rules = [
        'newChequeNumber' => 'required|string|max:255',
        'newBankName' => 'required|string|max:255',
        'newChequeDate' => 'required|date',
        'newChequeAmount' => 'required|numeric|min:0.01',
    ];

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedFromMonth()
    {
        $this->resetPage();
    }

    public function updatedToMonth()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->reset(['search', 'statusFilter', 'fromMonth', 'toMonth']);
        $this->statusFilter = 'all';
        $this->resetPage();
    }

    protected function getChequeDateRange()
    {
        $start = null;
        $end = null;

        if (!empty($this->fromMonth)) {
            try {
                $start = \Carbon\Carbon::parse($this->fromMonth . '-01')->startOfMonth()->toDateString();
            } catch (\Exception $e) {}
        }

        if (!empty($this->toMonth)) {
            try {
                $end = \Carbon\Carbon::parse($this->toMonth . '-01')->endOfMonth()->toDateString();
            } catch (\Exception $e) {}
        }

        return [$start, $end];
    }

    public function getChequesProperty()
    {
        // Only return and cancelled cheques
        $query = Cheque::with('customer')
            ->whereIn('status', ['return', 'cancelled'])
            ->orderByRaw("CASE WHEN status = 'return' THEN 0 ELSE 1 END ASC")
            ->orderByDesc('cheque_date');

        if (!empty($this->search)) {
            $term = '%' . $this->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('cheque_number', 'like', $term)
                    ->orWhere('bank_name', 'like', $term)
                    ->orWhereHas('customer', function ($cq) use ($term) {
                        $cq->where('name', 'like', $term)
                            ->orWhere('phone', 'like', $term);
                    });
            });
        }

        if (!empty($this->statusFilter) && $this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        // Apply cheque_date filter by month range
        [$start, $end] = $this->getChequeDateRange();
        if ($start) {
            $query->whereDate('cheque_date', '>=', $start);
        }
        if ($end) {
            $query->whereDate('cheque_date', '<=', $end);
        }

        return $query->paginate($this->perPage);
    }

    public function getReturnCountProperty()
    {
        $query = Cheque::where('status', 'return');
        [$start, $end] = $this->getChequeDateRange();
        if ($start) {
            $query->whereDate('cheque_date', '>=', $start);
        }
        if ($end) {
            $query->whereDate('cheque_date', '<=', $end);
        }
        return $query->count();
    }

    public function getCancelledCountProperty()
    {
        $query = Cheque::where('status', 'cancelled');
        [$start, $end] = $this->getChequeDateRange();
        if ($start) {
            $query->whereDate('cheque_date', '>=', $start);
        }
        if ($end) {
            $query->whereDate('cheque_date', '<=', $end);
        }
        return $query->count();
    }

    public function getOverdueCountProperty()
    {
        $query = Cheque::where('status', 'overdue');
        [$start, $end] = $this->getChequeDateRange();
        if ($start) {
            $query->whereDate('cheque_date', '>=', $start);
        }
        if ($end) {
            $query->whereDate('cheque_date', '<=', $end);
        }
        return $query->count();
    }

    // --- Supplier Returned Cheques Logic ---
    public function getSupplierChequesProperty()
    {
        $query = \App\Models\PurchasePayment::with(['supplier', 'allocations.order'])
            ->where(function ($q) {
                $q->where('payment_method', 'cheque')
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('cheque_number')
                         ->where('cheque_number', '!=', '');
                  });
            })
            ->where(function ($q) {
                $q->where('cheque_status', 'returned')
                  ->orWhere('status', 'rejected');
            })
            ->orderByDesc('cheque_date');

        if (!empty($this->search)) {
            $term = '%' . $this->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('cheque_number', 'like', $term)
                    ->orWhere('bank_name', 'like', $term)
                    ->orWhereHas('supplier', function ($sq) use ($term) {
                        $sq->where('name', 'like', $term)
                            ->orWhere('phone', 'like', $term)
                            ->orWhere('contact', 'like', $term);
                    });
            });
        }

        if (!empty($this->statusFilter) && $this->statusFilter !== 'all') {
            if ($this->statusFilter === 'return') {
                $query->where(function ($q) {
                    $q->where('cheque_status', 'returned')
                      ->orWhere('status', 'rejected');
                });
            } elseif ($this->statusFilter === 'cancelled') {
                $query->where('status', 'cancelled');
            }
        }

        [$start, $end] = $this->getChequeDateRange();
        if ($start) {
            $query->whereDate('cheque_date', '>=', $start);
        }
        if ($end) {
            $query->whereDate('cheque_date', '<=', $end);
        }

        return $query->paginate($this->perPage);
    }

    public function getSupplierReturnedCountProperty()
    {
        $query = \App\Models\PurchasePayment::where(function ($q) {
                $q->where('payment_method', 'cheque')->orWhereNotNull('cheque_number');
            })
            ->where(function ($q) {
                $q->where('cheque_status', 'returned')->orWhere('status', 'rejected');
            });
        [$start, $end] = $this->getChequeDateRange();
        if ($start) $query->whereDate('cheque_date', '>=', $start);
        if ($end) $query->whereDate('cheque_date', '<=', $end);
        return $query->count();
    }

    public function confirmCompleteSupplierCheque($id)
    {
        $this->js("
            Swal.fire({
                title: 'Mark Supplier Cheque as Complete?',
                text: 'Are you sure you want to mark this supplier cheque as complete?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, mark as complete!'
            }).then((result) => {
                if (result.isConfirmed) {
                    \$wire.completeSupplierCheque({$id});
                }
            });
        ");
    }

    public function confirmReturnSupplierCheque($id)
    {
        $this->js("
            Swal.fire({
                title: 'Return Supplier Cheque?',
                text: 'Are you sure you want to return this supplier cheque? This will reinstate order due balances.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, return cheque!'
            }).then((result) => {
                if (result.isConfirmed) {
                    \$wire.returnSupplierCheque({$id});
                }
            });
        ");
    }

    public function completeSupplierCheque($id)
    {
        try {
            $payment = \App\Models\PurchasePayment::with('allocations.order')->find($id);

            if (!$payment) {
                $this->js("Swal.fire('Error', 'Supplier payment record not found!', 'error');");
                return;
            }

            if ($payment->cheque_status === 'returned' || $payment->status === 'rejected') {
                foreach ($payment->allocations as $alloc) {
                    if ($alloc->order) {
                        $alloc->order->due_amount = max(0, round($alloc->order->due_amount - $alloc->allocated_amount, 2));
                        $alloc->order->save();
                    }
                }
            }

            $payment->cheque_status = 'complete';
            $payment->status = 'paid';
            $payment->is_completed = 1;
            $payment->save();

            $this->js("
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Supplier cheque marked as complete successfully!',
                    timer: 2000,
                    showConfirmButton: false
                });
            ");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error completing supplier cheque: " . $e->getMessage());
            $this->js("Swal.fire('Error', 'Failed to mark supplier cheque as complete!', 'error');");
        }
    }

    public function returnSupplierCheque($id)
    {
        try {
            $payment = \App\Models\PurchasePayment::with('allocations.order')->find($id);

            if (!$payment) {
                $this->js("Swal.fire('Error', 'Supplier payment record not found!', 'error');");
                return;
            }

            if ($payment->cheque_status !== 'returned') {
                foreach ($payment->allocations as $alloc) {
                    if ($alloc->order) {
                        $alloc->order->due_amount = round($alloc->order->due_amount + $alloc->allocated_amount, 2);
                        $alloc->order->save();
                    }
                }

                $payment->cheque_status = 'returned';
                $payment->status = 'rejected';
                $payment->is_completed = 0;
                $payment->save();
            }

            $this->js("
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Supplier cheque marked as returned successfully!',
                    timer: 2000,
                    showConfirmButton: false
                });
            ");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error returning supplier cheque: " . $e->getMessage());
            $this->js("Swal.fire('Error', 'Failed to return supplier cheque!', 'error');");
        }
    }

    public function setSelectedCheque($id)
    {
        $this->selectedChequeId = $id;

        // Pre-fill the cheque amount from the selected cheque
        $cheque = Cheque::find($id);
        if ($cheque) {
            $this->newChequeAmount = $cheque->cheque_amount;
        }
    }

    public function rechequeSubmit()
    {
        $this->validate();

        // Check if cheque number already exists
        $existingCheque = Cheque::where('cheque_number', $this->newChequeNumber)->first();
        if ($existingCheque) {
            $this->js("Swal.fire('Error!', 'Cheque number already exists. Please use a different cheque number.', 'error');");
            return;
        }

        $oldCheque = Cheque::find($this->selectedChequeId);
        if (!$oldCheque) {
            $this->js("Swal.fire('Error!', 'Cheque not found.', 'error');");
            return;
        }

        try {
            // Mark old cheque as cancelled
            $oldCheque->status = 'cancelled';
            $oldCheque->save();

            // Reactivate payment record if it was marked as return
            if ($oldCheque->payment_id) {
                $payment = \App\Models\Payment::find($oldCheque->payment_id);
                if ($payment && $payment->status === 'return') {
                    $payment->status = 'paid';
                    $payment->save();
                }
            }

            // Add new cheque (copy customer/payment if needed)
            Cheque::create([
                'cheque_number' => $this->newChequeNumber,
                'bank_name' => $this->newBankName,
                'cheque_date' => $this->newChequeDate,
                'cheque_amount' => $this->newChequeAmount,
                'status' => 'pending',
                'customer_id' => $oldCheque->customer_id,
                'payment_id' => $oldCheque->payment_id,
            ]);

            if ($oldCheque->customer_id) {
                \App\Services\CustomerBalanceService::syncCustomerBalance($oldCheque->customer_id);
            }

            // Reset form
            $this->resetForm();

            // Close modal and show success message
            $this->js("bootstrap.Modal.getInstance(document.getElementById('rechequeModal')).hide();");
            $this->js("Swal.fire('Success!', 'New cheque added and old cheque cancelled successfully.', 'success');");
            $this->js("location.reload();");
        } catch (\Exception $e) {
            $this->js("Swal.fire('Error!', 'Failed to process re-cheque: " . addslashes($e->getMessage()) . "', 'error');");
        }
    }

    private function resetForm()
    {
        $this->selectedChequeId = null;
        $this->reset(['newChequeNumber', 'newBankName', 'newChequeDate', 'newChequeAmount']);
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.return-cheque', [
            'cheques' => $this->cheques,
            'returnCount' => $this->returnCount,
            'cancelledCount' => $this->cancelledCount,
            'overdueCount' => $this->overdueCount,
            'supplierCheques' => $this->supplierCheques,
            'supplierReturnedCount' => $this->supplierReturnedCount,
        ])->layout($this->layout);
    }
}
