<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Payment;
use App\Models\Cheque;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Layout('components.layouts.admin')]
#[Title('Payment Approvals')]
class StaffPaymentApproval extends Component
{
    use WithPagination;

    public $pendingCount = 0;
    public $approvedCount = 0;
    public $rejectedCount = 0;
    public $searchTerm = '';
    public $filterStatus = 'pending'; // pending, approved, rejected
    public $filterPaymentMethod = 'all'; // all, cash, cheque, bank_transfer, credit
    public $perPage = 15;

    public function mount()
    {
        $this->updateCounts();
    }

    public function getPaymentsProperty()
    {
        // Get all payments with pending, approved, rejected statuses
        $query = Payment::with(['sale.customer', 'sale.user'])
            ->where('sale_id', '!=', null);

        // Filter by status
        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        // Filter by payment method
        if ($this->filterPaymentMethod !== 'all') {
            $query->where('payment_method', $this->filterPaymentMethod);
        }

        // Search term
        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('id', 'like', '%' . $this->searchTerm . '%')
                    ->orWhere('payment_reference', 'like', '%' . $this->searchTerm . '%')
                    ->orWhereHas('sale', function ($q) {
                        $q->where('invoice_number', 'like', '%' . $this->searchTerm . '%');
                    })
                    ->orWhereHas('sale.customer', function ($q) {
                        $q->where('name', 'like', '%' . $this->searchTerm . '%');
                    });
            });
        }

        return $query->orderByDesc('created_at')->paginate($this->perPage);
    }

    private function updateCounts()
    {
        // Get counts
        $this->pendingCount = Payment::where('status', 'pending')->where('sale_id', '!=', null)->count();
        $this->approvedCount = Payment::where('status', 'approved')->where('sale_id', '!=', null)->count();
        $this->rejectedCount = Payment::where('status', 'rejected')->where('sale_id', '!=', null)->count();
    }

    public function updatedSearchTerm()
    {
        $this->resetPage();
        $this->updateCounts();
    }

    public function updatedFilterStatus()
    {
        $this->resetPage();
        $this->updateCounts();
    }

    public function updatedFilterPaymentMethod()
    {
        $this->resetPage();
        $this->updateCounts();
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function approvePayment($paymentId)
    {
        try {
            DB::beginTransaction();

            $payment = Payment::findOrFail($paymentId);
            $payment->update([
                'status' => 'approved',
                'is_completed' => true,
            ]);

            // If payment method is cheque, approve cheques too
            if ($payment->payment_method === 'cheque') {
                Cheque::where('payment_id', $paymentId)->update([
                    'status' => 'complete',
                ]);
            }

            // Update sale payment status
            if ($payment->sale) {
                $totalPaid = Payment::where('sale_id', $payment->sale_id)
                    ->where('status', 'approved')
                    ->sum('amount');

                $sale = $payment->sale;
                if ($totalPaid >= $sale->total_amount) {
                    $sale->update(['payment_status' => 'paid']);
                } else {
                    $sale->update(['payment_status' => 'partial']);
                }
            }

            DB::commit();

            $this->js("Swal.fire('success', 'Payment approved successfully!', 'success')");
            $this->updateCounts();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment approval error: ' . $e->getMessage());
            $this->js("Swal.fire('error', 'Failed to approve payment: " . $e->getMessage() . "', 'error')");
        }
    }

    public function rejectPayment($paymentId)
    {
        try {
            DB::beginTransaction();

            $payment = Payment::findOrFail($paymentId);
            $payment->update([
                'status' => 'rejected',
                'is_completed' => false,
            ]);

            // If payment method is cheque, reject cheques too
            if ($payment->payment_method === 'cheque') {
                Cheque::where('payment_id', $paymentId)->update([
                    'status' => 'cancelled',
                ]);
            }

            DB::commit();

            $this->js("Swal.fire('success', 'Payment rejected successfully!', 'success')");
            $this->updateCounts();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment rejection error: ' . $e->getMessage());
            $this->js("Swal.fire('error', 'Failed to reject payment: " . $e->getMessage() . "', 'error')");
        }
    }

    public function getPaymentMethodBadge($method)
    {
        return match ($method) {
            'cash' => ['class' => 'bg-success', 'text' => 'Cash'],
            'cheque' => ['class' => 'bg-info', 'text' => 'Cheque'],
            'bank_transfer' => ['class' => 'bg-warning', 'text' => 'Bank Transfer'],
            'credit' => ['class' => 'bg-danger', 'text' => 'Credit'],
            default => ['class' => 'bg-secondary', 'text' => 'Unknown']
        };
    }

    public function getStatusBadge($status)
    {
        return match ($status) {
            'pending' => ['class' => 'bg-warning', 'text' => 'Pending Approval'],
            'approved' => ['class' => 'bg-success', 'text' => 'Approved'],
            'rejected' => ['class' => 'bg-danger', 'text' => 'Rejected'],
            'paid' => ['class' => 'bg-success', 'text' => 'Paid'],
            default => ['class' => 'bg-secondary', 'text' => 'Unknown']
        };
    }

    public function render()
    {
        return view('livewire.admin.staff-payment-approval', [
            'payments' => $this->payments,
        ]);
    }
}
