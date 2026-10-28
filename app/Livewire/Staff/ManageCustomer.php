<?php

namespace App\Livewire\Staff;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use App\Livewire\Concerns\WithDynamicLayout;

#[\Livewire\Attributes\Title('My Customers')]
class ManageCustomer extends Component
{
    use WithDynamicLayout;
    use WithPagination;

    public $search = '';
    public $perPage = 10;
    public $editingId = null;
    public $editingData = [
        'name' => '',
        'phone' => '',
        'email' => '',
        'address' => '',
        'type' => 'retail'
    ];
    public $showEditModal = false;
    public $showDeleteConfirm = false;
    public $deleteId = null;

    public function mount()
    {
        $this->loadCustomers();
    }

    public function loadCustomers()
    {
        // Customers are filtered by staff member
    }

    public function editCustomer($id)
    {
        $customer = Customer::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$customer) {
            $this->dispatch('show-error', 'Customer not found or access denied.');
            return;
        }

        $this->editingId = $id;
        $this->editingData = [
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address' => $customer->address,
            'type' => $customer->type
        ];
        $this->showEditModal = true;
    }

    public function saveCustomer()
    {
        $this->validate([
            'editingData.name' => 'required|string|max:255',
            'editingData.phone' => 'required|string|max:20',
            'editingData.email' => 'nullable|email',
            'editingData.address' => 'nullable|string',
            'editingData.type' => 'required|in:retail,wholesale,distributor'
        ]);

        $customer = Customer::where('id', $this->editingId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$customer) {
            $this->dispatch('show-error', 'Customer not found or access denied.');
            return;
        }

        $customer->update($this->editingData);
        $this->showEditModal = false;
        $this->resetEditForm();
        $this->dispatch('show-success', 'Customer updated successfully.');
    }

    public function confirmDelete($id)
    {
        $customer = Customer::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$customer) {
            $this->dispatch('show-error', 'Customer not found or access denied.');
            return;
        }

        $this->deleteId = $id;
        $this->showDeleteConfirm = true;
    }

    public function deleteCustomer()
    {
        $customer = Customer::where('id', $this->deleteId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$customer) {
            $this->dispatch('show-error', 'Customer not found or access denied.');
            return;
        }

        $customer->delete();
        $this->showDeleteConfirm = false;
        $this->deleteId = null;
        $this->dispatch('show-success', 'Customer deleted successfully.');
    }

    public function resetEditForm()
    {
        $this->editingId = null;
        $this->editingData = [
            'name' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
            'type' => 'retail'
        ];
    }

    public function render()
    {
        $query = Customer::where('user_id', Auth::id());

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('phone', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate($this->perPage);

        return view('livewire.staff.manage-customer', [
            'customers' => $customers,
        ])->layout($this->layout);
    }
}
