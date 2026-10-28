<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Cheque;
use App\Models\Customer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Log;
use App\Livewire\Concerns\WithDynamicLayout;

#[Title('Cheque List')]
class ChequeList extends Component
{
    use WithDynamicLayout;

    use WithPagination;
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
        // Show pending cheques first, then others by cheque_date desc
        $query = Cheque::with('customer')
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END ASC")
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
        // Apply status filter if set
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

    public function getPendingCountProperty()
    {
        $query = Cheque::where('status', 'pending');
        [$start, $end] = $this->getChequeDateRange();
        if ($start) {
            $query->whereDate('cheque_date', '>=', $start);
        }
        if ($end) {
            $query->whereDate('cheque_date', '<=', $end);
        }
        return $query->count();
    }

    public function getCompleteCountProperty()
    {
        $query = Cheque::where('status', 'complete');
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

    // --- Supplier Cheques Logic ---
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
            ->orderByRaw("CASE WHEN cheque_status = 'pending' THEN 0 ELSE 1 END ASC")
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
            if ($this->statusFilter === 'pending') {
                $query->where('cheque_status', 'pending');
            } elseif ($this->statusFilter === 'complete') {
                $query->where(function ($q) {
                    $q->where('cheque_status', 'complete')
                      ->orWhere('status', 'paid');
                });
            } elseif ($this->statusFilter === 'return') {
                $query->where(function ($q) {
                    $q->where('cheque_status', 'returned')
                      ->orWhere('status', 'rejected');
                });
            } elseif ($this->statusFilter === 'overdue') {
                $query->where('cheque_status', 'pending')
                      ->whereDate('cheque_date', '<', now()->toDateString());
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

    public function getSupplierPendingCountProperty()
    {
        $query = \App\Models\PurchasePayment::where(function ($q) {
                $q->where('payment_method', 'cheque')->orWhereNotNull('cheque_number');
            })
            ->where('cheque_status', 'pending');
        [$start, $end] = $this->getChequeDateRange();
        if ($start) $query->whereDate('cheque_date', '>=', $start);
        if ($end) $query->whereDate('cheque_date', '<=', $end);
        return $query->count();
    }

    public function getSupplierCompleteCountProperty()
    {
        $query = \App\Models\PurchasePayment::where(function ($q) {
                $q->where('payment_method', 'cheque')->orWhereNotNull('cheque_number');
            })
            ->where(function ($q) {
                $q->where('cheque_status', 'complete')->orWhere('status', 'paid');
            });
        [$start, $end] = $this->getChequeDateRange();
        if ($start) $query->whereDate('cheque_date', '>=', $start);
        if ($end) $query->whereDate('cheque_date', '<=', $end);
        return $query->count();
    }

    public function getSupplierOverdueCountProperty()
    {
        $query = \App\Models\PurchasePayment::where(function ($q) {
                $q->where('payment_method', 'cheque')->orWhereNotNull('cheque_number');
            })
            ->where('cheque_status', 'pending')
            ->whereDate('cheque_date', '<', now()->toDateString());
        [$start, $end] = $this->getChequeDateRange();
        if ($start) $query->whereDate('cheque_date', '>=', $start);
        if ($end) $query->whereDate('cheque_date', '<=', $end);
        return $query->count();
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

    public function confirmComplete($id)
    {
        $this->js("
            Swal.fire({
                title: 'Mark as Complete?',
                text: 'Are you sure you want to mark this cheque as complete?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, mark as complete!'
            }).then((result) => {
                if (result.isConfirmed) {
                    \$wire.completeCheque({$id});
                }
            });
        ");
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

    public function confirmReturn($id)
    {
        $this->js("
            Swal.fire({
                title: 'Return Cheque?',
                text: 'Are you sure you want to return this cheque?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, return cheque!'
            }).then((result) => {
                if (result.isConfirmed) {
                    \$wire.returnCheque({$id});
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
            Log::error("Error completing supplier cheque: " . $e->getMessage());
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
            Log::error("Error returning supplier cheque: " . $e->getMessage());
            $this->js("Swal.fire('Error', 'Failed to return supplier cheque!', 'error');");
        }
    }

    public function completeCheque($id)
    {
        try {
            $cheque = Cheque::find($id);

            if (!$cheque) {
                $this->js("Swal.fire('Error', 'Cheque not found!', 'error');");
                return;
            }

            $cheque->status = 'complete';
            $cheque->save();

            // If the payment had been set to return or pending, ensure it is active
            if ($cheque->payment_id) {
                $payment = \App\Models\Payment::find($cheque->payment_id);
                if ($payment && in_array($payment->status, ['return', 'pending'])) {
                    $payment->status = 'paid';
                    $payment->save();
                }
            }

            if ($cheque->customer_id) {
                \App\Services\CustomerBalanceService::syncCustomerBalance($cheque->customer_id);
            }

            $this->js("
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Cheque marked as complete successfully!',
                    timer: 2000,
                    showConfirmButton: false
                });
            ");
        } catch (\Exception $e) {
            Log::error("Error completing cheque: " . $e->getMessage());
            $this->js("Swal.fire('Error', 'Failed to mark cheque as complete!', 'error');");
        }
    }

    public function returnCheque($id)
    {
        try {
            $cheque = Cheque::find($id);

            if (!$cheque) {
                $this->js("Swal.fire('Error', 'Cheque not found!', 'error');");
                return;
            }

            $cheque->status = 'return';
            $cheque->save();

            // Update associated payment status
            if ($cheque->payment_id) {
                $payment = \App\Models\Payment::find($cheque->payment_id);
                if ($payment) {
                    $payment->status = 'return';
                    $payment->save();
                    \Illuminate\Support\Facades\Log::info("Payment {$payment->id} status updated to return due to cheque return.");
                }
            }

            if ($cheque->customer_id) {
                \App\Services\CustomerBalanceService::syncCustomerBalance($cheque->customer_id);
            }

            // Refresh the data


            $this->js("
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Cheque returned successfully!',
                    timer: 2000,
                    showConfirmButton: false
                });
            ");
        } catch (\Exception $e) {
            Log::error("Error returning cheque: " . $e->getMessage());
            $this->js("Swal.fire('Error', 'Failed to return cheque!', 'error');");
        }
    }

    public function render()
    {
        return view('livewire.admin.cheque-list', [
            'cheques' => $this->cheques,
            'pendingCount' => $this->pendingCount,
            'completeCount' => $this->completeCount,
            'overdueCount' => $this->overdueCount,
            'supplierCheques' => $this->supplierCheques,
            'supplierPendingCount' => $this->supplierPendingCount,
            'supplierCompleteCount' => $this->supplierCompleteCount,
            'supplierOverdueCount' => $this->supplierOverdueCount,
            'supplierReturnedCount' => $this->supplierReturnedCount,
        ])->layout($this->layout);
    }
}
