<?php

namespace App\Livewire\Staff;

use App\Models\StaffExpense;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

#[Layout("components.layouts.staff")]
#[Title('My Expenses')]
class StaffExpenseManagement extends Component
{
    use WithPagination;

    public $expense_type = '';
    public $amount = '';
    public $description = '';
    public $expense_date = '';
    public $status_filter = 'all';
    public $search = '';

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

    public function addExpense()
    {
        $this->validate();

        try {
            StaffExpense::create([
                'staff_id' => Auth::id(),
                'expense_type' => $this->expense_type,
                'amount' => $this->amount,
                'description' => $this->description,
                'expense_date' => $this->expense_date,
                'status' => 'pending',
            ]);

            session()->flash('message', 'Expense added successfully! Waiting for admin approval.');

            $this->dispatch('close-expense-modal');
            $this->resetForm();
            $this->resetPage();
        } catch (\Exception $e) {
            session()->flash('error', 'Error adding expense: ' . $e->getMessage());
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

    public function deleteExpense($expenseId)
    {
        try {
            $expense = StaffExpense::where('staff_id', Auth::id())
                ->where('id', $expenseId)
                ->where('status', 'pending')
                ->first();

            if ($expense) {
                $expense->delete();
                session()->flash('message', 'Expense deleted successfully.');
            } else {
                session()->flash('error', 'Cannot delete this expense. Only pending expenses can be deleted.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting expense: ' . $e->getMessage());
        }
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

        return view('livewire.staff.staff-expense-management', [
            'expenses' => $expenses,
            'totalPending' => $totalPending,
            'totalApproved' => $totalApproved,
            'totalRejected' => $totalRejected,
        ]);
    }
}
