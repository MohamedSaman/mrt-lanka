<div class="container-fluid py-4">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('admin.staff-allocated-list') }}">Staff Allocations</a></li>
                    <li class="breadcrumb-item active">{{ $staff->name }}</li>
                </ol>
            </nav>
            <h4 class="mb-0 fw-bold">Allocated Products - {{ $staff->name }}</h4>
        </div>
        <a href="{{ route('admin.staff-allocated-list') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="search" 
                            placeholder="Search by product name or code...">
                    </div>
                </div>
                <div class="col-md-4">
                    <select class="form-select" wire:model.live="statusFilter">
                        <option value="all">All Status</option>
                        <option value="assigned">Assigned</option>
                        <option value="sold">Sold Out</option>
                        <option value="returned">Returned</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Products Grid --}}
    <div class="row g-3" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
        @forelse($allocatedProducts as $item)
            <div wire:key="product-{{ $item->id }}">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3">
                        {{-- Product Image --}}
                        <div class="bg-light rounded mb-2 d-flex align-items-center justify-content-center overflow-hidden" 
                            style="height: 120px;">
                            @if($item->product->image)
                                <img src="{{ $item->product->image }}" alt="{{ $item->product->name }}" 
                                    style="width: 100%; height: 100%; object-fit: cover;">
                            @else
                                <i class="bi bi-tools fs-3 text-muted"></i>
                            @endif
                        </div>

                        {{-- Product Info --}}
                        <p class="text-muted mb-1 text-truncate small fw-semibold" title="{{ $item->product->name }}">{{ $item->product->name }}</p>
                        <p class="text-muted small mb-2">Code: {{ $item->product->code }}</p>

                        {{-- Status Badge --}}
                        <div class="mb-2">
                            @if($item->available_quantity == 0)
                                <span class="badge bg-danger small">Sold Out</span>
                            @elseif($item->status == 'returned')
                                <span class="badge bg-secondary small">Returned</span>
                            @else
                                <span class="badge bg-success small">Available</span>
                            @endif
                        </div>

                        {{-- Quantity Info --}}
                        <div class="d-flex justify-content-between align-items-center mb-2" style="font-size: 0.85rem;">
                            <div>
                                <small class="text-muted d-block">Allocated</small>
                                <strong>{{ $item->quantity }}</strong>
                            </div>
                            <div>
                                <small class="text-muted d-block">Sold</small>
                                <strong class="text-success">{{ $item->sold_quantity }}</strong>
                            </div>
                            <div>
                                <small class="text-muted d-block">Available</small>
                                <strong class="text-info">{{ $item->available_quantity }}</strong>
                            </div>
                        </div>

                        {{-- Progress Bar --}}
                        <div class="progress mb-2" style="height: 6px;">
                            @php
                                $soldPercentage = $item->quantity > 0 ? ($item->sold_quantity / $item->quantity) * 100 : 0;
                            @endphp
                            <div class="progress-bar bg-success" style="width: {{ $soldPercentage }}%"></div>
                        </div>

                        {{-- Price Info --}}
                        <div class="mb-2" style="font-size: 0.85rem;">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Unit Price</small>
                                <div class="fw-bold">Rs.{{ number_format($item->unit_price, 2) }}</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Total Value</small>
                                <div class="fw-bold">Rs.{{ number_format($item->total_value, 2) }}</div>
                            </div>
                        </div>

                        {{-- Allocated Date --}}
                        <small class="text-muted">
                            <i class="bi bi-calendar me-1"></i>
                            {{ $item->created_at->format('d M Y') }}
                        </small>
                    </div>
                </div>
            </div>
        @empty
            <div style="grid-column: 1 / -1;">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                        <h5 class="text-muted">No products found</h5>
                        <p class="text-muted mb-0">No products have been allocated to this staff member yet</p>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('success', (event) => {
            Swal.fire('Success', event[0] || event, 'success');
        });

        Livewire.on('error', (event) => {
            Swal.fire('Error', event[0] || event, 'error');
        });
    });
</script>
@endpush
