<div class="container-fluid py-4">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold">Stock Re-entry for {{ $staff->name }}</h4>
        </div>
        <a href="{{ route('admin.staff-allocated-list') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    {{-- Search Bar --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="input-group">
                <span class="input-group-text bg-white">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" class="form-control" wire:model.live.debounce.300ms="search" 
                    placeholder="Search watches...">
            </div>
        </div>
    </div>

    {{-- Products Grid --}}
    <div class="row g-3">
        <div class="{{ $showReentryModal ? 'col-md-8' : 'col-12' }}">
            <div class="row g-3" style="display: grid; grid-template-columns: repeat({{ $showReentryModal ? '4' : '5' }}, 1fr);">
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
                                    @else
                                        <span class="badge bg-success small">Available</span>
                                    @endif
                                </div>

                                {{-- Quantity Display --}}
                                <div class="d-flex justify-content-between align-items-center mb-2" style="font-size: 0.85rem;">
                                    <span class="fw-bold">{{ $item->available_quantity }}/{{ $item->quantity }}</span>
                                </div>

                                {{-- Progress Bar --}}
                                <div class="progress mb-3" style="height: 6px;">
                                    @php
                                        $percentage = $item->quantity > 0 ? ($item->sold_quantity / $item->quantity) * 100 : 0;
                                    @endphp
                                    <div class="progress-bar bg-primary" style="width: {{ $percentage }}%"></div>
                                </div>

                                {{-- Re-entry Button --}}
                                <button class="btn btn-outline-primary btn-sm w-100" 
                                    wire:click="openReentryModal({{ $item->id }})"
                                    @if($item->available_quantity == 0) disabled @endif>
                                    Re-entry
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                                <h5 class="text-muted">No products available for re-entry</h5>
                                <p class="text-muted mb-0">All allocated products have been sold</p>
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Re-entry Side Panel --}}
        @if($showReentryModal && $selectedProduct)
        <div class="col-md-4">
            <div class="card border-0 shadow-lg sticky-top" style="top: 20px;">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Edit Stock</h5>
                        <button type="button" class="btn-close" wire:click="closeReentryModal"></button>
                    </div>
                </div>
                <div class="card-body">
                    {{-- Product Info --}}
                    <div class="mb-4">
                        <h6 class="fw-bold">{{ $selectedProduct->name }}</h6>
                        <p class="text-muted small">Code: {{ $selectedProduct->code }}</p>
                    </div>

                    {{-- Available Quantity Display --}}
                    <div class="alert alert-info mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Available Quantity:</span>
                            <strong class="fs-5">{{ $availableQuantity }}</strong>
                        </div>
                    </div>

                    {{-- Damaged Quantity --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Damaged Quantity</label>
                        <input type="number" class="form-control form-control-lg" 
                            wire:model.live="damagedQuantity" 
                            min="0" 
                            max="{{ $availableQuantity }}"
                            placeholder="0">
                        @error('damagedQuantity')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Restock Quantity --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Restock Quantity</label>
                        <input type="number" class="form-control form-control-lg" 
                            wire:model.live="restockQuantity" 
                            min="0" 
                            max="{{ $availableQuantity }}"
                            placeholder="0">
                        @error('restockQuantity')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>


                    {{-- Summary --}}
                    <div class="alert alert-light border mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Damaged:</span>
                            <strong class="text-danger">{{ $damagedQuantity }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Restock:</span>
                            <strong class="text-success">{{ $restockQuantity }}</strong>
                        </div>
                        <div class="d-flex justify-content-between pt-2 border-top">
                            <span class="fw-bold">Total Return:</span>
                            <strong class="fs-5">{{ $damagedQuantity + $restockQuantity }}</strong>
                        </div>
                    </div>

                    {{-- Submit Button --}}
                    <button class="btn btn-success btn-lg w-100" 
                        wire:click="submitReentry"
                        @if(($damagedQuantity + $restockQuantity) == 0 || ($damagedQuantity + $restockQuantity) > $availableQuantity) disabled @endif>
                        <i class="bi bi-check-circle me-2"></i> Submit
                    </button>
                </div>
            </div>
        </div>
        @endif
    </div>

    <style>
        .sticky-top {
            position: sticky;
        }
    </style>
</div>

@push('scripts')
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('success', (event) => {
            const message = Array.isArray(event) ? event[0] : event;
            Swal.fire('Success', message, 'success');
        });

        Livewire.on('error', (event) => {
            const message = Array.isArray(event) ? event[0] : event;
            Swal.fire('Error', message, 'error');
        });
    });
</script>
@endpush
