<?php

namespace App\Livewire\Admin;

use App\Models\Salary;
use App\Models\StaffExpense;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use Exception;
use App\Livewire\Concerns\WithDynamicLayout;
use App\Models\User;
use Livewire\Component;

#[Title('Staff Salary')]
class StaffSalary extends Component
{
    use WithDynamicLayout;
    use WithPagination;

    // Search and selection
    public $search = '';
    public $staffResults = [];
    public $selectedStaffId = null;
    public $selectedStaff = null;
    public $showSearchResults = false;

    // Salary form fields
    public $salary_month;
    public $salaryId = null;
    public $isEditMode = false;
    public $basic_salary = 0;
    public $approved_expenses = 0;
    public $monthlyExpenses = [];
    public $allowance = 0;
    public $bonus = 0;
    public $deductions = 0;
    public $advance_salary = 0;
    public $overtime = 0;
    public $net_salary = 0;
    public $salary_type = 'monthly';
    public $payment_status = 'pending';

    // Modal state
    public $showSalaryModal = false;
    public $showViewModal = false;
    public $viewingSalary = null;
    public $viewingSalaryId = null;
    public $showDeleteConfirmModal = false;
    public $deleteConfirmId = null;
    public $deleteConfirmName = '';
    public $perPage = 10;

    protected $queryString = ['search'];

