<?php

namespace App\Livewire\Salesman;

use App\Models\Customer;
use App\Models\Sale;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

#[Title('Customer Dues')]
#[Layout('components.layouts.salesman')]
class SalesmanCustomerDues extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedCustomer = null;
    public $showDetailsModal = false;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function viewDetails($customerId)
    {
        $this->selectedCustomer = Customer::with(['sales' => function ($q) {
            $q->where('user_id', Auth::id())
                ->where('status', 'confirm')
                ->where('due_amount', '>', 0)
                ->with('items')
                ->orderBy('created_at', 'desc');
        }])->find($customerId);

        $this->showDetailsModal = true;
    }

    public function closeDetailsModal()
    {
        $this->showDetailsModal = false;
        $this->selectedCustomer = null;
    }

    public function render()
    {
        // Get customers with dues from salesman's approved sales
        $customers = Customer::whereHas('sales', function ($q) {
            $q->where('user_id', Auth::id())
                ->where('status', 'confirm')
                ->where('due_amount', '>', 0);
        })
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('phone', 'like', '%' . $this->search . '%');
                });
            })
            ->withSum(['sales as total_due' => function ($q) {
                $q->where('user_id', Auth::id())
                    ->where('status', 'confirm');
            }], 'due_amount')
            ->orderByDesc('total_due')
            ->paginate(15);

        // Total dues from salesman's customers
        $totalDues = Sale::where('user_id', Auth::id())
            ->where('status', 'confirm')
            ->sum('due_amount');

        return view('livewire.salesman.salesman-customer-dues', [
            'customers' => $customers,
            'totalDues' => $totalDues,
        ]);
    }
}
