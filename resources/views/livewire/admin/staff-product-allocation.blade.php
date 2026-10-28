<div class="container-fluid py-3">
    {{-- Top Header with Staff Selection --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-bold text-dark">Billing System</h5>
        <div style="width: 280px;">
            <select class="form-select form-select-sm" wire:model.live="staffId">
                <option value="">-- Choose Staff --</option>
                @foreach($staff as $member)
                    <option value="{{ $member->id }}">
                        {{ $member->name }}
                        @if($member->email)
                        - {{ $member->email }}
                        @endif
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Search Bar with Shadow --}}
    <div class="mb-4 position-relative card border-0 shadow-lg p-4">
        <div class="input-group input-group-lg">
            <span class="input-group-text bg-white search-icon">
                <i class="bi bi-search text-muted" style="font-size: 20px;"></i>
            </span>
            <input type="text" class="form-control search-input" style="font-size: 16px; padding: 12px 15px;"
                wire:model.live="search"
                wire:input="searchProducts"
                placeholder="Search by code, model, barcode, brand or name..."
                @if(!$staffId) disabled @endif>
        </div>

        {{-- Search Results Dropdown --}}
        @if($search && count($searchResults) > 0)
        <div class="search-results mt-2 position-absolute w-100 shadow-lg" style="max-height: 300px; z-index: 1055; top: 100%; left: 0;">
            @foreach($searchResults as $product)
                @php
                    $stock = App\Models\ProductStock::where('product_id', $product->id)->sum('available_stock');
                @endphp
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white"
                    wire:key="product-{{ $product->id }}">
                    <div>
                        <h6 class="mb-1 fw-semibold">{{ $product->name }}</h6>
                        <p class="text-muted small mb-0">
                            Code: {{ $product->code }}
                        </p>
                        <p class="text-success small mb-0">
                            Rs.{{ number_format($product->price?->selling_price ?? 0, 2) }} | Stock: {{ $stock }}
                        </p>
                    </div>
                    <button class="btn btn-sm btn-outline-primary"
                        wire:click="addToCart({{ $product->id }})"
                        {{ $stock <= 0 ? 'disabled' : '' }}>
                        <i class="bi bi-plus-lg"></i> Add
                    </button>
                </div>
            @endforeach
        </div>
        @elseif($search)
        <div class="text-center text-muted p-4 bg-white border rounded-bottom shadow-lg" style="margin-top: 5px;">
            <i class="bi bi-exclamation-circle me-1"></i> No products found
        </div>
        @endif
    </div>

    {{-- Allocation Items Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if(count($cart) > 0)
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr class="border-bottom">
                            <th class="fw-bold text-uppercase text-muted" style="font-size: 12px;">Product</th>
                            <th class="fw-bold text-uppercase text-muted" style="font-size: 12px;">Unit Price</th>
                            <th class="fw-bold text-uppercase text-muted" style="font-size: 12px;">Quantity</th>
                            <th class="fw-bold text-uppercase text-muted" style="font-size: 12px;">Discount (Per Unit)</th>
                            <th class="fw-bold text-uppercase text-muted" style="font-size: 12px;">Total</th>
                            <th class="fw-bold text-uppercase text-muted text-center" style="font-size: 12px; ">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cart as $index => $item)
                        <tr wire:key="cart_{{ $index }}" class="border-bottom">
                            <td class="py-3">
                                <div>
                                    <strong>{{ $item['product_name'] }}</strong><br>
                                    <small class="text-muted">{{ $item['product_code'] }}</small>
                                </div>
                            </td>
                            <td class="py-3">
                                <div class="input-group input-group-sm" style="width: 140px;">
                                    <span class="input-group-text bg-light">Rs.</span>
                                    <input type="number" class="form-control" step="0.01" min="0"
                                        wire:change="updatePrice('{{ $index }}', $event.target.value)"
                                        value="{{ number_format($item['unit_price'], 2, '.', '') }}">
                                </div>
                            </td>
                            <td class="py-3">
                                <div class="input-group input-group-sm" style="width: 140px;">
                                    <input type="number" class="form-control" min="1" max="{{ $item['available_stock'] }}"
                                        wire:change="updateQuantity('{{ $index }}', $event.target.value)"
                                        value="{{ $item['quantity'] }}">
                                    <span class="input-group-text bg-light" style="font-size: 11px;">/ {{ $item['available_stock'] }}</span>
                                </div>
                            </td>
                            <td class="py-3">
                                <input type="number" class="form-control form-control-sm" style="width: 120px;"
                                    wire:change="updateDiscount('{{ $index }}', $event.target.value)"
                                    value="{{ $item['discount'] }}" min="0" step="0.01">
                            </td>
                            <td class="py-3 fw-bold">Rs.{{ number_format($item['total'], 2) }}</td>
                            <td class="py-3 text-center">
                                <button class="btn btn-sm btn-outline-danger"
                                    wire:click="removeFromCart('{{ $index }}')"
                                    title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Cart-level additional discount (labelled) placed at right corner --}}
            <div class="p-3 border-top d-flex">
                <div class="ms-auto" style="min-width:360px;">
                    <label class="form-label fw-semibold mb-1  d-block">Additional Discount</label>
                    <div class="input-group input-group-sm mb-1">
                        <input type="number" class="form-control" min="0" step="0.01"
                            wire:model.live="additionalDiscount"
                            placeholder="Enter discount">
                        <select class="form-select" wire:model.live="additionalDiscountType" style="max-width: 90px;">
                            <option value="fixed">Rs.</option>
                            <option value="percentage">%</option>
                        </select>
                    </div>
                    <div class="text-danger fw-bold text-end">
                        @if($additionalDiscount > 0)
                            - Rs.{{ number_format($additionalDiscountType === 'percentage' ? ($this->subtotal * $additionalDiscount) / 100 : $additionalDiscount, 2) }}
                        @endif
                    </div>
                </div>
            </div>

            @else
            <div class="text-center text-muted py-5">
                <i class="bi bi-cart display-4 d-block mb-2"></i>
                Your cart is empty. Search and add products to create a bill.
            </div>
            @endif
        </div>
    </div>


    {{-- Bottom Summary and Actions --}}
    @if(count($cart) > 0)
    <div class="mt-3">
        <div class="row">
            {{-- Notes --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <label class="form-label fw-semibold mb-2">Notes</label>
                        <textarea class="form-control" wire:model="notes" rows="4"placeholder="Add any notes for this allocation...">
                        </textarea>
                    </div>
                </div>
            </div>

            {{-- Summary and Buttons --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Subtotal:</span>
                                <strong>Rs.{{ number_format($this->subtotal, 2) }}</strong>
                            </div>

                            {{-- Additional Discount --}}
                            @if($additionalDiscount > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Discount ({{ $additionalDiscountType === 'percentage' ? $additionalDiscount . '%' : 'Rs.' }}):</span>
                                <strong class="text-danger">
                                    - Rs.{{ number_format($additionalDiscountType === 'percentage' 
                                        ? ($this->subtotal * $additionalDiscount) / 100 
                                        : $additionalDiscount, 2) }}
                                </strong>
                            </div>
                            @endif

                            {{-- Discount input moved to cart area; removed duplicate here --}}

                            <div class="border-top pt-2">
                                <div class="d-flex justify-content-between">
                                    <strong class="fs-5">Grand Total:</strong>
                                    <strong class="fs-5 text-primary">Rs.{{ number_format($this->grandTotal, 2) }}</strong>
                                </div>
                            </div>
                        </div>

                        <button class="btn btn-success w-100 btn-lg" wire:click="allocateProducts"
                            {{ count($cart) == 0 || !$staffId ? 'disabled' : '' }}>
                            <i class="bi bi-check-circle me-2"></i>Allocate Products
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    </div>
</div>

@push('styles')
<style>
    /* Search input and icon styling */
    .search-input {
        border: 1px solid #e6eef6;
        box-shadow: 0 6px 18px rgba(11, 22, 39, 0.06);
        border-radius: 0.6rem;
        outline: none;
    }
    .search-input:focus {
        box-shadow: 0 8px 22px rgba(11, 22, 39, 0.09);
        border-color: #bcd3ee;
    }
    .search-icon {
        border: 1px solid #e6eef6;
        border-right: 0;
        background: #ffffff;
        box-shadow: 0 6px 18px rgba(11, 22, 39, 0.06);
        border-top-left-radius: 0.6rem;
        border-bottom-left-radius: 0.6rem;
    }
    .search-icon .bi-search {
        color: #6b7280;
    }

    .container-fluid,
    .card,
    .modal-content {
        font-size: 13px !important;
    }

    .table th,
    .table td {
        font-size: 12px !important;
        padding: 0.35rem 0.5rem !important;
    }

    .modal-header {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
        margin-bottom: 0.25rem !important;
    }

    .modal-footer,
    .card-header,
    .card-body,
    .row,
    .col-md-6,
    .col-md-4,
    .col-md-2,
    .col-md-12 {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
        margin-top: 0.25rem !important;
        margin-bottom: 0.25rem !important;
    }

    .form-control,
    .form-select {
        font-size: 12px !important;
        padding: 0.35rem 0.5rem !important;
    }

    .btn,
    .btn-sm,
    .btn-primary,
    .btn-secondary,
    .btn-outline-danger,
    .btn-outline-secondary {
        font-size: 12px !important;
        padding: 0.25rem 0.5rem !important;
    }

    .badge {
        font-size: 11px !important;
        padding: 0.25em 0.5em !important;
    }

    .list-group-item,
    .dropdown-item {
        font-size: 12px !important;
        padding: 0.35rem 0.5rem !important;
    }

    .summary-card,
    .card {
        border-radius: 8px !important;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06) !important;
    }

    .icon-container {
        width: 36px !important;
        height: 36px !important;
        font-size: 1.1rem !important;
    }

    .search-results {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
    }

    .search-results .p-3 {
        transition: background-color 0.2s ease;
    }

    .search-results .p-3:hover {
        background-color: #f8f9fa;
    }

    .table th {
        font-size: 0.875rem;
        font-weight: 600;
        background-color: #f8f9fa;
    }

    .form-control-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    .card {
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        padding: 1rem 1.25rem;
    }

    /* Flexbox layout for header cards */
    .row.g-0 {
        display: flex !important;
        gap: 0 !important;
    }

    .row.g-0 > .col-md-6 {
        flex: 1;
        min-width: 0;
    }

    .input-group-sm>.btn {
        padding: 0.25rem 0.5rem;
    }

    /* Discount input styling */
    .text-danger.form-control:focus {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .table-responsive {
            font-size: 0.875rem;
        }

        .input-group-sm {
            flex-wrap: nowrap;
        }

        .card-header {
            padding: 0.75rem 1rem;
        }

        .modal-dialog {
            margin: 0.5rem;
        }
    }

    /* Stock warning */
    .text-info small {
        font-size: 0.75rem;
    }
</style>
@endpush

@push('scripts')
<script>
    // Auto-close alerts after 5 seconds
    document.addEventListener('livewire:initialized', () => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });

    // Prevent form submission on enter key in search
    document.addEventListener('keydown', function(e) {
        if (e.target.type === 'text' && e.target.getAttribute('wire:model') === 'search') {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        }
    });
</script>
@endpush
