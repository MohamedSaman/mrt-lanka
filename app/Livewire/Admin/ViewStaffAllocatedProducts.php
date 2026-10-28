<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\User;
use App\Models\StaffProduct;
use App\Livewire\Concerns\WithDynamicLayout;

#[Title('View Staff Allocated Products')]
class ViewStaffAllocatedProducts extends Component
{
    use WithDynamicLayout;

    public $staffId;
    public $staff;
    public $search = '';
    public $allocatedProducts = [];
    public $statusFilter = 'all'; // all, assigned, sold, returned

    public function mount($staffId)
    {
        $this->staffId = $staffId;
        $this->loadStaff();
        $this->loadAllocatedProducts();
    }

    public function loadStaff()
    {
        $this->staff = User::find($this->staffId);

        if (!$this->staff || $this->staff->role !== 'staff') {
            abort(404, 'Staff member not found');
        }
    }

    public function loadAllocatedProducts()
    {
        $query = StaffProduct::where('staff_id', $this->staffId)
            ->with(['product.brand', 'product.category', 'product.price']);

        // Apply search filter
        if ($this->search) {
            $query->whereHas('product', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }

        // Apply status filter
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $this->allocatedProducts = $query->orderBy('created_at', 'desc')->get()
            ->map(function ($item) {
                $item->available_quantity = $item->quantity - $item->sold_quantity;
                return $item;
            });
    }

    public function updatedSearch()
    {
        $this->loadAllocatedProducts();
    }

    public function updatedStatusFilter()
    {
        $this->loadAllocatedProducts();
    }

    public function render()
    {
        return view('livewire.admin.view-staff-allocated-products')->layout($this->layout);
    }
}
