<?php

namespace App\Livewire\Salesman;

use App\Models\StaffExpense;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

#[Layout('components.layouts.salesman')]
#[Title('My Expenses')]
class SalesmanExpenses extends Component
{
    use WithPagination;

    public $expense_type = '';
    public $amount = '';
    public $description = '';
    public $expense_date = '';
    public $status_filter = 'all';
    public $search = '';

    // Modal states
    public $showAddModal = false;
    public $showDeleteModal = false;
    public $expenseToDelete = null;

    protected $paginationTheme = 'bootstrap';

    protected $rules = [
        'expense_type' => 'required|string|max:255',
        'amount' => 'required|numeric|min:0.01',
        'description' => 'nullable|string',
        'expense_date' => 'required|date',
    ];

    public function mount()
    {
        $this->expense_date = date('Y-m-d');
    }

    public function openAddModal()
    {
        $this->resetForm();
        $this->showAddModal = true;
    }

    public function closeAddModal()
    {
        $this->showAddModal = false;
        $this->resetForm();
    }

    public function addExpense()
    {
        $this->validate();

        try {
            DB::beginTransaction();

            // Create staff expense with auto-approval
            $staffExpense = StaffExpense::create([
                'staff_id' => Auth::id(),
                'expense_type' => $this->expense_type,
                'amount' => $this->amount,
                'description' => $this->description,
                'expense_date' => $this->expense_date,
                'status' => 'approved',
                'admin_notes' => 'Auto-approved',
            ]);

            // Create a regular expense record
            \App\Models\Expense::create([
                'category' => 'Staff Expense - ' . $this->expense_type,
                'amount' => $this->amount,
                'description' => 'Staff: ' . Auth::user()->name . ' - ' . ($this->description ?? $this->expense_type),
                'date' => $this->expense_date,
                'expense_type' => 'daily',
            ]);

            // Update cash in hands - subtract expense amount
            $cashInHandRecord = DB::table('cash_in_hands')->where('key', 'cash_amount')->first();

            if ($cashInHandRecord) {
                DB::table('cash_in_hands')
                    ->where('key', 'cash_amount')
                    ->update([
                        'value' => $cashInHandRecord->value - $this->amount,
                        'updated_at' => now()
                    ]);
            }

            // Update today's POS session if expense is for today
            try {
                if (\Carbon\Carbon::parse($this->expense_date)->isToday()) {
                    $session = \App\Models\POSSession::getTodaySession(Auth::id());
                    if ($session) {
                        $session->expenses = ($session->expenses ?? 0) + $this->amount;
                        $session->save();
                        $session->calculateDifference();
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to update POS session after staff expense: ' . $e->getMessage());
            }

            DB::commit();

            $this->js("Swal.fire('Success!', 'Expense added and approved successfully!', 'success')");
            $this->closeAddModal();
            $this->resetPage();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->js("Swal.fire('Error!', 'Error adding expense: " . $e->getMessage() . "', 'error')");
        }
    }

    public function resetForm()
    {
        $this->expense_type = '';
        $this->amount = '';
        $this->description = '';
        $this->expense_date = date('Y-m-d');
        $this->resetValidation();
    }

    public function confirmDelete($expenseId)
    {
        $this->expenseToDelete = $expenseId;
        $this->showDeleteModal = true;
    }

    public function cancelDelete()
    {
        $this->expenseToDelete = null;
        $this->showDeleteModal = false;
    }

    public function deleteExpense()
    {
        try {
            $expense = StaffExpense::where('staff_id', Auth::id())
                ->where('id', $this->expenseToDelete)
                ->where('status', 'pending')
                ->first();

            if ($expense) {
                $expense->delete();
                $this->js("Swal.fire('Deleted!', 'Expense deleted successfully.', 'success')");
            } else {
                $this->js("Swal.fire('Error!', 'Cannot delete this expense. Only pending expenses can be deleted.', 'error')");
            }
        } catch (\Exception $e) {
            $this->js("Swal.fire('Error!', 'Error deleting expense: " . $e->getMessage() . "', 'error')");
        }

        $this->cancelDelete();
    }

    public function render()
    {
        $query = StaffExpense::where('staff_id', Auth::id());

        // Apply status filter
        if ($this->status_filter !== 'all') {
            $query->where('status', $this->status_filter);
        }

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('expense_type', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        $expenses = $query->orderBy('expense_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Calculate totals
        $totalPending = StaffExpense::where('staff_id', Auth::id())->pending()->sum('amount');
        $totalApproved = StaffExpense::where('staff_id', Auth::id())->approved()->sum('amount');
        $totalRejected = StaffExpense::where('staff_id', Auth::id())->rejected()->sum('amount');

        return view('livewire.salesman.salesman-expenses', [
            'expenses' => $expenses,
            'totalPending' => $totalPending,
            'totalApproved' => $totalApproved,
            'totalRejected' => $totalRejected,
        ]);
    }
}
