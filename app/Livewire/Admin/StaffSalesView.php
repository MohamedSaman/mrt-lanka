<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

#[Layout('components.layouts.admin')]
#[Title('Staff Sales Summary')]
class StaffSalesView extends Component
{
    public $staffSummary = [];
    public $searchTerm = '';

    public function mount()
    {
        $this->loadStaffSummary();
    }

    public function loadStaffSummary()
    {
        // Get all staff members with their sales summary
        $staffMembers = User::where('role', 'staff')
            ->orderBy('name')
            ->get();

        $this->staffSummary = $staffMembers->map(function ($staff) {
            // Get all sales for this staff
            $sales = Sale::where('user_id', $staff->id)
                ->where('sale_type', 'staff')
                ->with('payments')
                ->get();

            // Calculate totals
            $totalSales = $sales->count();
            $totalAmount = $sales->sum('total_amount');

            // Paid Amount: Sum of amounts from payments with status 'paid'
            $paidAmount = 0;
            foreach ($sales as $sale) {
                foreach ($sale->payments as $payment) {
                    if ($payment->status === 'approved') {
                        $paidAmount += $payment->amount;
                    }
                }
            }

            // Pending Approval: Sum of amounts from payments with status 'pending' (awaiting payment approval)
            $pendingApprovalAmount = 0;
            foreach ($sales as $sale) {
                foreach ($sale->payments as $payment) {
                    if ($payment->status === 'pending') {
                        $pendingApprovalAmount += $payment->amount;
                    }
                }
            }

            $dueAmount = $sales->sum('due_amount');

            return [
                'staff_id' => $staff->id,
                'staff_name' => $staff->name,
                'staff_email' => $staff->email,
                'total_sales' => $totalSales,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'pending_approval_amount' => $pendingApprovalAmount,
                'due_amount' => $dueAmount,
            ];
        })->filter(function ($item) {
            // Apply search filter
            if ($this->searchTerm) {
                return str_contains(strtolower($item['staff_name']), strtolower($this->searchTerm)) ||
                    str_contains(strtolower($item['staff_email'] ?? ''), strtolower($this->searchTerm));
            }
            return true;
        })->values()->toArray();
    }

    public function updatedSearchTerm()
    {
        $this->loadStaffSummary();
    }

    public function render()
    {
        return view('livewire.admin.staff-sales-view', [
            'staffSummary' => $this->staffSummary,
        ]);
    }
}
