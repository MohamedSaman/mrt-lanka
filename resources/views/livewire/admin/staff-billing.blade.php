<div>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="fw-bold text-dark mb-1">
                                    <i class="bi bi-receipt-cutoff text-primary me-2"></i>Staff Billing
                                </h3>
                                <p class="text-muted small mb-0">Create and manage sales from your allocated products</p>
                            </div>
                            <div class="text-end">
                                <div class="text-muted small">Staff Member</div>
                                <h5 class="fw-bold text-dark">{{ Auth::user()->name }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Side: Product Search & Cart -->
            <div class="col-lg-8">
                <!-- Customer Selection -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-light border-bottom">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="bi bi-person-fill text-primary me-2"></i>Customer
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-9">
                                <select class="form-select" wire:model.live="customerId">
                                    <option value="">Select Customer</option>
                                    @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-primary w-100" wire:click="openCustomerModal">
                                    <i class="bi bi-plus-lg"></i> New Customer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Search -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-light border-bottom">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="bi bi-search text-primary me-2"></i>Search Products
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="input-group mb-3">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" placeholder="Search by name, code, or model..." 
                                wire:model.live="search">
                        </div>

                        @if($searchResults->count() > 0)
                        <div class="list-group" style="max-height: 300px; overflow-y: auto;">
                            @foreach($searchResults as $product)
                            <button type="button" class="list-group-item list-group-item-action border-0 p-3"
                                wire:click="addToCart({{ json_encode($product) }})">
                                <div class="d-flex w-100 justify-content-between align-items-start">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-1">{{ $product['name'] }}</h6>
                                        <small class="text-muted d-block">Code: {{ $product['code'] }}</small>
                                        <small class="text-muted d-block">Model: {{ $product['model'] }}</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-primary">Rs. {{ number_format($product['price'], 2) }}</span>
                                        <span class="badge bg-{{ $product['stock'] > 0 ? 'success' : 'danger' }}">
                                            Stock: {{ $product['stock'] }}
                                        </span>
                                    </div>
                                </div>
                            </button>
                            @endforeach
                        </div>
                        @elseif($search)
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-2"></i>No products found matching your search
                        </div>
                        @else
                        <div class="alert alert-secondary mb-0">
                            <i class="bi bi-search me-2"></i>Start typing to search your allocated products
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Cart Items -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="bi bi-cart-fill text-primary me-2"></i>Cart Items ({{ count($cart) }})
                            </h6>
                            @if(count($cart) > 0)
                            <button class="btn btn-sm btn-outline-danger" wire:click="clearCart">
                                <i class="bi bi-trash"></i> Clear All
                            </button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        @if(count($cart) > 0)
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Qty</th>
                                        <th>Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($cart as $index => $item)
                                    <tr>
                                        <td>
                                            <small class="fw-bold text-dark">{{ $item['name'] }}</small>
                                            <div><small class="text-muted">{{ $item['code'] }}</small></div>
                                        </td>
                                        <td>
                                            <small class="fw-bold">Rs. {{ number_format($item['price'], 2) }}</small>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm" style="width: 100px;">
                                                <button class="btn btn-outline-secondary btn-sm" 
                                                    wire:click="decrementQuantity({{ $index }})" type="button">-</button>
                                                <input type="number" class="form-control form-control-sm text-center" 
                                                    wire:model.lazy="cart.{{ $index }}.quantity" min="1" max="{{ $item['stock'] }}">
                                                <button class="btn btn-outline-secondary btn-sm" 
                                                    wire:click="incrementQuantity({{ $index }})" type="button">+</button>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="fw-bold text-success">Rs. {{ number_format($item['total'], 2) }}</small>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                wire:click="removeFromCart({{ $index }})">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="text-center py-5">
                            <i class="bi bi-cart-x" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="text-muted mt-3">No items in cart. Add products to get started.</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Side: Summary & Checkout -->
            <div class="col-lg-4">
                <!-- Order Summary -->
                <div class="card border-0 shadow-sm sticky-top" style="top: 20px;">
                    <div class="card-header bg-primary text-white border-0">
                        <h6 class="fw-bold mb-0">
                            <i class="bi bi-calculator-fill me-2"></i>Order Summary
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Subtotal:</span>
                                <span class="fw-bold">Rs. {{ number_format($subtotal, 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Item Discount:</span>
                                <span class="fw-bold text-danger">-Rs. {{ number_format($totalDiscount, 2) }}</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="fw-bold">After Item Discount:</span>
                                <span class="fw-bold">Rs. {{ number_format($subtotalAfterItemDiscounts, 2) }}</span>
                            </div>

                            @if($additionalDiscountAmount > 0)
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Additional Discount:</span>
                                <span class="fw-bold text-danger">-Rs. {{ number_format($additionalDiscountAmount, 2) }}</span>
                            </div>
                            <hr>
                            @endif
                        </div>

                        <!-- Grand Total -->
                        <div class="alert alert-success border-0 mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Grand Total:</span>
                                <span class="fw-bold" style="font-size: 1.3rem;">Rs. {{ number_format($grandTotal, 2) }}</span>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Notes</label>
                            <textarea class="form-control form-control-sm" wire:model="notes" rows="3" placeholder="Add any notes..."></textarea>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary fw-bold py-2" wire:click="createSale" 
                                {{ count($cart) == 0 ? 'disabled' : '' }}>
                                <i class="bi bi-check-circle me-2"></i>Create Sale
                            </button>
                            <button class="btn btn-outline-secondary fw-bold py-2" wire:click="clearCart"
                                {{ count($cart) == 0 ? 'disabled' : '' }}>
                                <i class="bi bi-x-circle me-2"></i>Clear Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Customer Modal -->
    @if($showCustomerModal)
    <div class="modal d-block" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 bg-primary text-white">
                    <h5 class="modal-title fw-bold">Add New Customer</h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeCustomerModal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Customer Name *</label>
                        <input type="text" class="form-control" wire:model="customerName">
                        @error('customerName') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="text" class="form-control" wire:model="customerPhone">
                        @error('customerPhone') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" class="form-control" wire:model="customerEmail">
                        @error('customerEmail') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Address *</label>
                        <textarea class="form-control" wire:model="customerAddress" rows="2"></textarea>
                        @error('customerAddress') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Customer Type *</label>
                        <select class="form-select" wire:model="customerType">
                            <option value="retail">Retail</option>
                            <option value="wholesale">Wholesale</option>
                        </select>
                        @error('customerType') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary" wire:click="closeCustomerModal">Cancel</button>
                    <button type="button" class="btn btn-primary fw-bold" wire:click="createCustomer">Create Customer</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Sale Success Modal -->
    @if($showSaleModal && $createdSale)
    <div class="modal d-block" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 bg-success text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-check-circle me-2"></i>Sale Created Successfully
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <div class="mb-3">
                        <i class="bi bi-check-circle" style="font-size: 3rem; color: #28a745;"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-2">Invoice #{{ $createdSale->invoice_number }}</h6>
                    <p class="text-muted small mb-3">Customer: {{ $createdSale->customer?->name ?? 'Walk-in' }}</p>
                    <div class="alert alert-light border mb-3">
                        <strong>Total Amount:</strong> Rs. {{ number_format($createdSale->total_amount, 2) }}
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-primary fw-bold" wire:click="downloadInvoice">
                        <i class="bi bi-download me-2"></i>Download Invoice
                    </button>
                    <button type="button" class="btn btn-success fw-bold" wire:click="createNewSale">
                        <i class="bi bi-plus-circle me-2"></i>New Sale
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
