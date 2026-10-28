<?php

namespace App\Livewire\DeliveryMan;

use App\Models\Sale;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;

#[Title('Completed Deliveries')]
#[Layout('components.layouts.delivery-man')]
class DeliveryManCompletedDeliveries extends Component
{
    use WithPagination;

    public $search = '';
    public $dateFilter = '';
    public $selectedSale = null;
    public $showDetailsModal = false;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedDateFilter()
    {
        $this->resetPage();
    }

    public function viewDetails($saleId)
    {
        $this->selectedSale = Sale::with(['customer', 'items.product', 'user', 'payments', 'approvedBy'])
            ->find($saleId);
        $this->showDetailsModal = true;
    }

    public function closeDetailsModal()
    {
        $this->showDetailsModal = false;
        $this->selectedSale = null;
    }

    public function render()
    {
        $sales = Sale::where('delivered_by', Auth::id())
            ->where('delivery_status', 'delivered')
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('sale_id', 'like', '%' . $this->search . '%')
                        ->orWhere('invoice_number', 'like', '%' . $this->search . '%')
                        ->orWhereHas('customer', function ($cq) {
                            $cq->where('name', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->dateFilter, function ($q) {
                if ($this->dateFilter === 'today') {
                    $q->whereDate('delivered_at', today());
                } elseif ($this->dateFilter === 'week') {
                    $q->whereBetween('delivered_at', [now()->startOfWeek(), now()->endOfWeek()]);
                } elseif ($this->dateFilter === 'month') {
                    $q->whereMonth('delivered_at', now()->month);
                }
            })
            ->with(['customer'])
            ->orderBy('delivered_at', 'desc')
            ->paginate(15);

        $todayCount = Sale::where('delivered_by', Auth::id())
            ->where('delivery_status', 'delivered')
            ->whereDate('delivered_at', today())
            ->count();

        $totalCount = Sale::where('delivered_by', Auth::id())
            ->where('delivery_status', 'delivered')
            ->count();

        return view('livewire.delivery-man.delivery-man-completed-deliveries', [
            'sales' => $sales,
            'todayCount' => $todayCount,
            'totalCount' => $totalCount,
        ]);
    }
}
