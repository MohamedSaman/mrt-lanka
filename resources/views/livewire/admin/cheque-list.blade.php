<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-journal-check text-success me-2"></i> Cheque Management
            </h3>
            <p class="text-muted mb-0">View and manage customer and supplier cheques</p>
        </div>
    </div>

    {{-- Navigation Tabs --}}
    <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3">
        <li class="nav-item">
            <button class="nav-link px-4 py-2 fw-bold {{ $activeTab === 'customer' ? 'active bg-primary text-white' : 'bg-light text-dark border' }}" 
                wire:click="setActiveTab('customer')">
                <i class="bi bi-person-check me-2"></i> Customer Cheques
                <span class="badge {{ $activeTab === 'customer' ? 'bg-white text-primary' : 'bg-secondary' }} ms-2">
                    {{ $cheques->total() ?? 0 }}
                </span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link px-4 py-2 fw-bold {{ $activeTab === 'supplier' ? 'active bg-success text-white' : 'bg-light text-dark border' }}" 
                wire:click="setActiveTab('supplier')">
                <i class="bi bi-truck me-2"></i> Supplier Cheques
                <span class="badge {{ $activeTab === 'supplier' ? 'bg-white text-success' : 'bg-secondary' }} ms-2">
                    {{ $supplierCheques->total() ?? 0 }}
                </span>
            </button>
        </li>
    </ul>

    {{-- Statistics Cards --}}
    <div class="row mb-4">
        @if($activeTab === 'customer')
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-start border-warning border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">Customer Pending Cheques</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">{{ $pendingCount ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-clock-history fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-start border-success border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">Customer Completed Cheques</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">{{ $completeCount ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-check2-circle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-start border-danger border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-danger text-uppercase mb-1">Customer Overdue Cheques</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">{{ $overdueCount ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-exclamation-triangle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        @else
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-start border-warning border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">Supplier Pending Cheques</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">{{ $supplierPendingCount ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-clock-history fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-start border-success border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">Supplier Completed Cheques</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">{{ $supplierCompleteCount ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-check2-circle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
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
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-start border-secondary border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-secondary text-uppercase mb-1">Supplier Overdue Cheques</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">{{ $supplierOverdueCount ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-exclamation-triangle fa-2x text-gray-300"></i></div>
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
                        <i class="bi bi-calendar-month text-primary me-1"></i> From (Month & Year)
                    </label>
                    <input type="month" class="form-control form-control-sm" wire:model.live="fromMonth">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label text-muted small fw-bold mb-1">
                        <i class="bi bi-calendar-month text-primary me-1"></i> To (Month & Year)
                    </label>
                    <input type="month" class="form-control form-control-sm" wire:model.live="toMonth">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label text-muted small fw-bold mb-1">
                        <i class="bi bi-funnel text-primary me-1"></i> Status
                    </label>
                    <select wire:model.live="statusFilter" class="form-select form-select-sm">
                        <option value="all">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="complete">Complete</option>
                        <option value="overdue">Overdue</option>
                        <option value="return">Return</option>
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
                    <i class="bi bi-person-check text-primary me-2"></i> Customer Cheque List
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
                                <span class="badge bg-{{ $cheque->status == 'pending' ? 'warning' : ($cheque->status == 'complete' ? 'success' : ($cheque->status == 'return' ? 'danger' : 'secondary')) }}">
                                    {{ ucfirst($cheque->status) }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                @if($cheque->status == 'pending' || $cheque->status == 'overdue')
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                        type="button"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false">
                                        <i class="bi bi-gear-fill me-1"></i> Actions
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <button class="dropdown-item" wire:click="confirmComplete({{ $cheque->id }})">
                                                <i class="bi bi-check2-circle text-success me-2"></i> Complete
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" wire:click="confirmReturn({{ $cheque->id }})">
                                                <i class="bi bi-arrow-counterclockwise text-danger me-2"></i> Return
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                @else
                                <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-x-circle display-4 d-block mb-2"></i>
                                No customer cheques found.
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
                <h5 class="fw-bold mb-0 text-success">
                    <i class="bi bi-truck text-success me-2"></i> Supplier Cheque List (Purchase Payments)
                </h5>
                <span class="badge bg-success mt-1">{{ $supplierCheques->total() ?? 0 }} records</span>
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
                        @php
                            $st = strtolower($payment->cheque_status ?? 'pending');
                            if ($st === 'pending' && $payment->cheque_date && \Carbon\Carbon::parse($payment->cheque_date)->isPast()) {
                                $st = 'overdue';
                            }
                        @endphp
                        <tr wire:key="supplier-cheque-{{ $payment->id }}">
                            <td class="ps-4 fw-bold text-success">{{ $payment->cheque_number ?? 'N/A' }}</td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $payment->supplier->name ?? 'N/A' }}</div>
                                <small class="text-muted">{{ $payment->supplier->mobile ?? 'N/A' }}</small>
                            </td>
                            <td class="text-center">{{ $payment->bank_name ?? '-' }}</td>
                            <td class="text-end fw-bold text-success">Rs.{{ number_format($payment->amount, 2) }}</td>
                            <td class="text-center">{{ $payment->cheque_date ? date('M d, Y', strtotime($payment->cheque_date)) : '-' }}</td>
                            <td class="text-center">
                                @if($st === 'complete' || $st === 'paid')
                                    <span class="badge bg-success">Complete</span>
                                @elseif($st === 'returned' || $st === 'rejected')
                                    <span class="badge bg-danger">Returned</span>
                                @elseif($st === 'overdue')
                                    <span class="badge bg-secondary">Overdue</span>
                                @else
                                    <span class="badge bg-warning text-dark">Pending</span>
                                @endif
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
                                        @if($st !== 'complete' && $st !== 'paid')
                                        <li>
                                            <button class="dropdown-item" wire:click="confirmCompleteSupplierCheque({{ $payment->id }})">
                                                <i class="bi bi-check2-circle text-success me-2"></i> Complete
                                            </button>
                                        </li>
                                        @endif
                                        @if($st !== 'returned' && $st !== 'rejected')
                                        <li>
                                            <button class="dropdown-item text-danger" wire:click="confirmReturnSupplierCheque({{ $payment->id }})">
                                                <i class="bi bi-arrow-counterclockwise text-danger me-2"></i> Return
                                            </button>
                                        </li>
                                        @endif
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-x-circle display-4 d-block mb-2"></i>
                                No supplier cheques found in purchase payments.
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
</div>