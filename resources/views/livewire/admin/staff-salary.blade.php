<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-cash-coin text-success me-2"></i> Staff Salary Management
            </h3>
            <p class="text-muted mb-0">Calculate and manage staff salaries with deductions and allowances</p>
        </div>
    </div>

    {{-- Search Section --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold text-muted">Search Staff by Name, Email or Phone</label>
                    <div class="position-relative">
                        <input type="text" class="form-control form-control-lg" 
                               wire:model.live.debounce.300ms="search" 
                               placeholder="Type staff name, email or phone number..."
                               @if($showSearchResults && count($staffResults) > 0) autocomplete="off" @endif>
                        <i class="bi bi-search position-absolute top-50 end-0 translate-middle-y me-3 text-muted"></i>

                        {{-- Search Results Dropdown --}}
                        @if($showSearchResults && count($staffResults) > 0)
                        <div class="list-group position-absolute w-100 mt-2 shadow-lg" style="max-height: 300px; overflow-y: auto; z-index: 1000;">
                            @foreach($staffResults as $staff)
                            <button type="button" class="list-group-item list-group-item-action" 
                                    wire:click="selectStaff({{ $staff['id'] }})">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1 fw-bold">{{ $staff['name'] }}</h6>
                                    <small class="badge bg-primary">{{ $staff['staff_type'] ?? 'N/A' }}</small>
                                </div>
                                <p class="mb-1 text-muted small">
                                    <i class="bi bi-envelope-fill me-1"></i> {{ $staff['email'] }}
                                    <i class="bi bi-telephone-fill ms-2 me-1"></i> {{ $staff['contact'] }}
                                </p>
                            </button>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-muted">&nbsp;</label>
                    @if($selectedStaff)
                    <button wire:click="clearSelection" class="btn btn-danger w-100">
                        <i class="bi bi-x-circle me-2"></i> Clear Selection
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Selected Staff Info --}}
    @if($selectedStaff)
    <div class="card shadow-sm mb-4 border-success">
        <div class="card-header bg-light border-success">
            <h5 class="fw-bold mb-0">
                <i class="bi bi-person-badge text-success me-2"></i> Selected Staff Details
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <h6 class="text-muted small">Staff Name</h6>
                    <p class="fw-bold fs-5">{{ $selectedStaff->name }}</p>
                </div>
                <div class="col-md-4">
                    <h6 class="text-muted small">Email</h6>
                    <p class="fw-bold">{{ $selectedStaff->email }}</p>
                </div>
                <div class="col-md-4">
                    <h6 class="text-muted small">Contact Number</h6>
                    <p class="fw-bold">{{ $selectedStaff->contact }}</p>
                </div>
                <div class="col-md-4">
                    <h6 class="text-muted small">Staff Type</h6>
                    <p class="fw-bold">
                        @if($selectedStaff->staff_type === 'salesman')
                            <span class="badge bg-primary">Salesman</span>
                        @elseif($selectedStaff->staff_type === 'delivery_man')
                            <span class="badge bg-info">Delivery Man</span>
                        @else
                            <span class="badge bg-secondary">{{ $selectedStaff->staff_type }}</span>
                        @endif
                    </p>
                </div>
                <div class="col-md-4">
                    <h6 class="text-muted small">Basic Salary</h6>
                    <p class="fw-bold text-success fs-5">Rs. {{ number_format($selectedStaff->userDetail->basic_salary ?? 0, 2) }}</p>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-success mt-4" wire:click="openAddSalaryModal">
                        <i class="bi bi-plus-circle me-2"></i> Add Salary
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Salary List --}}
    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="fw-bold mb-0">
                <i class="bi bi-list-check text-primary me-2"></i> Salary Records
            </h5>
        </div>
        <div class="card-body p-0">
            @if($salaries->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Month</th>
                            <th>Type</th>
                            <th>Basic Salary</th>
                            <th>Allowance</th>
                            <th>Bonus</th>
                            <th>Deductions</th>
                            <th>Advance</th>
                            <th>Net Salary</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($salaries as $salary)
                        <tr>
                            <td class="ps-4 fw-medium">{{ $salary->salary_month->format('M Y') }}</td>
                            <td>
                                <span class="badge bg-info">{{ ucfirst($salary->salary_type) }}</span>
                            </td>
                            <td class="fw-bold">Rs. {{ number_format($salary->basic_salary, 2) }}</td>
                            <td>Rs. {{ number_format($salary->allowance, 2) }}</td>
                            <td class="text-success fw-bold">+ Rs. {{ number_format($salary->bonus, 2) }}</td>
                            <td class="text-danger fw-bold">- Rs. {{ number_format($salary->deductions, 2) }}</td>
                            <td class="text-danger fw-bold">- Rs. {{ number_format($salary->additional_salary ?? 0, 2) }}</td>
                            <td class="fw-bold text-success fs-5">Rs. {{ number_format($salary->net_salary, 2) }}</td>
                            <td>
                                @if($salary->payment_status === 'pending')
                                    <span class="badge bg-warning">Pending</span>
                                @else
                                    <span class="badge bg-success">Paid</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="javascript:void(0)" wire:click="viewSalary({{ $salary->salary_id }})" title="View Salary Details">
                                            <i class="bi bi-eye text-primary me-2"></i>View
                                        </a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" wire:click="editSalary({{ $salary->salary_id }})" title="Edit Salary">
                                            <i class="bi bi-pencil text-warning me-2"></i>Edit
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="javascript:void(0)" wire:click="deleteConfirm({{ $salary->salary_id }})" title="Delete Salary">
                                            <i class="bi bi-trash text-danger me-2"></i>Delete
                                        </a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-light">
                <div class="d-flex justify-content-center">
                    {{ $salaries->links('livewire.custom-pagination') }}
                </div>
            </div>
            @else
            <div class="text-center py-5">
                <i class="bi bi-inbox display-4 text-muted d-block mb-3"></i>
                <p class="text-muted">No salary records found for this staff member</p>
            </div>
            @endif
        </div>
    </div>
    @else
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i> <strong>Select a staff member</strong> from the search results above to view and manage their salary records.
    </div>
    @endif

    {{-- Add Salary Modal --}}
    @if($showSalaryModal)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header {{ $isEditMode ? 'bg-warning' : 'bg-success' }} text-white">
                    <h5 class="modal-title fw-bold">
                        @if($isEditMode)
                            <i class="bi bi-pencil-circle me-2"></i> Edit Salary - {{ $selectedStaff->name }}
                        @else
                            <i class="bi bi-plus-circle me-2"></i> Add Salary - {{ $selectedStaff->name }}
                        @endif
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeSalaryModal"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="saveSalary">
                        {{-- Staff Info Section --}}
                        <div class="card bg-light mb-4">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <h6 class="text-muted small">Staff Name</h6>
                                        <p class="fw-bold">{{ $selectedStaff->name }}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted small">Staff Type</h6>
                                        <p class="fw-bold">{{ ucfirst(str_replace('_', ' ', $selectedStaff->staff_type)) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Salary Calculation Section --}}
                        <div class="card bg-light mb-4">
                            <div class="card-header bg-info text-white">
                                <h6 class="fw-bold mb-0">Salary Calculation</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <h6 class="text-muted small">Basic Salary</h6>
                                        <input type="number" step="0.01" class="form-control fw-bold" 
                                               wire:model="basic_salary" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted small">Approved Expenses Total</h6>
                                        <input type="number" step="0.01" class="form-control fw-bold text-success" 
                                               wire:model="approved_expenses" readonly>
                                    </div>
                                </div>
                                
                                {{-- Monthly Expenses List --}}
                                @if(count($monthlyExpenses) > 0)
                                <div class="mt-3">
                                    <h6 class="text-muted small mb-2">
                                        <i class="bi bi-receipt text-info me-1"></i> Expenses for {{ $salary_month ? \Carbon\Carbon::parse($salary_month . '-01')->format('F Y') : 'This Month' }}
                                    </h6>
                                    <div class="table-responsive expenses-table">
                                        <table class="table table-sm table-borderless mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="small">Date</th>
                                                    <th class="small">Type</th>
                                                    <th class="text-end small">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($monthlyExpenses as $expense)
                                                <tr>
                                                    <td class="small">{{ \Carbon\Carbon::parse($expense['expense_date'])->format('d M') }}</td>
                                                    <td class="small">{{ ucfirst(str_replace('_', ' ', $expense['expense_type'])) }}</td>
                                                    <td class="text-end small text-success fw-bold">Rs. {{ number_format($expense['amount'], 2) }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                @else
                                <div class="alert alert-warning small mt-3 mb-0">
                                    <i class="bi bi-exclamation-triangle me-1"></i> No approved expenses for the selected month
                                </div>
                                @endif
                            </div>
                        </div>

                        {{-- Form Fields --}}
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Salary Month <span class="text-danger">*</span></label>
                                <input type="month" class="form-control @error('salary_month') is-invalid @enderror" 
                                       wire:model.live="salary_month" required>
                                @error('salary_month') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Salary Type <span class="text-danger">*</span></label>
                                <select class="form-select @error('salary_type') is-invalid @enderror" wire:model="salary_type">
                                    <option value="monthly">Monthly</option>
                                    <option value="daily">Daily</option>
                                </select>
                                @error('salary_type') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Allowance</label>
                                <input type="number" step="0.01" class="form-control @error('allowance') is-invalid @enderror" 
                                       wire:model="allowance" placeholder="0.00" wire:change="calculateSalary">
                                @error('allowance') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Additional Bonus</label>
                                <input type="number" step="0.01" class="form-control @error('bonus') is-invalid @enderror" 
                                       wire:model="bonus" placeholder="0.00" wire:change="calculateSalary">
                                @error('bonus') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Deductions</label>
                                <input type="number" step="0.01" class="form-control @error('deductions') is-invalid @enderror" 
                                       wire:model="deductions" placeholder="0.00" wire:change="calculateSalary">
                                @error('deductions') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Advance Salary</label>
                                <input type="number" step="0.01" class="form-control @error('advance_salary') is-invalid @enderror" 
                                       wire:model="advance_salary" placeholder="0.00" wire:change="calculateSalary">
                                @error('advance_salary') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Overtime</label>
                                <input type="number" step="0.01" class="form-control @error('overtime') is-invalid @enderror" 
                                       wire:model="overtime" placeholder="0.00" wire:change="calculateSalary">
                                @error('overtime') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Payment Status <span class="text-danger">*</span></label>
                                <select class="form-select @error('payment_status') is-invalid @enderror" wire:model="payment_status">
                                    <option value="pending">Pending</option>
                                    <option value="paid">Paid</option>
                                </select>
                                @error('payment_status') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Net Salary Display --}}
                        <div class="alert alert-success mb-3">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <h6 class="text-muted small mb-1">Total Net Salary</h6>
                                    <h3 class="fw-bold mb-0">Rs. {{ number_format($net_salary, 2) }}</h3>
                                </div>
                                <div class="col-md-6 d-flex align-items-end justify-content-end">
                                    <button type="button" class="btn btn-info" wire:click="calculateSalary">
                                        <i class="bi bi-calculator me-2"></i> Calculate
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg" wire:loading.attr="disabled">
                                <i class="bi bi-check-circle me-2"></i>
                                <span wire:loading.remove>Save Salary Record</span>
                                <span wire:loading>Saving...</span>
                            </button>
                            <button type="button" class="btn btn-secondary" wire:click="closeSalaryModal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- View Salary Modal --}}
    @if($showViewModal && $viewingSalary)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-file-earmark-text me-2"></i> Salary Details - {{ $viewingSalary->user->name }} ({{ $viewingSalary->salary_month->format('F Y') }})
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeViewModal"></button>
                </div>
                <div class="modal-body">
                    {{-- Staff & Month Info --}}
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted small">Staff Name</h6>
                            <p class="fw-bold">{{ $viewingSalary->user->name }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted small">Salary Month</h6>
                            <p class="fw-bold">{{ $viewingSalary->salary_month->format('F Y') }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted small">Salary Type</h6>
                            <p><span class="badge bg-info">{{ ucfirst($viewingSalary->salary_type) }}</span></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted small">Payment Status</h6>
                            <p><span class="badge {{ $viewingSalary->payment_status === 'pending' ? 'bg-warning' : 'bg-success' }}">{{ ucfirst($viewingSalary->payment_status) }}</span></p>
                        </div>
                    </div>

                    <hr>

                    {{-- Salary Breakdown --}}
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted small">Basic Salary</h6>
                            <p class="fw-bold fs-5">Rs. {{ number_format($viewingSalary->basic_salary, 2) }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted small">Allowance</h6>
                            <p class="fw-bold fs-5">Rs. {{ number_format($viewingSalary->allowance, 2) }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted small">Bonus</h6>
                            <p class="fw-bold text-success fs-5">+ Rs. {{ number_format($viewingSalary->bonus, 2) }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted small">Overtime</h6>
                            <p class="fw-bold text-success fs-5">+ Rs. {{ number_format($viewingSalary->overtime, 2) }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted small">Deductions</h6>
                            <p class="fw-bold text-danger fs-5">- Rs. {{ number_format($viewingSalary->deductions, 2) }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted small">Advance Salary</h6>
                            <p class="fw-bold text-danger fs-5">- Rs. {{ number_format($viewingSalary->additional_salary ?? 0, 2) }}</p>
                        </div>
                    </div>

                    <hr>

                    {{-- Net Salary --}}
                    <div class="alert alert-success">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <h6 class="text-muted small mb-1">Total Net Salary</h6>
                                <h3 class="fw-bold mb-0">Rs. {{ number_format($viewingSalary->net_salary, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    @if($viewingSalary->payment_status === 'pending')
                    <button type="button" class="btn btn-success" wire:click="markAsPaid">
                        <i class="bi bi-check-circle me-2"></i> Mark as Paid
                    </button>
                    @endif
                    <button type="button" class="btn btn-secondary" wire:click="closeViewModal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Delete Confirmation Modal --}}
    @if($showDeleteConfirmModal)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-exclamation-triangle me-2"></i> Confirm Delete
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="cancelDelete"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Warning!</strong> This action cannot be undone.
                    </div>
                    <p class="mb-3">Are you sure you want to delete the salary record for:</p>
                    <div class="alert alert-light">
                        <h6 class="fw-bold text-danger">{{ $deleteConfirmName }}</h6>
                    </div>
                    <p class="text-muted small mb-0">Once deleted, the salary data will be permanently removed from the system.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="cancelDelete">
                        <i class="bi bi-x-circle me-2"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger" wire:click="confirmDelete" wire:loading.attr="disabled">
                        <i class="bi bi-trash me-2"></i>
                        <span wire:loading.remove>Delete Salary</span>
                        <span wire:loading>Deleting...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

@push('styles')
<style>
    .card {
        border: none;
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .card:hover {
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
    }

    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 0.75rem 1rem;
    }

    .form-control:focus, .form-select:focus {
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
        border-color: #4361ee;
    }

    .list-group-item {
        cursor: pointer;
        border: none;
        padding: 1rem;
        border-bottom: 1px solid #f0f0f0;
    }

    .list-group-item:hover {
        background-color: #f8f9fa;
    }

    .modal-content {
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }

    .btn {
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-success {
        background-color: #28a745;
        border-color: #28a745;
    }

    .btn-success:hover {
        background-color: #218838;
        border-color: #218838;
        transform: translateY(-2px);
    }

    .alert {
        border-radius: 12px;
        border: none;
    }

    .badge {
        font-size: 0.85rem;
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
    }

    .table {
        margin-bottom: 0;
    }

    .table td {
        vertical-align: middle;
        padding: 1rem 0.75rem;
    }
    .table-responsive.expenses-table{
        min-height: 100px !important;
        overflow-y: auto;
    }
</style>
@endpush

@push('scripts')
<script>
    Livewire.on('showToast', (data) => {
        const { type = 'info', message = '' } = data;
        
        const iconMap = {
            'success': 'success',
            'error': 'error',
            'warning': 'warning',
            'info': 'info'
        };

        Swal.fire({
            icon: iconMap[type] || 'info',
            title: type.charAt(0).toUpperCase() + type.slice(1),
            text: message,
            toast: true,
            position: 'top-right',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
    });
</script>
@endpush
