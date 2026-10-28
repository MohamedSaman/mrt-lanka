<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-graph-up text-primary me-2"></i> Staff Sales Details
            </h3>
            <p class="text-muted mb-0">Track and manage staff sales performance efficiently</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="printStaffSales()" class="btn btn-primary">
                <i class="bi bi-printer me-2"></i> Print Report
            </button>
            <button wire:click="exportToCsv" class="btn btn-success">
                <i class="bi bi-file-earmark-excel me-2"></i> Export CSV
            </button>
        </div>
    </div>

    {{-- Staff Sales Summary --}}
    <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-dark mb-1">
                    <i class="bi bi-journal-text text-primary me-2"></i> Staff Sales Summary
                </h5>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Staff</th>
                            <th class="d-none d-md-table-cell">Email</th>
                            <th class="d-none d-sm-table-cell">Contact</th>
                            <th class="text-center">Total Qty</th>
                            <th class="text-center d-none d-lg-table-cell">Total Value</th>
                            <th class="text-center">Sold Qty</th>
                            <th class="text-center d-none d-lg-table-cell">Sold Value</th>
                            <th class="text-center">Available</th>
                            <th class="text-center d-none d-lg-table-cell">Avail Value</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($staffSales as $sale)
                        <tr>
                            <td class="ps-4">{{ $loop->iteration }}</td>
                            <td>
                                <span class="fw-medium text-dark">{{ $sale->name ?? 'N/A' }}</span>
                                <div class="d-sm-none small text-muted">
                                    {{ $sale->contact ?? 'N/A' }}
                                </div>
                                <div class="d-md-none small text-muted">
                                    {{ $sale->email ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell">{{ $sale->email ?? 'N/A' }}</td>
                            <td class="d-none d-sm-table-cell">{{ $sale->contact ?? 'N/A' }}</td>
                            <td class="text-center">
                                <span class="fw-bold text-dark">{{ $sale->total_quantity }}</span>
                            </td>
                            <td class="text-center d-none d-lg-table-cell">
                                <span class="fw-bold text-dark">Rs.{{ number_format($sale->total_value, 2) }}</span>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold text-success">{{ $sale->sold_quantity }}</span>
                            </td>
                            <td class="text-center d-none d-lg-table-cell">
                                <span class="fw-bold text-success">Rs.{{ number_format($sale->sold_value, 2) }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $sale->available_quantity > 0 ? 'primary' : 'danger' }}">
                                    {{ $sale->available_quantity }}
                                </span>
                            </td>
                            <td class="text-center d-none d-lg-table-cell">
                                <span class="fw-bold text-{{ $sale->total_value - $sale->sold_value > 0 ? 'primary' : 'danger' }}">
                                    Rs.{{ number_format($sale->total_value - $sale->sold_value, 2) }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-link text-primary p-0" 
                                        wire:click="viewSaleDetails({{ $sale->user_id }})" 
                                        wire:loading.attr="disabled">
                                    <i class="bi bi-eye fs-6"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">
                                <i class="bi bi-graph-up display-4 d-block mb-2"></i>
                                No sales records found
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>


@push('styles')
<style>
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
    }

    .card-header {
        background-color: white;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        border-radius: 12px 12px 0 0 !important;
        padding: 1.25rem 1.5rem;
    }

    .summary-card {
        border-left: 4px solid;
        transition: all 0.3s ease;
    }

    .summary-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
    }

    .summary-card:nth-child(1) {
        border-left-color: #4361ee;
    }

    .summary-card:nth-child(2) {
        border-left-color: #4cc9f0;
    }

    .table th {
        border-top: none;
        font-weight: 600;
        color: #6c757d;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table td {
        vertical-align: middle;
        padding: 1rem 0.75rem;
    }

    .btn-link {
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .btn-link:hover {
        transform: scale(1.1);
    }

    .modal-content {
        border: none;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }

    .btn {
        border-radius: 8px;
        font-weight: 500;
        padding: 0.75rem 1.5rem;
        transition: all 0.3s ease;
    }

    .btn-primary {
        background-color: #4361ee;
        border-color: #4361ee;
    }

    .btn-primary:hover {
        background-color: #3f37c9;
        border-color: #3f37c9;
        transform: translateY(-2px);
    }

    .btn-success {
        background-color: #4cc9f0;
        border-color: #4cc9f0;
    }

    .btn-success:hover {
        background-color: #3a7bd5;
        border-color: #3a7bd5;
        transform: translateY(-2px);
    }

    .alert {
        border-radius: 8px;
        border: none;
    }

    .badge {
        font-size: 0.75rem;
        padding: 0.35rem 0.65rem;
        border-radius: 6px;
    }
</style>
@endpush

@push('scripts')
{{-- Sale Details Modal --}}
@if($isViewModalOpen)
<div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content rounded-0">
            <div class="modal-header text-white rounded-0" style="background: linear-gradient(135deg, #2a83df 0%, #1a5fb8 100%);">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-receipt me-2"></i>{{ $staffName }} - Sale Details
                </h5>
                <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
            </div>
            <div class="modal-body p-4">
                {{-- Summary Stats --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Total Sales Items</h6>
                                <h3 class="fw-bold text-primary">{{ count($selectedSaleDetails) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Total Sold Quantity</h6>
                                <h3 class="fw-bold text-success">{{ data_get($summaryStats, 'sold_quantity', 0) }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sales List --}}
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-3">
                        <h6 class="card-title mb-0 fw-bold">Recent Sales</h6>
                    </div>
                    <div class="card-body p-0">
                        @if(!empty($selectedSaleDetails))
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="120">Invoice No.</th>
                                        <th width="150">Date</th>
                                        <th width="100">Amount</th>
                                        <th width="100">Items</th>
                                        <th width="120">Payment Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($selectedSaleDetails as $sale)
                                    <tr>
                                        <td>
                                            <span class="badge bg-info">{{ $sale->invoice_number }}</span>
                                        </td>
                                        <td>
                                            {{ \Carbon\Carbon::parse($sale->created_at)->format('d M, Y H:i') }}
                                        </td>
                                        <td>
                                            <strong class="text-success">Rs. {{ number_format($sale->total_amount, 2) }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">{{ count(array_filter($saleItems, fn($item) => $item->invoice_number === $sale->invoice_number)) }} items</span>
                                        </td>
                                        <td>
                                            @php
                                                $statusClass = match($sale->payment_status) {
                                                    'paid' => 'bg-success',
                                                    'partial' => 'bg-warning',
                                                    'pending' => 'bg-danger',
                                                    default => 'bg-secondary'
                                                };
                                                $statusText = match($sale->payment_status) {
                                                    'paid' => 'Paid',
                                                    'partial' => 'Partial',
                                                    'pending' => 'Pending',
                                                    default => 'Unknown'
                                                };
                                            @endphp
                                            <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-inbox display-4 mb-2"></i>
                            <p>No sales found for this staff member</p>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Sale Items List --}}
                @if(!empty($saleItems))
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-light py-3">
                        <h6 class="card-title mb-0 fw-bold">Sale Items</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Invoice No.</th>
                                        <th>Product Name</th>
                                        <th width="80">Qty</th>
                                        <th width="100">Unit Price</th>
                                        <th width="100">Discount</th>
                                        <th width="100">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($saleItems as $item)
                                    <tr>
                                        <td>
                                            <span class="badge bg-info">{{ $item->invoice_number }}</span>
                                        </td>
                                        <td>{{ $item->product_name }}</td>
                                        <td>{{ $item->quantity }}</td>
                                        <td>Rs. {{ number_format($item->unit_price, 2) }}</td>
                                        <td>Rs. {{ number_format($item->total_discount, 2) }}</td>
                                        <td><strong>Rs. {{ number_format($item->total, 2) }}</strong></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>
            <div class="modal-footer bg-light rounded-0">
                <button type="button" class="btn btn-secondary" wire:click="closeModal">
                    <i class="bi bi-x me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<script>
    function printStaffSales() {
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        const tableContent = document.querySelector('.table-responsive').innerHTML;

        const htmlContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Staff Sales Report</title>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
                    th { background-color: #f2f2f2; }
                    .header { text-align: center; margin-bottom: 20px; }
                    .header h2 { margin-bottom: 5px; }
                    .header p { margin-top: 0; color: #666; }
                    .badge { padding: 3px 8px; border-radius: 4px; font-size: smaller; }
                    .bg-primary { background-color: #0d6efd; color: white; }
                    .bg-danger { background-color: #dc3545; color: white; }
                    .bg-success { background-color: #198754; color: white; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>Staff Sales Report</h2>
                    <p>Generated on ${new Date().toLocaleString()}</p>
                </div>
                ${tableContent}
                <div style="text-align: center; margin-top: 20px;">
                    <button onclick="window.print();" style="padding: 8px 16px; background: #0d6efd; color: white; border: none; border-radius: 4px; cursor: pointer;">Print Report</button>
                    <button onclick="window.close();" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">Close</button>
                </div>
            </body>
            </html>
        `;

        printWindow.document.write(htmlContent);
        printWindow.document.close();
        printWindow.onload = function() {
            printWindow.focus();
        };
    }

    function printStaffDetails() {
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        const staffInfo = document.querySelector('.modal-body .card-body').innerHTML;
        const summaryStats = document.querySelectorAll('.modal-body .row.g-4.mb-4 .card');
        const productsTable = document.querySelector('.modal-body .table-responsive').innerHTML;
        const staffName = document.querySelector('.modal-title').textContent.trim();

        let summaryHtml = '<div style="display: flex; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap;">';
        summaryStats.forEach(card => {
            summaryHtml += '<div style="width: 48%; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 10px;">';
            summaryHtml += '<div style="background-color: #f8f9fa; padding: 10px; border-bottom: 1px solid #ddd;">';
            summaryHtml += card.querySelector('.card-header').textContent;
            summaryHtml += '</div>';
            summaryHtml += '<div style="padding: 15px;">';
            summaryHtml += card.querySelector('.card-body').innerHTML;
            summaryHtml += '</div>';
            summaryHtml += '</div>';
        });
        summaryHtml += '</div>';

        const htmlContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>${staffName} - Sales Details</title>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; margin: 0; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
                    .staff-info { margin-bottom: 20px; }
                    .row { display: flex; }
                    .col-4 { width: 33.33%; }
                    .text-center { text-align: center; }
                    .border-end { border-right: 1px solid #eee; }
                    .fw-bold { font-weight: bold; }
                    .text-success { color: green; }
                    .text-danger { color: red; }
                    .text-muted { color: #6c757d; }
                    .badge { padding: 3px 8px; border-radius: 4px; font-size: smaller; }
                    .bg-success { background-color: #198754; color: white; }
                    .bg-danger { background-color: #dc3545; color: white; }
                    .bg-warning { background-color: #ffc107; color: black; }
                    .bg-info { background-color: #0dcaf0; color: white; }
                    .bg-light { background-color: #f8f9fa; color: black; }
                    .bg-primary { background-color: #0d6efd; color: white; }
                    img { max-width: 100%; }
                    @media print {
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>${staffName}</h1>
                    <p>Sales Report - Generated on ${new Date().toLocaleString()}</p>
                </div>
                
                <div class="staff-info">
                    <h3>Staff Information</h3>
                    ${staffInfo}
                </div>
                
                <div class="summary-stats">
                    <h3>Sales Summary</h3>
                    ${summaryHtml}
                </div>
                
                <div class="products">
                    <h3>Product Details</h3>
                    ${productsTable}
                </div>
                
                <div class="no-print" style="text-align: center; margin-top: 30px;">
                    <button onclick="window.print();" style="padding: 8px 16px; background: #0d6efd; color: white; border: none; border-radius: 4px; cursor: pointer;">Print Report</button>
                    <button onclick="window.close();" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">Close</button>
                </div>
            </body>
            </html>
        `;

        printWindow.document.write(htmlContent);
        printWindow.document.close();
        printWindow.onload = function() {
            printWindow.focus();
        };
    }
</script>
@endpush