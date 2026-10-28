<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-shop text-primary me-2"></i> Shop Staff Dashboard
            </h3>
            <p class="text-muted mb-0">Welcome back, {{ auth()->user()->name }}!</p>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Total Products</p>
                            <h3 class="fw-bold text-primary mb-0">{{ $totalProducts }}</h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-box-seam text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Low Stock</p>
                            <h3 class="fw-bold text-warning mb-0">{{ $lowStockProducts }}</h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-exclamation-triangle text-warning fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Out of Stock</p>
                            <h3 class="fw-bold text-danger mb-0">{{ $outOfStockProducts }}</h3>
                        </div>
                        <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-x-circle text-danger fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Categories</p>
                            <h3 class="fw-bold text-info mb-0">{{ $totalCategories }}</h3>
                        </div>
                        <div class="bg-info bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-grid text-info fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <a href="{{ route('shop-staff.products') }}" class="btn btn-primary w-100 py-3">
                <i class="bi bi-box-seam me-2"></i> View Product List
            </a>
        </div>
    </div>

    {{-- Info Card --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="bg-info bg-opacity-10 rounded-circle p-3 me-3">
                    <i class="bi bi-info-circle text-info fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1">Your Role: Shop Staff</h6>
                    <p class="text-muted mb-0">As a shop staff member, you have read-only access to view the product catalog. For any product updates or sales operations, please contact the admin or salesman.</p>
                </div>
            </div>
        </div>
    </div>
</div>