    public function updatedSearch()
    {
        $this->showSearchResults = true;
        if (strlen($this->search) >= 2) {
            $this->staffResults = User::where('role', 'staff')
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('contact', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                })
                ->limit(10)
                ->get()
                ->toArray();
        } else {
            $this->staffResults = [];
        }
    }

    public function selectStaff($staffId)
    {
        $this->selectedStaffId = $staffId;
        $this->selectedStaff = User::with('userDetail')->find($staffId);
        $this->showSearchResults = false;
        $this->search = $this->selectedStaff->name;
    }

    public function clearSelection()
    {
        $this->selectedStaffId = null;
        $this->selectedStaff = null;
        $this->search = '';
        $this->showSearchResults = false;
    }

    public function openAddSalaryModal()
    {
        if (!$this->selectedStaffId) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Please select a staff member first']);
            return;
        }

        // Check if salary already exists for this month
        $salaryMonthDate = \Carbon\Carbon::parse(now()->format('Y-m') . '-01');
        $existingSalary = Salary::where('user_id', $this->selectedStaffId)
            ->whereYear('salary_month', $salaryMonthDate->year)
            ->whereMonth('salary_month', $salaryMonthDate->month)
            ->first();

        if ($existingSalary) {
            $this->dispatch('showToast', ['type' => 'warning', 'message' => 'Salary already exists for this month. Please edit the existing record or select a different month.']);
            return;
        }

        $this->resetSalaryForm();
        $this->isEditMode = false;
        $this->salaryId = null;

        // Fetch basic salary from user details
        $userDetail = $this->selectedStaff->userDetail;
        $this->basic_salary = $userDetail ? $userDetail->basic_salary : 0;

        // Fetch approved staff expenses for this specific staff member for the selected month
        $this->loadExpensesForMonth();

        $this->showSalaryModal = true;
    }

    public function viewSalary($salaryId)
    {
        try {
            $this->viewingSalaryId = $salaryId;
            $this->viewingSalary = Salary::with('user')->find($salaryId);
            if (!$this->viewingSalary) {
                $this->dispatch('showToast', ['type' => 'error', 'message' => 'Salary record not found']);
                return;
            }
            $this->showViewModal = true;
        } catch (Exception $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Error loading salary: ' . $e->getMessage()]);
        }
    }

    public function editSalary($salaryId)
    {
        try {
            $salary = Salary::find($salaryId);
            if (!$salary) {
                $this->dispatch('showToast', ['type' => 'error', 'message' => 'Salary record not found']);
                return;
            }

            $this->salaryId = $salaryId;
            $this->isEditMode = true;
            $this->salary_month = $salary->salary_month->format('Y-m');
            $this->basic_salary = $salary->basic_salary;
            $this->allowance = $salary->allowance;
            $this->bonus = $salary->bonus;
            $this->deductions = $salary->deductions;
            $this->advance_salary = $salary->additional_salary;
            $this->overtime = $salary->overtime;
            $this->net_salary = $salary->net_salary;
            $this->salary_type = $salary->salary_type;
            $this->payment_status = $salary->payment_status;

            // Load expenses for the salary month
            $this->loadExpensesForMonth();

            $this->showSalaryModal = true;
        } catch (Exception $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Error loading salary: ' . $e->getMessage()]);
        }
    }

    public function deleteConfirm($salaryId)
    {
        try {
            $salary = Salary::find($salaryId);
            if (!$salary) {
                $this->dispatch('showToast', ['type' => 'error', 'message' => 'Salary record not found']);
                return;
            }

            $this->deleteConfirmId = $salaryId;
            $this->deleteConfirmName = $salary->user->name . ' - ' . $salary->salary_month->format('F Y');
            $this->showDeleteConfirmModal = true;
        } catch (Exception $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function confirmDelete()
    {
        try {
            $salary = Salary::find($this->deleteConfirmId);
            if (!$salary) {
                $this->dispatch('showToast', ['type' => 'error', 'message' => 'Salary record not found']);
                return;
            }

            $salary->delete();
            $this->showDeleteConfirmModal = false;
            $this->deleteConfirmId = null;
            $this->deleteConfirmName = '';
            $this->dispatch('showToast', ['type' => 'success', 'message' => 'Salary record deleted successfully']);
        } catch (Exception $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Error deleting salary: ' . $e->getMessage()]);
        }
    }

    public function cancelDelete()
    {
        $this->showDeleteConfirmModal = false;
        $this->deleteConfirmId = null;
        $this->deleteConfirmName = '';
    }

    public function markAsPaid()
    {
        try {
            if (!$this->viewingSalaryId) {
                $this->dispatch('showToast', ['type' => 'error', 'message' => 'No salary selected']);
                return;
            }

            $salary = Salary::find($this->viewingSalaryId);
            if (!$salary) {
                $this->dispatch('showToast', ['type' => 'error', 'message' => 'Salary record not found']);
                return;
            }

            $salary->update(['payment_status' => 'paid']);
            $this->viewingSalary = Salary::with('user')->find($this->viewingSalaryId);
            $this->dispatch('showToast', ['type' => 'success', 'message' => 'Salary marked as paid successfully']);
        } catch (Exception $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Error updating salary: ' . $e->getMessage()]);
        }
    }

    #[\Livewire\Attributes\On('updated-salary_month')]
    public function updatedSalaryMonth()
    {
        // Reload expenses when month is changed
        $this->loadExpensesForMonth();
        $this->calculateSalary();
    }

    public function updated($name)
    {
        // Handle salary_month changes
        if ($name === 'salary_month') {
            $this->updatedSalaryMonth();
        }
    }

    private function loadExpensesForMonth()
    {
        if (!$this->salary_month || !$this->selectedStaffId) {
            $this->approved_expenses = 0;
            $this->monthlyExpenses = [];
            return;
        }

        try {
            // Handle both full date (Y-m-d) and month-only (Y-m) formats
            $monthValue = $this->salary_month;
            if (strlen($monthValue) === 7) {
                // Month-only format YYYY-MM from type="month" input
                $monthValue = $monthValue . '-01';
            }

            $salaryDate = \Carbon\Carbon::parse($monthValue);
            $monthStart = $salaryDate->copy()->startOfMonth()->format('Y-m-d');
            $monthEnd = $salaryDate->copy()->endOfMonth()->format('Y-m-d');

            // Fetch approved expenses for the month using the model
            $expenses = StaffExpense::where('staff_id', $this->selectedStaffId)
                ->where('status', 'approved')
                ->whereBetween('expense_date', [$monthStart, $monthEnd])
                ->orderBy('expense_date', 'asc')
                ->get();

            // Store expenses as array for blade display
            $this->monthlyExpenses = $expenses->toArray();

            // Calculate total
            $this->approved_expenses = $expenses->sum('amount');

            // Log for debugging
            Log::info('Loading expenses for month', [
                'staff_id' => $this->selectedStaffId,
                'salary_month' => $this->salary_month,
                'month_start' => $monthStart,
                'month_end' => $monthEnd,
                'expenses_count' => $expenses->count(),
                'total_amount' => $this->approved_expenses
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading expenses: ' . $e->getMessage());
            $this->approved_expenses = 0;
            $this->monthlyExpenses = [];
        }
    }

    public function calculateSalary()
    {
        $this->net_salary = $this->basic_salary
            + $this->approved_expenses
            + (float)$this->allowance
            + (float)$this->bonus
            + (float)$this->overtime
            - (float)$this->deductions
            - (float)$this->advance_salary;

        if ($this->net_salary < 0) {
            $this->dispatch('showToast', ['type' => 'warning', 'message' => 'Net salary is negative']);
        }
    }

    public function saveSalary()
    {
        $this->validate([
            'salary_month' => 'required',
            'basic_salary' => 'required|numeric|min:0',
            'allowance' => 'nullable|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'advance_salary' => 'nullable|numeric|min:0',
            'overtime' => 'nullable|numeric|min:0',
            'net_salary' => 'required|numeric',
            'salary_type' => 'required|in:daily,monthly',
            'payment_status' => 'required|in:pending,paid',
        ]);

        try {
            DB::beginTransaction();

            // Calculate final net salary
            $finalNetSalary = $this->basic_salary
                + $this->approved_expenses
                + (float)$this->allowance
                + (float)$this->bonus
                + (float)$this->overtime
                - (float)$this->deductions
                - (float)$this->advance_salary;

            // Convert month format (YYYY-MM) to date format (YYYY-MM-01)
            $salaryMonthDate = \Carbon\Carbon::parse($this->salary_month . '-01');

            if ($this->isEditMode && $this->salaryId) {
                // Update existing salary
                $salary = Salary::find($this->salaryId);
                if (!$salary) {
                    throw new Exception('Salary record not found');
                }

                $salary->update([
                    'salary_month' => $salaryMonthDate->format('Y-m-d'),
                    'salary_type' => $this->salary_type,
                    'basic_salary' => $this->basic_salary,
                    'allowance' => $this->allowance,
                    'bonus' => $this->bonus,
                    'deductions' => $this->deductions,
                    'additional_salary' => $this->advance_salary,
                    'overtime' => $this->overtime,
                    'net_salary' => $finalNetSalary,
                    'payment_status' => $this->payment_status,
                ]);

                $message = 'Salary record updated successfully';
            } else {
                // Create new salary - check for duplicate
                $existingSalary = Salary::where('user_id', $this->selectedStaffId)
                    ->whereYear('salary_month', $salaryMonthDate->year)
                    ->whereMonth('salary_month', $salaryMonthDate->month)
                    ->first();

                if ($existingSalary) {
                    throw new Exception('Salary already exists for this month. Please edit the existing record.');
                }

                Salary::create([
                    'user_id' => $this->selectedStaffId,
                    'salary_month' => $salaryMonthDate->format('Y-m-d'),
                    'salary_type' => $this->salary_type,
                    'basic_salary' => $this->basic_salary,
                    'allowance' => $this->allowance,
                    'bonus' => $this->bonus,
                    'deductions' => $this->deductions,
                    'additional_salary' => $this->advance_salary,
                    'overtime' => $this->overtime,
                    'net_salary' => $finalNetSalary,
                    'payment_status' => $this->payment_status,
                    'total_hours' => 0,
                    'overtime_hours' => 0,
                ]);

                $message = 'Salary record created successfully';
            }

            DB::commit();

            $this->dispatch('showToast', ['type' => 'success', 'message' => $message]);
            $this->closeSalaryModal();
        } catch (Exception $e) {
            DB::rollBack();
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Error saving salary: ' . $e->getMessage()]);
        }
    }

    public function resetSalaryForm()
    {
        // Format as YYYY-MM for month input
        $this->salary_month = now()->format('Y-m');
        $this->allowance = 0;
        $this->bonus = 0;
        $this->deductions = 0;
        $this->advance_salary = 0;
        $this->overtime = 0;
        $this->net_salary = 0;
        $this->salary_type = 'monthly';
        $this->payment_status = 'pending';
        $this->approved_expenses = 0;
        $this->monthlyExpenses = [];
    }

    public function closeSalaryModal()
    {
        $this->showSalaryModal = false;
        $this->resetSalaryForm();
        $this->isEditMode = false;
        $this->salaryId = null;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingSalary = null;
        $this->viewingSalaryId = null;
    }

    public function render()
    {
        $salaries = [];

        if ($this->selectedStaffId) {
            $salaries = Salary::where('user_id', $this->selectedStaffId)
                ->orderBy('salary_month', 'desc')
                ->paginate($this->perPage);
        }

        return view('livewire.admin.staff-salary', [
            'salaries' => $salaries,
            'monthlyExpenses' => $this->monthlyExpenses,
        ])->layout($this->layout);
    }
}
