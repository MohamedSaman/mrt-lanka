<div>
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-clipboard-check text-primary me-2"></i> Staff Expense Approval
            </h3>
            <p class="text-muted mb-0">Review and approve staff submitted expenses</p>
        </div>
        @if($pendingCount > 0)
        <span class="badge bg-warning text-dark fs-6">
            <i class="bi bi-exclamation-circle me-1"></i> {{ $pendingCount }} Pending
        </span>
        @endif
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-clock text-warning fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1 small">Pending Approval</p>
                            <h4 class="fw-bold mb-0">Rs. {{ number_format($totalPending, 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-check-circle text-success fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1 small">Total Approved</p>
                            <h4 class="fw-bold mb-0">Rs. {{ number_format($totalApproved, 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-x-circle text-danger fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1 small">Total Rejected</p>
                            <h4 class="fw-bold mb-0">Rs. {{ number_format($totalRejected, 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Expenses List Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold text-dark mb-0">
                <i class="bi bi-journal-text text-primary me-2"></i> Staff Expenses
            </h5>
        </div>

        <div class="card-body">
            <!-- Filters -->
            <div class="row mb-3 g-2">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" placeholder="Search..." wire:model.live="search">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" wire:model.live="status_filter">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" wire:model.live="staff_filter">
                        <option value="">All Staff</option>
                        @foreach($staffList as $staff)
                            <option value="{{ $staff->id }}">
                                {{ $staff->name }} 
                                ({{ ucfirst(str_replace('_', ' ', $staff->staff_type)) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="perPage" class="form-select" title="Entries per page">
                        <option value="10">10 per page</option>
                        <option value="15">15 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
            </div>

            <!-- Date Range Filters -->
            <div class="row mb-3 g-2">
                <div class="col-md-6">
                    <label class="form-label small text-muted">From Date</label>
                    <input type="date" class="form-control" wire:model.live="dateFrom" placeholder="From Date">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">To Date</label>
                    <input type="date" class="form-control" wire:model.live="dateTo" placeholder="To Date">
                </div>
            </div>

            <!-- Expenses Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Staff</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expenses as $expense)
                            <tr>
                                <td>{{ $expense->expense_date->format('d M, Y') }}</td>
                                <td>
                                    <div>
                                        <span class="fw-medium">{{ $expense->staff->name ?? 'Unknown' }}</span>
                                        <small class="d-block text-muted">
                                            {{ ucfirst(str_replace('_', ' ', $expense->staff->staff_type ?? '')) }}
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-medium">{{ $expense->expense_type }}</span>
                                </td>
                                <td>
                                    <span class="text-muted">{{ Str::limit($expense->description, 40) ?: '-' }}</span>
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold">Rs. {{ number_format($expense->amount, 2) }}</span>
                                </td>
                                <td>
                                    @if($expense->status === 'pending')
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-clock me-1"></i> Pending
                                        </span>
                                    @elseif($expense->status === 'approved')
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i> Approved
                                        </span>
                                    @else
                                        <span class="badge bg-danger">
                                            <i class="bi bi-x-circle me-1"></i> Rejected
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($expense->status === 'pending')
                                        <button class="btn btn-sm btn-success me-1" wire:click="openApprovalModal({{ $expense->id }})" title="Approve">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" wire:click="openRejectModal({{ $expense->id }})" title="Reject">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    @else
                                        @if($expense->admin_notes)
                                            <span class="text-muted small" data-bs-toggle="tooltip" title="{{ $expense->admin_notes }}">
                                                <i class="bi bi-chat-dots"></i>
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        No expenses found
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-center mt-3">
                {{ $expenses->links('livewire.custom-pagination') }}
            </div>
        </div>
    </div>

    <!-- Approval Modal -->
    @if($showApprovalModal && $selectedExpense)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-check-circle me-2"></i> Approve Expense
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeApprovalModal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="row">
                            <div class="col-6">
                                <p class="text-muted mb-1">Staff</p>
                                <p class="fw-medium mb-2">{{ $selectedExpense->staff->name ?? 'Unknown' }}</p>
                            </div>
                            <div class="col-6">
                                <p class="text-muted mb-1">Date</p>
                                <p class="fw-medium mb-2">{{ $selectedExpense->expense_date->format('d M, Y') }}</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <p class="text-muted mb-1">Type</p>
                                <p class="fw-medium mb-2">{{ $selectedExpense->expense_type }}</p>
                            </div>
                            <div class="col-6">
                                <p class="text-muted mb-1">Amount</p>
                                <p class="fw-bold text-success mb-2">Rs. {{ number_format($selectedExpense->amount, 2) }}</p>
                            </div>
                        </div>
                        @if($selectedExpense->description)
                        <div>
                            <p class="text-muted mb-1">Description</p>
                            <p class="mb-0">{{ $selectedExpense->description }}</p>
                        </div>
                        @endif
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label">Admin Notes (Optional)</label>
                        <textarea class="form-control" rows="2" wire:model="admin_notes" placeholder="Add any notes..."></textarea>
                    </div>
                    <div class="alert alert-success small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Approving this will deduct Rs. {{ number_format($selectedExpense->amount, 2) }} from cash in hand.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeApprovalModal">Cancel</button>
                    <button type="button" class="btn btn-success" wire:click="approveExpense">
                        <i class="bi bi-check-lg me-1"></i> Approve Expense
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Reject Modal -->
    @if($showRejectModal && $selectedExpense)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-x-circle me-2"></i> Reject Expense
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeRejectModal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="row">
                            <div class="col-6">
                                <p class="text-muted mb-1">Staff</p>
                                <p class="fw-medium mb-2">{{ $selectedExpense->staff->name ?? 'Unknown' }}</p>
                            </div>
                            <div class="col-6">
                                <p class="text-muted mb-1">Amount</p>
                                <p class="fw-bold text-danger mb-2">Rs. {{ number_format($selectedExpense->amount, 2) }}</p>
                            </div>
                        </div>
                        <div>
                            <p class="text-muted mb-1">Type</p>
                            <p class="mb-0">{{ $selectedExpense->expense_type }}</p>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control" rows="3" wire:model="admin_notes" placeholder="Please provide a reason for rejection..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeRejectModal">Cancel</button>
                    <button type="button" class="btn btn-danger" wire:click="rejectExpense">
                        <i class="bi bi-x-lg me-1"></i> Reject Expense
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
