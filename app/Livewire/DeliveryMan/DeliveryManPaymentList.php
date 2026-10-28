<?php

namespace App\Livewire\DeliveryMan;

use App\Models\Payment;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

#[Title('My Payment Collections')]
#[Layout('components.layouts.delivery-man')]
class DeliveryManPaymentList extends Component
{
    use WithPagination;

    public $search = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $perPage = 10;
    public $selectedPayment = null;
    public $showDetailsModal = false;

    protected $paginationTheme = 'bootstrap';

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedDateFrom()
    {
        $this->resetPage();
    }

    public function updatedDateTo()
    {
        $this->resetPage();
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function viewDetails($paymentId)
    {
        $this->selectedPayment = Payment::with([
            'customer',
            'sale.customer',
            'cheque',
            'allocations.sale'
        ])->find($paymentId);

        $this->showDetailsModal = true;
    }

    public function closeDetailsModal()
    {
        $this->showDetailsModal = false;
        $this->selectedPayment = null;
    }

    public function render()
    {
        $query = Payment::with(['customer', 'sale.customer', 'cheque'])
            ->where('collected_by', Auth::id())
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->whereHas('customer', function ($cq) {
                        $cq->where('name', 'like', '%' . $this->search . '%');
                    })
                        ->orWhereHas('sale', function ($saleQ) {
                            $saleQ->where('invoice_number', 'like', '%' . $this->search . '%')
                                ->orWhere('sale_id', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->dateFrom, function ($q) {
                $q->whereDate('collected_at', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($q) {
                $q->whereDate('collected_at', '<=', $this->dateTo);
            })
            ->orderBy('collected_at', 'desc');

        // Calculate summary stats
        $summaryQuery = clone $query;
        $totalCollected = $summaryQuery->sum('amount');
        $totalPayments = $summaryQuery->count();

        return view('livewire.delivery-man.delivery-man-payment-list', [
            'payments' => $query->paginate($this->perPage),
            'totalCollected' => $totalCollected,
            'totalPayments' => $totalPayments,
        ]);
    }
}
