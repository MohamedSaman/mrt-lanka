<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1" style="color: #2a83df;">Payment Approvals</h2>
            <p class="text-muted">Review and approve staff member payments</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.staff-payment-approval') }}" class="btn btn-outline-primary rounded-2">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-warning" style="border-width: 4px !important;">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Pending Approvals</h6>
                    <h3 class="fw-bold text-warning">{{ $pendingCount }}</h3>
                    <small class="text-muted">Awaiting review</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-success" style="border-width: 4px !important;">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Approved</h6>
                    <h3 class="fw-bold text-success">{{ $approvedCount }}</h3>
                    <small class="text-muted">Completed payments</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-danger" style="border-width: 4px !important;">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Rejected</h6>
                    <h3 class="fw-bold text-danger">{{ $rejectedCount }}</h3>
                    <small class="text-muted">Declined payments</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Filter by Status</label>
                    <select class="form-select" wire:model.live="filterStatus">
                        <option value="pending">Pending Approval</option>
                        <option value="all">All Status</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Payment Method</label>
                    <select class="form-select" wire:model.live="filterPaymentMethod">
                        <option value="all">All Methods</option>
                        <option value="cash">Cash</option>
                        <option value="cheque">Cheque</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="credit">Credit</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Search</label>
                    <input type="text" class="form-control" wire:model.live="searchTerm" placeholder="Payment ID, Reference or Customer...">
                </div>
            </div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold" style="color: #2a83df;">
                <i class="bi bi-credit-card me-2"></i>Payments List
            </h5>
            <div class="d-flex align-items-center gap-2">
                <label class="text-sm text-muted fw-medium mb-0">Show</label>
                <select wire:model.live="perPage" class="form-select form-select-sm" style="width: 80px;">
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-sm text-muted mb-0">entries</span>
            </div>
        </div>
        <div class="card-body p-0">
            @if ($payments->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="80">Payment ID</th>
                                <th>Customer Name</th>
                                <th>Invoice No.</th>
                                <th width="100">Amount</th>
                                <th width="100">Method</th>
                                <th width="100">Reference</th>
                                <th width="120">Payment Status</th>
                                <th width="100">Date</th>
                                <th width="150" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payments as $payment)
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary">{{ $payment->id }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ $payment->sale->customer->name ?? 'N/A' }}</strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ $payment->sale->invoice_number ?? 'N/A' }}</span>
                                    </td>
                                    <td>
                                        <strong class="text-success">Rs. {{ number_format($payment->amount, 2) }}</strong>
                                    </td>
                                    <td>
                                        @php
                                            $methodBadge = match($payment->payment_method) {
                                                'cash' => ['class' => 'bg-success', 'text' => 'Cash', 'icon' => 'bi-cash-coin'],
                                                'cheque' => ['class' => 'bg-info', 'text' => 'Cheque', 'icon' => 'bi-card-text'],
                                                'bank_transfer' => ['class' => 'bg-warning', 'text' => 'Bank Transfer', 'icon' => 'bi-bank'],
                                                'credit' => ['class' => 'bg-danger', 'text' => 'Credit', 'icon' => 'bi-percent'],
                                                default => ['class' => 'bg-secondary', 'text' => 'Unknown', 'icon' => 'bi-question']
                                            };
                                        @endphp
                                        <span class="badge {{ $methodBadge['class'] }}">
                                            <i class="bi {{ $methodBadge['icon'] }} me-1"></i>{{ $methodBadge['text'] }}
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $payment->payment_reference ?? 'N/A' }}</small>
                                    </td>
                                    <td>
                                        @php
                                            $statusBadge = match($payment->status) {
                                                'pending' => ['class' => 'bg-warning', 'text' => 'Pending Approval'],
                                                'approved' => ['class' => 'bg-success', 'text' => 'Approved'],
                                                'rejected' => ['class' => 'bg-danger', 'text' => 'Rejected'],
                                                'paid' => ['class' => 'bg-success', 'text' => 'Paid'],
                                                default => ['class' => 'bg-secondary', 'text' => 'Unknown']
                                            };
                                        @endphp
                                        <span class="badge {{ $statusBadge['class'] }}">{{ $statusBadge['text'] }}</span>
                                    </td>
                                    <td>
                                        {{ \Carbon\Carbon::parse($payment->created_at)->format('d M, Y') }}
                                    </td>
                                    <td>
                                        @if ($payment->status === 'pending')
                                            <div class="d-flex gap-2 justify-content-center flex-wrap">
                                                <button class="btn btn-sm btn-success" wire:click="approvePayment({{ $payment->id }})" title="Approve Payment">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </button>
                                                <button class="btn btn-sm btn-danger" wire:click="rejectPayment({{ $payment->id }})" title="Reject Payment">
                                                    <i class="bi bi-x-circle"></i> Reject
                                                </button>
                                            </div>
                                        @else
                                            <span class="text-muted small">No action</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-center">
                        {{ $payments->links('livewire.custom-pagination') }}
                    </div>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-3 text-muted mb-3"></i>
                    <p class="text-muted">No payments found</p>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endpush
