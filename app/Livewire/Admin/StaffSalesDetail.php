<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Sale;
use App\Models\User;
use App\Livewire\Concerns\WithDynamicLayout;

#[Title('Staff Sales Detail')]
class StaffSalesDetail extends Component
{
    use WithDynamicLayout;

    public $staffId;
    public $staff;
    public $salesData = [];
    public $searchTerm = '';
    public $filterStatus = 'all';
    public $paginationData = [];
    public $perPage = 25;
    public $showViewModal = false;
    public $selectedSale = null;

    public function mount($staffId)
    {
        $this->staffId = $staffId;
        $this->loadStaff();
        $this->loadSalesData();
    }

    public function loadStaff()
    {
        $this->staff = User::find($this->staffId);

        if (!$this->staff || $this->staff->role !== 'staff') {
            abort(404, 'Staff member not found');
        }
    }

    public function loadSalesData()
    {
        $query = Sale::with(['customer', 'items', 'payments', 'user'])
            ->where('sale_type', 'staff')
            ->where('user_id', $this->staffId);

        // Apply search filter
        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('invoice_number', 'like', '%' . $this->searchTerm . '%')
                    ->orWhere('sale_id', 'like', '%' . $this->searchTerm . '%')
                    ->orWhereHas('customer', function ($q) {
                        $q->where('name', 'like', '%' . $this->searchTerm . '%');
                    });
            });
        }

        // Apply status filter
        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        $paginated = $query->orderByDesc('created_at')->paginate($this->perPage);

        $this->salesData = $paginated->items();
        $this->paginationData = [
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'from' => $paginated->firstItem(),
            'to' => $paginated->lastItem(),
        ];
    }

    public function updatedSearchTerm()
    {
        $this->loadSalesData();
    }

    public function updatedFilterStatus()
    {
        $this->loadSalesData();
    }

    public function updatedPerPage()
    {
        $this->loadSalesData();
    }

    public function nextPage()
    {
        if ($this->paginationData['current_page'] < $this->paginationData['last_page']) {
            $this->loadSalesDataForPage($this->paginationData['current_page'] + 1);
        }
    }

    public function previousPage()
    {
        if ($this->paginationData['current_page'] > 1) {
            $this->loadSalesDataForPage($this->paginationData['current_page'] - 1);
        }
    }

    public function goToPage($page)
    {
        if ($page >= 1 && $page <= $this->paginationData['last_page']) {
            $this->loadSalesDataForPage($page);
        }
    }

    private function loadSalesDataForPage($page)
    {
        $query = Sale::with(['customer', 'items', 'payments', 'user'])
            ->where('sale_type', 'staff')
            ->where('user_id', $this->staffId);

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('invoice_number', 'like', '%' . $this->searchTerm . '%')
                    ->orWhere('sale_id', 'like', '%' . $this->searchTerm . '%')
                    ->orWhereHas('customer', function ($q) {
                        $q->where('name', 'like', '%' . $this->searchTerm . '%');
                    });
            });
        }

        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        $paginated = $query->orderByDesc('created_at')->paginate($this->perPage, ['*'], 'page', $page);

        $this->salesData = $paginated->items();
        $this->paginationData = [
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'from' => $paginated->firstItem(),
            'to' => $paginated->lastItem(),
        ];
    }

    public function viewSale($saleId)
    {
        $this->selectedSale = Sale::with(['customer', 'items', 'payments', 'user'])
            ->findOrFail($saleId);
        $this->showViewModal = true;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->selectedSale = null;
    }

    public function render()
    {
        return view('livewire.admin.staff-sales-detail')->layout($this->layout);
    }
}
