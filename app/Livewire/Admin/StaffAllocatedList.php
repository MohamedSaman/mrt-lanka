<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\User;
use App\Models\StaffProduct;
use Illuminate\Support\Facades\DB;
use App\Livewire\Concerns\WithDynamicLayout;

#[Title('Staff Allocated Products List')]
class StaffAllocatedList extends Component
{
    use WithDynamicLayout;

    public $search = '';
    public $staffMembers = [];

    public function mount()
    {
        $this->loadStaffWithAllocations();
    }

    public function loadStaffWithAllocations()
    {
        $this->staffMembers = User::where('role', 'staff')
            ->withCount(['staffProducts as total_allocated' => function ($query) {
                $query->select(DB::raw('COALESCE(SUM(quantity), 0)'));
            }])
            ->withCount(['staffProducts as total_sold' => function ($query) {
                $query->select(DB::raw('COALESCE(SUM(sold_quantity), 0)'));
            }])
            ->withSum('staffProducts as total_value', 'total_value')
            ->withSum('staffProducts as total_sold_value', 'sold_value')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')
                        ->orWhere('contact', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('name')
            ->get()
            ->map(function ($staff) {
                $staff->available_quantity = $staff->total_allocated - $staff->total_sold;
                $staff->available_value = $staff->total_value - $staff->total_sold_value;
                return $staff;
            });
    }

    public function updatedSearch()
    {
        $this->loadStaffWithAllocations();
    }

    public function render()
    {
        return view('livewire.admin.staff-allocated-list')->layout($this->layout);
    }
}
