<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-box-seam text-primary me-2"></i> Product Catalog
            </h3>
            <p class="text-muted mb-0">View product information and stock levels (read-only)</p>
        </div>
        <a href="{{ route('shop-staff.dashboard') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search by name, code, or model...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="categoryFilter" class="form-select">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="stockFilter" class="form-select">
                        <option value="all">All Stock Levels</option>
                        <option value="in_stock">In Stock (>10)</option>
                        <option value="low_stock">Low Stock (1-10)</option>
                        <option value="out_of_stock">Out of Stock</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Products Grid --}}
    <div class="row g-3">
        @forelse($products as $product)
        @php
            $imageUrl = $product['image'] ? asset('images/product.jpg') : null;
        @endphp
        
        {{-- Product Card - 5 per row --}}
        <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
            <div class="card border-0 shadow-sm h-100 product-card" style="transition: all 0.3s ease;">
                {{-- Stock Badge --}}
                <div class="position-absolute top-0 start-0 m-2" style="z-index: 10;">
                    @if($product['stock'] <= 0)
                        <span class="badge bg-danger text-white" style="font-size: 9px;">Out of Stock</span>
                    @elseif($product['stock'] <= 10)
                        <span class="badge bg-warning text-dark" style="font-size: 9px;">Low Stock</span>
                    @else
                        <span class="badge bg-success text-white" style="font-size: 9px;">In Stock</span>
                    @endif
                    @if($product['variant_value'])
                        <span class="badge bg-primary text-white ms-1" style="font-size: 9px;">Variant</span>
                    @endif
                </div>

                {{-- Product Image --}}
                <div style="height: 180px; overflow: hidden; background-color: #f8f9fa;" class="position-relative">
                    @if($imageUrl)
                        <img src="{{ $imageUrl }}" class="w-100 h-100" alt="{{ $product['name'] }}" style="object-fit: cover; object-position: center;">
                    @else
                        <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                            <i class="bi bi-box text-muted" style="font-size: 3rem;"></i>
                        </div>
                    @endif
                </div>
                
                {{-- Product Info --}}
                <div class="card-body pb-2 bg-white">
                    <p class="mb-1" style="font-size: 9px; color: #94a3b8; font-family: monospace;">{{ $product['code'] }}</p>
                    <h6 class="card-title fw-bold mb-2" style="font-size: 11px; line-height: 1.4; color: #1e293b;">{{ $product['name'] }}</h6>
                    
                    <div class="mb-2">
                        <span class="badge bg-info" style="font-size: 9px;">{{ $product['category'] }}</span>
                    </div>

                    <div class="border-top pt-2">
                        <div class="d-flex justify-content-between mb-1" style="font-size: 12px;">
                            <span class="text-muted">Retail:</span>
                            <span class="fw-bold text-success">Rs. {{ number_format($product['retail_price'], 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between" style="font-size: 12px;">
                            <span class="text-muted">Wholesale:</span>
                            <span class="fw-bold text-primary">Rs. {{ number_format($product['wholesale_price'], 2) }}</span>
                        </div>
                    </div>
                    
                    <div class="border-top pt-2 mt-2">
                        <span style="font-size: 12px; color: #64748b; font-weight: bold;" class="d-block">
                            <span class="{{ $product['stock'] <= 10 ? 'text-warning' : 'text-success' }}">Avail: {{ $product['stock'] }}</span>
                            @if($product['pending'] > 0)
                                | <span class="text-primary">Pend: {{ $product['pending'] }}</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                    </div>
                    <p class="text-muted mb-0 text-uppercase fw-bold" style="font-size: 12px; letter-spacing: 2px;">No products found</p>
                </div>
            </div>
        </div>
        @endforelse
    </div>

    <style>
        /* 5 columns per row for large screens */
        @media (min-width: 1200px) {
            .col-xl-2-4 {
                flex: 0 0 auto;
                width: 20%;
            }
        }

        .product-card {
            transition: all 0.3s ease;
        }

        .product-card:hover {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15) !important;
            transform: translateY(-4px);
        }
    </style>
</div>
