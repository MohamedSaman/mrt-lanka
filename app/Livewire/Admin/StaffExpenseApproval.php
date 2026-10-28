<?php

namespace App\Livewire\Admin;

use App\Models\StaffExpense;
use App\Models\Expense;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\POSSession;
use Illuminate\Support\Facades\Log;
use App\Livewire\Concerns\WithDynamicLayout;

#[Title('Staff Expense Approval')]
class StaffExpenseApproval extends Component
{
    use WithPagination, WithDynamicLayout;

    public $status_filter = 'all';
    public $staff_filter = '';
    public $search = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $perPage = 15;

    // Modal states
    public $showApprovalModal = false;
    public $showRejectModal = false;
    public $selectedExpense = null;
    public $admin_notes = '';

    protected $paginationTheme = 'bootstrap';

    public function viewExpense($id)
    {
        $this->selectedExpense = StaffExpense::with('staff')->find($id);
        $this->admin_notes = '';
    }

    public function openApprovalModal($id)
    {
        $this->selectedExpense = StaffExpense::with('staff')->find($id);
        $this->admin_notes = '';
        $this->showApprovalModal = true;
    }

    public function closeApprovalModal()
    {
        $this->showApprovalModal = false;
        $this->selectedExpense = null;
        $this->admin_notes = '';
    }

    public function openRejectModal($id)
    {
        $this->selectedExpense = StaffExpense::with('staff')->find($id);
        $this->admin_notes = '';
        $this->showRejectModal = true;
    }

    public function closeRejectModal()
    {
        $this->showRejectModal = false;
        $this->selectedExpense = null;
        $this->admin_notes = '';
    }

    public function approveExpense()
    {
        if (!$this->selectedExpense) return;

        try {
            DB::beginTransaction();

            // Update staff expense status
            $this->selectedExpense->update([
                'status' => 'approved',
                'admin_notes' => $this->admin_notes,
            ]);

            // Create a regular expense record
            Expense::create([
                'category' => 'Staff Expense - ' . $this->selectedExpense->expense_type,
                'amount' => $this->selectedExpense->amount,
                'description' => 'Staff: ' . $this->selectedExpense->staff->name . ' - ' . ($this->selectedExpense->description ?? $this->selectedExpense->expense_type),
                'date' => $this->selectedExpense->expense_date,
                'expense_type' => 'daily',
            ]);

            // Update cash in hands - subtract expense amount
            $cashInHandRecord = DB::table('cash_in_hands')->where('key', 'cash_amount')->first();

            if ($cashInHandRecord) {
                DB::table('cash_in_hands')
                    ->where('key', 'cash_amount')
                    ->update([
                        'value' => $cashInHandRecord->value - $this->selectedExpense->amount,
                        'updated_at' => now()
                    ]);
            }

            // Update today's POS session if expense is for today
            try {
                if ($this->selectedExpense->expense_date->isToday()) {
                    $session = POSSession::getTodaySession(Auth::id());
                    if ($session) {
                        $session->expenses = ($session->expenses ?? 0) + $this->selectedExpense->amount;
                        $session->save();
                        $session->calculateDifference();
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to update POS session after staff expense approval: ' . $e->getMessage());
            }

            DB::commit();

            $this->js("Swal.fire('Approved!', 'Staff expense has been approved and recorded.', 'success')");
            $this->closeApprovalModal();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->js("Swal.fire('Error!', 'Failed to approve expense: " . $e->getMessage() . "', 'error')");
        }
    }

    public function rejectExpense()
    {
        if (!$this->selectedExpense) return;

        try {
            $this->selectedExpense->update([
                'status' => 'rejected',
                'admin_notes' => $this->admin_notes,
            ]);

            $this->js("Swal.fire('Rejected!', 'Staff expense has been rejected.', 'info')");
            $this->closeRejectModal();
        } catch (\Exception $e) {
            $this->js("Swal.fire('Error!', 'Failed to reject expense: " . $e->getMessage() . "', 'error')");
        }
    }

    public function render()
    {
        $query = StaffExpense::with('staff');

        // Apply status filter
        if ($this->status_filter !== 'all') {
            $query->where('status', $this->status_filter);
        }

        // Apply staff filter
        if ($this->staff_filter) {
            $query->where('staff_id', $this->staff_filter);
        }

        // Apply date range filter
        if ($this->dateFrom) {
            $query->whereDate('expense_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('expense_date', '<=', $this->dateTo);
        }

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('expense_type', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%')
                    ->orWhereHas('staff', function ($staffQ) {
                        $staffQ->where('name', 'like', '%' . $this->search . '%');
                    });
            });
        }

        $expenses = $query->orderBy('expense_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        // Calculate totals
        $totalPending = StaffExpense::pending()->sum('amount');
        $pendingCount = StaffExpense::pending()->count();
        $totalApproved = StaffExpense::approved()->sum('amount');
        $totalRejected = StaffExpense::rejected()->sum('amount');

        // Get staff list for filter
        $staffList = \App\Models\User::whereNotNull('staff_type')
            ->whereIn('staff_type', ['salesman', 'delivery_man', 'shop_staff'])
            ->orderBy('name')
            ->get(['id', 'name', 'staff_type']);

        return view('livewire.admin.staff-expense-approval', [
            'expenses' => $expenses,
            'totalPending' => $totalPending,
            'pendingCount' => $pendingCount,
            'totalApproved' => $totalApproved,
            'totalRejected' => $totalRejected,
            'staffList' => $staffList,
        ])->layout($this->layout);
    }
}
