<?php

namespace App\Livewire\Staff;

use App\Models\Payment;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;

#[Layout('components.layouts.staff')]
#[Title('My Payments List')]
class PaymentsList extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = 'all'; // all, pending, approved, rejected
    public $dateRange = '';
    public $perPage = 15;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'statusFilter', 'dateRange']);
        $this->resetPage();
    }

    public function render()
    {
        $query = Payment::query()
            ->with(['sale.customer', 'sale.user'])
            ->whereHas('sale', function ($saleQuery) {
                // Only show payments for sales created by this staff member
                $saleQuery->where('sales.user_id', Auth::id());
            })
            ->whereNotNull('payments.status'); // Only show submitted payments (not null status)

        // Search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('sale', function ($saleQuery) {
                    $saleQuery->where('invoice_number', 'like', "%{$this->search}%")
                        ->orWhere('sale_id', 'like', "%{$this->search}%")
                        ->orWhereHas('customer', function ($customerQuery) {
                            $customerQuery->where('name', 'like', "%{$this->search}%")
                                ->orWhere('phone', 'like', "%{$this->search}%");
                        });
                });
            });
        }

        // Status filter
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        // Date range filter
        if (!empty($this->dateRange)) {
            if (strpos($this->dateRange, ' to ') !== false) {
                list($startDate, $endDate) = explode(' to ', $this->dateRange);
                $query->whereBetween('payment_date', [$startDate, $endDate . ' 23:59:59']);
            }
        }

        $payments = $query->orderByDesc('payment_date')->paginate($this->perPage);

        // Calculate statistics
        $stats = [
            'total_payments' => Payment::whereHas('sale', function ($q) {
                $q->where('sales.user_id', Auth::id());
            })->whereNotNull('payments.status')->count(),

            'pending_count' => Payment::whereHas('sale', function ($q) {
                $q->where('sales.user_id', Auth::id());
            })->where('payments.status', 'pending')->count(),

            'approved_count' => Payment::whereHas('sale', function ($q) {
                $q->where('sales.user_id', Auth::id());
            })->where('payments.status', 'approved')->count(),

            'rejected_count' => Payment::whereHas('sale', function ($q) {
                $q->where('sales.user_id', Auth::id());
            })->where('payments.status', 'rejected')->count(),

            'total_amount' => Payment::whereHas('sale', function ($q) {
                $q->where('sales.user_id', Auth::id());
            })->where('payments.status', 'approved')->sum('payments.amount'),
        ];

        return view('livewire.staff.payments-list', [
            'payments' => $payments,
            'stats' => $stats,
        ]);
    }
}
