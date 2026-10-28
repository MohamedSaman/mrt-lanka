<div>
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h5 class="fw-bold mb-1">Product Value Report</h5>
            <p class="text-muted mb-0 small">
                <i class="bi bi-calendar-event me-1"></i>As of {{ now()->format('F d, Y h:i A') }}
            </p>
        </div>
    </div>

    <!-- Summary Card -->
    <div class="row g-3 mb-4">
        <div class="col-lg-4 col-md-6">
            <div class="card border-0 bg-success bg-opacity-10">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1 small">Total Inventory Value</h6>
                            <h3 class="mb-0 fw-bold text-success">Rs. {{ number_format($reportTotal ?? 0, 2) }}</h3>
                        </div>
                        <i class="bi bi-currency-rupee fs-1 text-success opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card border-0 bg-primary bg-opacity-10">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1 small">Total Products</h6>
                            <h3 class="mb-0 fw-bold text-primary">{{ count($reportData) }}</h3>
                        </div>
                        <i class="bi bi-box-seam fs-1 text-primary opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card border-0 bg-info bg-opacity-10">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1 small">Total Available Stock</h6>
                            <h3 class="mb-0 fw-bold text-info">{{ number_format(collect($reportData)->sum('available_stock')) }}</h3>
                        </div>
                        <i class="bi bi-boxes fs-1 text-info opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(empty($reportData))
    <div class="text-center py-5">
        <i class="bi bi-inbox display-4 text-muted"></i>
        <p class="mt-3 text-muted">No product data available</p>
    </div>
    @else
    <!-- Product Value Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th class="text-center" style="width: 80px;">#</th>
                    <th>Product Code</th>
                    <th>Product Name</th>
                    <th class="text-center">Available Stock</th>
                    <th class="text-end">Supplier Price</th>
                    <th class="text-end">Total Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reportData as $index => $item)
                <tr>
                    <td class="text-center text-muted">{{ $index + 1 }}</td>
                    <td>
                        <span class="badge bg-secondary">{{ $item['product_code'] }}</span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div>
                                <div class="fw-semibold">{{ $item['display_name'] }}</div>
                                @if($item['variant_value'])
                                <small class="text-muted">
                                    <i class="bi bi-tag me-1"></i>{{ $item['variant_value'] }}
                                </small>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="text-center">
                        @if($item['available_stock'] > 0)
                        <span class="badge bg-success">{{ number_format($item['available_stock']) }}</span>
                        @else
                        <span class="badge bg-danger">Out of Stock</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <span class="text-primary fw-semibold">Rs. {{ number_format($item['supplier_price'], 2) }}</span>
                    </td>
                    <td class="text-end">
                        <span class="fw-bold text-success">Rs. {{ number_format($item['total_value'], 2) }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <td colspan="5" class="text-end fw-bold">Grand Total:</td>
                    <td class="text-end">
                        <span class="fw-bold text-success fs-5">Rs. {{ number_format($reportTotal ?? 0, 2) }}</span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif
</div>
