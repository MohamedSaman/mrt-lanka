<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-arrow-counterclockwise text-danger me-2"></i> Returned & Cancelled Cheques
            </h3>
            <p class="text-muted mb-0">View and manage customer and supplier returned cheques</p>
        </div>
    </div>

    {{-- Navigation Tabs --}}
    <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3">
        <li class="nav-item">
            <button class="nav-link px-4 py-2 fw-bold {{ $activeTab === 'customer' ? 'active bg-primary text-white' : 'bg-light text-dark border' }}" 
                wire:click="setActiveTab('customer')">
                <i class="bi bi-person-x me-2"></i> Customer Returned Cheques
                <span class="badge {{ $activeTab === 'customer' ? 'bg-white text-primary' : 'bg-secondary' }} ms-2">
                    {{ $cheques->total() ?? 0 }}
                </span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link px-4 py-2 fw-bold {{ $activeTab === 'supplier' ? 'active bg-danger text-white' : 'bg-light text-dark border' }}" 
                wire:click="setActiveTab('supplier')">
                <i class="bi bi-truck me-2"></i> Supplier Returned Cheques
                <span class="badge {{ $activeTab === 'supplier' ? 'bg-white text-danger' : 'bg-secondary' }} ms-2">
                    {{ $supplierCheques->total() ?? 0 }}
                </span>
            </button>
        </li>
    </ul>

    {{-- Statistics Cards --}}
    <div class="row mb-4">
        @if($activeTab === 'customer')
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-start border-danger border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-danger text-uppercase mb-1">Customer Returned Cheques</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">{{ $returnCount ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-arrow-counterclockwise fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-start border-secondary border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-secondary text-uppercase mb-1">Customer Cancelled Cheques</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">{{ $cancelledCount ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-x-circle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-start border-warning border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">Customer Overdue Cheques</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">{{ $overdueCount ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-exclamation-triangle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        @else
        <div class="col-xl-6 col-md-6 mb-3">
            <div class="card border-start border-danger border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-danger text-uppercase mb-1">Supplier Returned Cheques</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">{{ $supplierReturnedCount ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-arrow-counterclockwise fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6 col-md-6 mb-3">
            <div class="card border-start border-success border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">Total Supplier Returned List</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">{{ $supplierCheques->total() ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-truck fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Filters Card --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3 col-sm-6">
                    <label class="form-label text-muted small fw-bold mb-1">
                        <i class="bi bi-calendar-month text-danger me-1"></i> From (Month & Year)
                    </label>
                    <input type="month" class="form-control form-control-sm" wire:model.live="fromMonth">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label text-muted small fw-bold mb-1">
                        <i class="bi bi-calendar-month text-danger me-1"></i> To (Month & Year)
                    </label>
                    <input type="month" class="form-control form-control-sm" wire:model.live="toMonth">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label text-muted small fw-bold mb-1">
                        <i class="bi bi-funnel text-danger me-1"></i> Status
                    </label>
                    <select wire:model.live="statusFilter" class="form-select form-select-sm">
                        <option value="all">All Returned & Cancelled</option>
                        <option value="return">Returned Only</option>
                        <option value="cancelled">Cancelled Only</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6">
                    <button type="button" wire:click="resetFilters" class="btn btn-outline-secondary btn-sm w-100" @if(!$fromMonth && !$toMonth && !$search && $statusFilter === 'all') disabled @endif>
                        <i class="bi bi-arrow-clockwise me-1"></i> Reset Filters
                    </button>
                </div>
            </div>
            @if($fromMonth || $toMonth)
            <div class="mt-2 text-muted small">
                <i class="bi bi-info-circle me-1"></i> Filtering cheques by Cheque Date:
                <span class="fw-semibold text-dark">
                    @if($fromMonth && $toMonth)
                        {{ \Carbon\Carbon::parse($fromMonth . '-01')->format('F Y') }}
                        to
                        {{ \Carbon\Carbon::parse($toMonth . '-01')->format('F Y') }}
                    @elseif($fromMonth)
                        From {{ \Carbon\Carbon::parse($fromMonth . '-01')->format('F Y') }} onwards
                    @elseif($toMonth)
                        Up to {{ \Carbon\Carbon::parse($toMonth . '-01')->format('F Y') }}
                    @endif
                </span>
            </div>
            @endif
        </div>
    </div>

    {{-- Customer Cheque Table --}}
    @if($activeTab === 'customer')
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="fw-bold mb-0 text-primary">
                    <i class="bi bi-person-x text-primary me-2"></i> Customer Returned & Cancelled Cheque List
                </h5>
                <span class="badge bg-primary mt-1">{{ $cheques->total() ?? 0 }} records</span>
            </div>
            <div class="flex-grow-1" style="max-width: 450px;">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" wire:model.live.debounce.300ms="search"
                        placeholder="Search by cheque number, customer, bank...">
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label class="text-sm text-muted fw-medium mb-0">Show</label>
                <select wire:model.live="perPage" class="form-select form-select-sm" style="width: 80px;">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-sm text-muted">entries</span>
            </div>
        </div>
        <div class="card-body p-0 overflow-auto">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Cheque No</th>
                            <th>Customer</th>
                            <th class="text-center">Bank</th>
                            <th class="text-center">Amount</th>
                            <th class="text-center">Date</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cheques as $cheque)
                        <tr wire:key="cheque-{{ $cheque->id }}">
                            <td class="ps-4 fw-bold text-primary">{{ $cheque->cheque_number }}</td>
                            <td>{{ $cheque->customer->name ?? '-' }}</td>
                            <td class="text-center">{{ $cheque->bank_name }}</td>
                            <td class="text-center fw-bold">Rs.{{ number_format($cheque->cheque_amount, 2) }}</td>
                            <td class="text-center">{{ $cheque->cheque_date ? date('M d, Y', strtotime($cheque->cheque_date)) : '-' }}</td>
                            <td class="text-center">
                                <span class="badge bg-{{ $cheque->status == 'return' ? 'danger' : ($cheque->status == 'cancelled' ? 'secondary' : 'warning') }}">
                                    {{ ucfirst($cheque->status) }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                @if($cheque->status == 'return')
                                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#rechequeModal" wire:click="setSelectedCheque({{ $cheque->id }})">
                                    <i class="bi bi-arrow-repeat me-1"></i> Re-Cheque
                                </button>
                                @else
                                <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-x-circle display-4 d-block mb-2"></i>
                                No customer returned or cancelled cheques found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($cheques->hasPages())
            <div class="card-footer bg-light">
                <div class="d-flex justify-content-center">
                    {{ $cheques->links('livewire.custom-pagination') }}
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Supplier Cheque Table --}}
    @if($activeTab === 'supplier')
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="fw-bold mb-0 text-danger">
                    <i class="bi bi-truck text-danger me-2"></i> Supplier Returned Cheque List
                </h5>
                <span class="badge bg-danger mt-1">{{ $supplierCheques->total() ?? 0 }} records</span>
            </div>
            <div class="flex-grow-1" style="max-width: 450px;">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" wire:model.live.debounce.300ms="search"
                        placeholder="Search by cheque number, supplier, bank...">
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label class="text-sm text-muted fw-medium mb-0">Show</label>
                <select wire:model.live="perPage" class="form-select form-select-sm" style="width: 80px;">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-sm text-muted">entries</span>
            </div>
        </div>
        <div class="card-body p-0 overflow-auto">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Cheque No</th>
                            <th>Supplier</th>
                            <th class="text-center">Bank</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Cheque Date</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($supplierCheques as $payment)
                        <tr wire:key="supplier-return-cheque-{{ $payment->id }}">
                            <td class="ps-4 fw-bold text-danger">{{ $payment->cheque_number ?? 'N/A' }}</td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $payment->supplier->name ?? 'N/A' }}</div>
                                <small class="text-muted">{{ $payment->supplier->mobile ?? 'N/A' }}</small>
                            </td>
                            <td class="text-center">{{ $payment->bank_name ?? '-' }}</td>
                            <td class="text-end fw-bold text-danger">Rs.{{ number_format($payment->amount, 2) }}</td>
                            <td class="text-center">{{ $payment->cheque_date ? date('M d, Y', strtotime($payment->cheque_date)) : '-' }}</td>
                            <td class="text-center">
                                <span class="badge bg-danger">Returned</span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                        type="button"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false">
                                        <i class="bi bi-gear-fill me-1"></i> Actions
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow">
                                        <li>
                                            <button class="dropdown-item" wire:click="confirmCompleteSupplierCheque({{ $payment->id }})">
                                                <i class="bi bi-check2-circle text-success me-2"></i> Complete
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-x-circle display-4 d-block mb-2"></i>
                                No supplier returned cheques found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($supplierCheques->hasPages())
            <div class="card-footer bg-light">
                <div class="d-flex justify-content-center">
                    {{ $supplierCheques->links('livewire.custom-pagination') }}
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Re-Cheque Modal -->
    <div wire:ignore.self class="modal fade" id="rechequeModal" tabindex="-1" aria-labelledby="rechequeModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-arrow-repeat me-2"></i> Re-Cheque
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form wire:submit.prevent="rechequeSubmit">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Cheque Number</label>
                            <input type="text" class="form-control" wire:model.defer="newChequeNumber" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bank Name</label>
                            <input type="text" class="form-control" wire:model.defer="newBankName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cheque Date</label>
                            <input type="date" class="form-control" wire:model.defer="newChequeDate" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" class="form-control" wire:model.defer="newChequeAmount" readonly style="background-color: #e9ecef; cursor: not-allowed;" required>
                            <small class="text-muted">Amount is automatically copied from the returned cheque</small>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Cheque</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('script')
<script>
    // Clean up any stuck modals on page load
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        document.body.style.removeProperty('overflow');
    });
</script>
@endpush