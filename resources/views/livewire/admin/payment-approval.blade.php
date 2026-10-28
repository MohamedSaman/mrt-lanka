<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-cash-coin text-success me-2"></i> Staff Payment Collection
            </h3>
            <p class="text-muted mb-0">View payments collected by staff members</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search invoice or customer...">
                    </div>
                </div>
                <div class="col-md-2">
                    <select wire:model.live="staffFilter" class="form-select">
                        <option value="">All Staff</option>
                        @foreach($staffUsers as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->name }} ({{ ucfirst($staff->staff_type) }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select wire:model.live="statusFilter" class="form-select">
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="">All Status</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" wire:model.live="dateFrom" class="form-control" title="From Date">
                </div>
                <div class="col-md-2">
                    <input type="date" wire:model.live="dateTo" class="form-control" title="To Date">
                </div>
                <div class="col-md-1">
                    <select wire:model.live="perPage" class="form-select form-select-sm" title="Entries">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Payments List --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Invoice</th>
                            <th>Customer</th>
                            <th>Collected By</th>
                            <th class="text-end">Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Collected At</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                        <tr>
                            <td class="ps-4">
                                <span class="fw-medium">{{ $payment->sale->invoice_number ?? 'N/A' }}</span>
                            </td>
                            <td>{{ $payment->sale->customer->name ?? $payment->customer->name ?? 'N/A' }}</td>
                            <td>{{ $payment->collectedBy->name ?? 'N/A' }}</td>
                            <td class="text-end fw-semibold">Rs. {{ number_format($payment->amount, 2) }}</td>
                            <td>
                                <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</span>
                            </td>
                            <td>
                                @if($payment->status === 'pending')
                                    <span class="badge bg-warning"><i class="bi bi-hourglass-split me-1"></i>Pending</span>
                                @elseif($payment->status === 'approved')
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Approved</span>
                                @else
                                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejected</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $payment->collected_at?->format('M d, Y H:i') ?? 'N/A' }}</td>
                            <td class="text-end pe-4">
                                @if($payment->status === 'pending')
                                <div class="btn-group">
                                    <button wire:click="openApproveModal({{ $payment->id }})" class="btn btn-sm btn-success">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button wire:click="openRejectModal({{ $payment->id }})" class="btn btn-sm btn-danger">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                @else
                                <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                                No payments to display.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $payments->links() }}
    </div>

    {{-- Approve Modal --}}
    @if($showApproveModal && $selectedPayment)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Approve Payment</h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeApproveModal"></button>
                </div>
                <div class="modal-body">
                    <div class="bg-light rounded p-3 mb-3">
                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted d-block">Customer</small>
                                <span class="fw-bold">{{ $selectedPayment->customer->name ?? ($selectedPayment->sale->customer->name ?? 'N/A') }}</span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Amount</small>
                                <span class="fw-bold text-success">Rs. {{ number_format($selectedPayment->amount, 2) }}</span>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted d-block">Payment Method</small>
                                <span class="fw-medium">{{ ucfirst(str_replace('_', ' ', $selectedPayment->payment_method)) }}</span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Collected By</small>
                                <span class="fw-medium">{{ $selectedPayment->collectedBy->name ?? 'N/A' }}</span>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div>
                            <small class="text-muted d-block">Collected At</small>
                            <span class="fw-medium">{{ $selectedPayment->collected_at?->format('M d, Y H:i A') ?? 'N/A' }}</span>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Approving this payment will update the customer's due amount and mark this payment as approved.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeApproveModal">Cancel</button>
                    <button wire:click="approvePayment" class="btn btn-success" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="approvePayment">
                            <i class="bi bi-check-lg me-2"></i>Approve Payment
                        </span>
                        <span wire:loading wire:target="approvePayment">
                            <span class="spinner-border spinner-border-sm me-2"></span>Processing...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Reject Modal --}}
    @if($showRejectModal && $selectedPayment)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Reject Payment</h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeRejectModal"></button>
                </div>
                <div class="modal-body">
                    <div class="bg-light rounded p-3 mb-3">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted d-block">Amount</small>
                                <span class="fw-bold">Rs. {{ number_format($selectedPayment->amount, 2) }}</span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Collected By</small>
                                <span class="fw-medium">{{ $selectedPayment->collectedBy->name ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea wire:model="rejectionReason" class="form-control" rows="3" placeholder="Please provide a reason for rejection..."></textarea>
                        @error('rejectionReason') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeRejectModal">Cancel</button>
                    <button wire:click="rejectPayment" class="btn btn-danger">
                        <i class="bi bi-x-circle me-2"></i>Reject Payment
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
