<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1" style="color: #2a83df;">My Payments</h2>
            <p class="text-muted">View all payments submitted and their approval status</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('staff.billing') }}" class="btn btn-outline-primary rounded-2">
                <i class="bi bi-plus-circle"></i> New Sale
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-primary border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Payments</h6>
                            <h3 class="fw-bold" style="color: #2a83df;">{{ $stats['total_payments'] }}</h3>
                        </div>
                        <i class="bi bi-cash-stack fs-1 text-primary opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-warning border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Pending Approval</h6>
                            <h3 class="fw-bold text-warning">{{ $stats['pending_count'] }}</h3>
                        </div>
                        <i class="bi bi-clock-history fs-1 text-warning opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-success border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Approved</h6>
                            <h3 class="fw-bold text-success">{{ $stats['approved_count'] }}</h3>
                        </div>
                        <i class="bi bi-check-circle fs-1 text-success opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-danger border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Rejected</h6>
                            <h3 class="fw-bold text-danger">{{ $stats['rejected_count'] }}</h3>
                        </div>
                        <i class="bi bi-x-circle fs-1 text-danger opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Search</label>
                    <input type="text" class="form-control" wire:model.live="search" placeholder="Invoice, customer name or phone...">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Filter by Status</label>
                    <select class="form-select" wire:model.live="statusFilter">
                        <option value="all">All Status</option>
                        <option value="pending">Pending Approval</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Entries Per Page</label>
                    <select class="form-select" wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" wire:click="clearFilters">
                        <i class="bi bi-x-circle"></i> Clear
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="card-title mb-0 fw-bold" style="color: #2a83df;">
                <i class="bi bi-list-check me-2"></i>Payment History
            </h5>
        </div>
        <div class="card-body p-0">
            @if ($payments->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="150">Invoice No.</th>
                                <th>Customer Name</th>
                                <th width="120">Payment Date</th>
                                <th width="100">Amount</th>
                                <th width="120">Payment Method</th>
                                <th width="120">Status</th>
                                <th width="100" class="text-center">Attachment</th>
                                <th width="80" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payments as $payment)
                                <tr>
                                    <td>
                                        <span class="badge bg-info rounded-pill">{{ $payment->sale->invoice_number }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ $payment->sale->customer->name ?? 'N/A' }}</strong>
                                        <div class="text-muted small">{{ $payment->sale->customer->phone ?? '' }}</div>
                                    </td>
                                    <td>
                                        {{ \Carbon\Carbon::parse($payment->payment_date)->format('d M, Y') }}
                                        <div class="text-muted small">{{ \Carbon\Carbon::parse($payment->payment_date)->format('H:i A') }}</div>
                                    </td>
                                    <td>
                                        <strong class="text-success">Rs. {{ number_format($payment->amount, 2) }}</strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $payment->due_payment_method ?? 'N/A' }}</span>
                                    </td>
                                    <td>
                                        @php
                                            $statusInfo = match($payment->status) {
                                                'pending' => ['class' => 'bg-warning', 'icon' => 'clock-history', 'text' => 'Pending Approval'],
                                                'approved' => ['class' => 'bg-success', 'icon' => 'check-circle', 'text' => 'Admin Approved'],
                                                'rejected' => ['class' => 'bg-danger', 'icon' => 'x-circle', 'text' => 'Admin Rejected'],
                                                default => ['class' => 'bg-secondary', 'icon' => 'question-circle', 'text' => 'Unknown']
                                            };
                                        @endphp
                                        <span class="badge {{ $statusInfo['class'] }}">
                                            <i class="bi bi-{{ $statusInfo['icon'] }} me-1"></i>{{ $statusInfo['text'] }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @if ($payment->due_payment_attachment)
                                            <a href="{{ asset('storage/' . $payment->due_payment_attachment) }}" 
                                               target="_blank" 
                                               class="btn btn-sm btn-outline-primary" 
                                               title="View Attachment">
                                                <i class="bi bi-paperclip"></i>
                                            </a>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <button wire:click="viewPaymentDetails({{ $payment->id }})" 
                                                class="btn btn-sm btn-outline-info" 
                                                title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Showing {{ $payments->firstItem() }} to {{ $payments->lastItem() }} of {{ $payments->total() }} entries
                        </small>
                        {{ $payments->links() }}
                    </div>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-3 text-muted mb-3"></i>
                    <p class="text-muted">No payment records found</p>
                    @if ($search || $statusFilter !== 'all')
                        <button wire:click="clearFilters" class="btn btn-sm btn-outline-primary">
                            Clear Filters
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
