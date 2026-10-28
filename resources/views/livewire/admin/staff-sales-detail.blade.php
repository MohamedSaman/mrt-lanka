<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('admin.staff-sales') }}">Staff Sales</a></li>
                    <li class="breadcrumb-item active">{{ $staff->name }}</li>
                </ol>
            </nav>
            <h2 class="fw-bold mb-1" style="color: #2a83df;">Sales by {{ $staff->name }}</h2>
            <p class="text-muted">View all sales created by this staff member</p>
        </div>
        <a href="{{ route('admin.staff-sales') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Search</label>
                    <input type="text" class="form-control" wire:model.live="searchTerm" 
                        placeholder="Invoice number or customer name...">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Filter by Status</label>
                    <select class="form-select" wire:model.live="filterStatus">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="confirm">Confirmed</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold" style="color: #2a83df;">
                <i class="bi bi-table me-2"></i>Sales List
            </h5>
            <div class="d-flex align-items-center gap-2">
                <label class="text-sm text-muted fw-medium mb-0">Show</label>
                <select wire:model.live="perPage" class="form-select form-select-sm" style="width: 80px;">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-sm text-muted mb-0">entries</span>
            </div>
        </div>
        <div class="card-body p-0">
            @if (!empty($salesData) && count($salesData) > 0)
                <div class="table-responsive" style="min-height: 100px !important;">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="150">Invoice No.</th>
                                <th>Customer Name</th>
                                <th width="120">Amount</th>
                                <th width="120">Payment Status</th>
                                <th width="100">Sale Status</th>
                                <th width="120">Date</th>
                                <th width="100" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($salesData as $sale)
                                <tr>
                                    <td>
                                        <span class="badge bg-info rounded-pill">{{ $sale['invoice_number'] }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ $sale['customer']['name'] ?? 'N/A' }}</strong>
                                        @if(isset($sale['customer']['phone']))
                                            <div class="text-muted small">{{ $sale['customer']['phone'] }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <strong class="text-success">Rs. {{ number_format($sale['total_amount'], 2) }}</strong>
                                        @if($sale['due_amount'] > 0)
                                            <div class="text-danger small">Due: Rs. {{ number_format($sale['due_amount'], 2) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $statusClass = match($sale['payment_status']) {
                                                'paid' => 'bg-success',
                                                'partial' => 'bg-warning',
                                                'pending' => 'bg-danger',
                                                default => 'bg-secondary'
                                            };
                                            $statusText = match($sale['payment_status']) {
                                                'paid' => 'Paid',
                                                'partial' => 'Partial',
                                                'pending' => 'Pending',
                                                default => 'Unknown'
                                            };
                                        @endphp
                                        <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
                                    </td>
                                    <td>
                                        @php
                                            $saleStatusClass = match($sale['status']) {
                                                'confirm' => 'bg-success',
                                                'pending' => 'bg-warning',
                                                'rejected' => 'bg-danger',
                                                default => 'bg-secondary'
                                            };
                                        @endphp
                                        <span class="badge {{ $saleStatusClass }}">{{ ucfirst($sale['status']) }}</span>
                                    </td>
                                    <td>
                                        {{ \Carbon\Carbon::parse($sale['created_at'])->format('d M, Y') }}
                                        <div class="text-muted small">{{ \Carbon\Carbon::parse($sale['created_at'])->format('h:i A') }}</div>
                                    </td>
                                    <td class="text-center">
                                        <button wire:click="viewSale({{ $sale['id'] }})" class="btn btn-sm btn-outline-primary" title="View Details">
                                            <i class="bi bi-eye"></i> View
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
                            Showing {{ $paginationData['from'] ?? 0 }} to {{ $paginationData['to'] ?? 0 }} of {{ $paginationData['total'] ?? 0 }} entries
                        </small>
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm mb-0">
                                @if ($paginationData['current_page'] > 1)
                                    <li class="page-item">
                                        <button class="page-link" wire:click="previousPage" wire:loading.attr="disabled">
                                            <i class="bi bi-chevron-left"></i>
                                        </button>
                                    </li>
                                @else
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="bi bi-chevron-left"></i></span>
                                    </li>
                                @endif

                                @for ($i = 1; $i <= $paginationData['last_page']; $i++)
                                    @if ($i === $paginationData['current_page'])
                                        <li class="page-item active">
                                            <span class="page-link">{{ $i }}</span>
                                        </li>
                                    @elseif ($i === 1 || $i === $paginationData['last_page'] || ($i >= $paginationData['current_page'] - 1 && $i <= $paginationData['current_page'] + 1))
                                        <li class="page-item">
                                            <button class="page-link" wire:click="goToPage({{ $i }})" wire:loading.attr="disabled">
                                                {{ $i }}
                                            </button>
                                        </li>
                                    @elseif ($i === $paginationData['current_page'] - 2 || $i === $paginationData['current_page'] + 2)
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    @endif
                                @endfor

                                @if ($paginationData['current_page'] < $paginationData['last_page'])
                                    <li class="page-item">
                                        <button class="page-link" wire:click="nextPage" wire:loading.attr="disabled">
                                            <i class="bi bi-chevron-right"></i>
                                        </button>
                                    </li>
                                @else
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="bi bi-chevron-right"></i></span>
                                    </li>
                                @endif
                            </ul>
                        </nav>
                    </div>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-3 text-muted mb-3"></i>
                    <p class="text-muted">No sales found for this staff member</p>
                </div>
            @endif
        </div>
    </div>

    <!-- View Sale Modal -->
    @if ($showViewModal && $selectedSale)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #2a83df 0%, #1a5fb8 100%);">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-receipt me-2"></i>Sale Details - {{ $selectedSale->invoice_number }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeViewModal"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Sale Header Info -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-muted mb-3">Sale Information</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="fw-bold">Invoice Number:</td>
                                    <td><span class="badge bg-info">{{ $selectedSale->invoice_number }}</span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Sale ID:</td>
                                    <td>{{ $selectedSale->sale_id }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Date:</td>
                                    <td>{{ \Carbon\Carbon::parse($selectedSale->created_at)->format('d M, Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Status:</td>
                                    <td>
                                        @php
                                            $statusClass = match($selectedSale->status) {
                                                'confirm' => 'bg-success',
                                                'pending' => 'bg-warning',
                                                'rejected' => 'bg-danger',
                                                default => 'bg-secondary'
                                            };
                                        @endphp
                                        <span class="badge {{ $statusClass }}">{{ ucfirst($selectedSale->status) }}</span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-muted mb-3">Customer Information</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="fw-bold">Customer Name:</td>
                                    <td>{{ $selectedSale->customer->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Phone:</td>
                                    <td>{{ $selectedSale->customer->phone ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Email:</td>
                                    <td>{{ $selectedSale->customer->email ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Staff Member:</td>
                                    <td>{{ $selectedSale->user->name ?? 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <hr>

                    <!-- Sale Items -->
                    <h6 class="fw-bold text-muted mb-3">Sale Items ({{ count($selectedSale->items ?? []) }})</h6>
                    @if ($selectedSale->items && count($selectedSale->items) > 0)
                    <div class="table-responsive mb-4" style="min-height: 100px !important;">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th width="40">#</th>
                                    <th>Product</th>
                                    <th width="80">Qty</th>
                                    <th width="100">Unit Price</th>
                                    <th width="80">Discount</th>
                                    <th width="100">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($selectedSale->items as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <strong>{{ $item->product_name }}</strong>
                                        <div class="text-muted small">Code: {{ $item->product_code }}</div>
                                    </td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>Rs. {{ number_format($item->unit_price, 2) }}</td>
                                    <td>Rs. {{ number_format($item->total_discount, 2) }}</td>
                                    <td><strong>Rs. {{ number_format($item->total, 2) }}</strong></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted">No items found</p>
                    @endif

                    <hr>

                    <!-- Sale Summary -->
                    <div class="row">
                        <div class="col-md-6 offset-md-6">
                            <h6 class="fw-bold text-muted mb-3">Sale Summary</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="fw-bold">Subtotal:</td>
                                    <td class="text-end">Rs. {{ number_format($selectedSale->subtotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Discount:</td>
                                    <td class="text-end text-danger">- Rs. {{ number_format($selectedSale->discount_amount, 2) }}</td>
                                </tr>
                                <tr class="border-top">
                                    <td class="fw-bold">Total Amount:</td>
                                    <td class="text-end"><strong style="color: #2a83df; font-size: 1.1em;">Rs. {{ number_format($selectedSale->total_amount, 2) }}</strong></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Paid Amount:</td>
                                    <td class="text-end text-success"><strong>Rs. {{ number_format($selectedSale->total_amount - $selectedSale->due_amount, 2) }}</strong></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Due Amount:</td>
                                    <td class="text-end text-danger"><strong>Rs. {{ number_format($selectedSale->due_amount, 2) }}</strong></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Payment Status:</td>
                                    <td class="text-end">
                                        @php
                                            $paymentStatusClass = match($selectedSale->payment_status) {
                                                'paid' => 'bg-success',
                                                'partial' => 'bg-warning',
                                                'pending' => 'bg-danger',
                                                default => 'bg-secondary'
                                            };
                                            $paymentStatusText = match($selectedSale->payment_status) {
                                                'paid' => 'Paid',
                                                'partial' => 'Partial',
                                                'pending' => 'Pending',
                                                default => 'Unknown'
                                            };
                                        @endphp
                                        <span class="badge {{ $paymentStatusClass }}">{{ $paymentStatusText }}</span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    @if ($selectedSale->notes)
                    <hr>
                    <h6 class="fw-bold text-muted mb-2">Notes</h6>
                    <p class="text-muted">{{ $selectedSale->notes }}</p>
                    @endif
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" wire:click="closeViewModal">
                        <i class="bi bi-x me-1"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
