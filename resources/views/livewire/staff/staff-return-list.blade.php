<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-arrow-return-left text-success me-2"></i> Product Returns List
            </h3>
            <p class="text-muted mb-0">View and manage all product returns</p>
        </div>
    </div>

    <!-- Returns Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-list-ul text-primary me-2"></i> Returns List
                </h5>
                <span class="badge bg-primary">{{ count($returns) }} records</span>
            </div>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3" style="width: 60%; margin: auto">
                <!-- 🔍 Search Bar -->
                <div class="search-bar flex-grow-1">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" wire:model.live="search"
                            placeholder="Search by product or customer...">
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 ms-3">
                <label class="text-sm text-muted fw-medium">Status</label>
                <select wire:model.live="statusFilter" class="form-select form-select-sm" style="width: 120px;">
                    <option value="all">All</option>
                    <option value="approved">Approved</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </div>
        <div class="card-body p-0 overflow-auto">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Customer</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Condition</th>
                            <th class="text-end pe-4">Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($returns as $index => $return)
                        <tr>
                            <td class="ps-4 fw-medium text-muted">{{ $index + 1 }}</td>
                            <td class="text-nowrap">
                                <span class="text-dark">{{ $return->created_at->format('M d, Y') }}</span>
                                <small class="text-muted d-block">{{ $return->created_at->format('H:i') }}</small>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $return->product->name }}</div>
                                <small class="text-muted"><i class="bi bi-upc me-1"></i>{{ $return->product->code ?? $return->product->barcode }}</small>
                            </td>
                            <td>
                                <span class="text-dark">{{ $return->customer->name ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $return->quantity }} units</span>
                            </td>
                            <td>
                                <span class="text-dark">Rs. {{ number_format($return->unit_price, 2) }}</span>
                            </td>
                            <td class="fw-bold text-dark">
                                Rs. {{ number_format($return->total_amount, 2) }}
                            </td>
                            <td>
                                @if($return->status === 'approved')
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle me-1"></i>Approved
                                    </span>
                                @elseif($return->status === 'pending')
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-clock me-1"></i>Pending
                                    </span>
                                @else
                                    <span class="badge bg-danger">
                                        <i class="bi bi-x-circle me-1"></i>Rejected
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($return->is_damaged)
                                    <span class="badge bg-danger">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Damaged
                                    </span>
                                @else
                                    <span class="badge bg-success">
                                        <i class="bi bi-check2-circle me-1"></i>Good
                                    </span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <div class="text-truncate" style="max-width: 200px;" title="{{ $return->reason ?? 'N/A' }}">
                                    {{ $return->reason ?? 'N/A' }}
                                </div>
                                @if($return->notes)
                                    <small class="text-muted d-block text-truncate" style="max-width: 200px;" title="{{ $return->notes }}">
                                        {{ $return->notes }}
                                    </small>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <i class="bi bi-arrow-return-left display-4 d-block mb-2"></i>
                                No returns found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($returns->hasPages())
            <div class="card-footer bg-light">
                <div class="d-flex justify-content-center">
                    {{ $returns->links('livewire.custom-pagination') }}
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
