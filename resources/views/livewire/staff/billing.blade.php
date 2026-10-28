<div class="container-fluid py-3" style="background-color:#ffffff;">
    <!-- Header -->
    <div class="header-section mb-4">
        <div class="d-flex justify-content-between align-items-center p-3 bg-white rounded shadow-sm border">
            <div class="d-flex align-items-center">
                <div class="company-logo me-3">
                    <i class="bi bi-shop fs-3 text-success"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold" style="color:#2a83df;">Staff POS Billing</h4>
                    <small class="text-muted">Sales System - Allocated Products Only</small>
                </div>
            </div>
            <div class="d-flex align-items-center">
                <span class="badge bg-info me-2">{{ Auth::user()->name }}</span>
            </div>
        </div>
    </div>

    @if(session()->has('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>{{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row">
        {{-- Customer Information --}}
        <div class="col-6 mb-4">
            <div class="card border-2 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="card-title mb-0 fw-bold" style="color:#2a83df;">
                        <i class="bi bi-person me-2" style="color:#2a83df;"></i>Customer Information
                    </h5>
                    <button class="btn btn-sm rounded-1 text-white" style="background: linear-gradient(135deg, #2a83df 0%, #1a5fb8 100%);" wire:click="openCustomerModal">
                        <i class="bi bi-plus-circle me-1"></i> Add New Customer
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold" style="color:#2a83df;">Select Customer *</label>
                            <select class="form-select rounded-0 border" wire:model.live="customerId">
                                @foreach($customers as $customer)
                                <option value="">-- Select a customer --</option>
                                <option value="{{ $customer->id }}" {{ $customer->name === 'Walking Customer' ? 'selected' : '' }}>
                                    {{ $customer->name }}
                                    @if($customer->phone) - {{ $customer->phone }} @endif
                                    @if($customer->name === 'Walking Customer') (Default) @endif
                                </option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                @if($selectedCustomer && $selectedCustomer->name === 'Walking Customer')
                                <span class="text-info"><i class="bi bi-info-circle"></i> Using default walking customer</span>
                                @else
                                Select existing customer or add new (only your customers are shown)
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Add Products Card --}}
        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-1">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-semibold">
                        <i class="bi bi-search me-2 text-success"></i> Add Products (Allocated Only)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <input type="text" class="form-control shadow-sm"
                            wire:model.live="search"
                            placeholder="Search by product name, code, or model...">
                    </div>

                    {{-- Search Results --}}
                    @if($search && count($searchResults) > 0)
                    <div class="search-results mt-1 position-absolute w-100 z-10 shadow-lg" style="max-height: 300px; max-width: 96%;">
                        @foreach($searchResults as $product)
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white rounded-1"
                            wire:key="product-{{ $product['id'] }}">
                            <div>
                                <h6 class="mb-1 fw-semibold">{{ $product['name'] }}</h6>
                                <p class="text-muted small mb-0">
                                    Code: {{ $product['code'] }} | Model: {{ $product['model'] }}
                                </p>
                                <p class="text-success small mb-0">
                                    Rs.{{ number_format($product['price'], 2) }} | Stock: {{ $product['stock'] }}
                                </p>
                            </div>
                            <button class="btn btn-sm btn-outline-primary"
                                wire:click="addToCart({{ json_encode($product) }})"
                                {{ $product['stock'] <= 0 ? 'disabled' : '' }}>
                                <i class="bi bi-plus-lg"></i> Add
                            </button>
                        </div>
                        @endforeach
                    </div>
                    @elseif($search)
                    <div class="text-center text-muted p-3">
                        <i class="bi bi-exclamation-circle me-1"></i> No allocated products found
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Sale Items Table --}}
    <div class="col-md-12 mb-4">
        <div class="card border-2 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="card-title mb-0 fw-bold" style="color:#2a83df;">
                    <i class="bi bi-cart me-2" style="color:#2a83df;"></i>Sale Items
                </h5>
                <span class="badge rounded-1 text-white" style="background: linear-gradient(135deg, #2a83df 0%, #1a5fb8 100%);">{{ count($cart) }} items</span>
            </div>
            <div class="card-body p-0">
                @if(count($cart) > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="30">#</th>
                                <th>Product</th>
                                <th width="120">Unit Price</th>
                                <th width="150">Quantity</th>
                                <th width="120">Discount/Unit</th>
                                <th width="120">Total</th>
                                <th width="100" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cart as $index => $item)
                            <tr wire:key="{{ $item['key'] ?? 'cart_' . $index }}">
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <div>
                                        <strong>{{ $item['name'] }}</strong>
                                        <div class="text-muted small">{{ $item['code'] }} | {{ $item['model'] }}</div>
                                        <div class="text-info small">Stock: {{ $item['stock'] }}</div>
                                    </div>
                                </td>
                                <td class="fw-bold">
                                    <input type="number" class="form-control-sm text-primary rounded-0" style="min-width:90px;"
                                        wire:change="updatePrice({{ $index }}, $event.target.value)"
                                        value="{{ $item['price'] }}" min="0" step="0.01">
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <button class="btn btn-outline-secondary rounded-0" type="button"
                                            wire:click="decrementQuantity({{ $index }})">-</button>
                                        <input type="number" class="form-control text-center rounded-0"
                                            wire:change="updateQuantity({{ $index }}, $event.target.value)"
                                            value="{{ $item['quantity'] }}" min="1" max="{{ $item['stock'] }}">
                                        <button class="btn btn-outline-secondary rounded-0" type="button"
                                            wire:click="incrementQuantity({{ $index }})">+</button>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" class="form-control-sm text-danger rounded-0"
                                        wire:change="updateDiscount({{ $index }}, $event.target.value)"
                                        value="{{ $item['discount'] }}" min="0" step="0.01">
                                </td>
                                <td class="fw-bold">Rs.{{ number_format($item['total'], 2) }}</td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-danger rounded-0"
                                        wire:click="removeFromCart({{ $index }})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="5" class="text-end fw-bold">Subtotal:</td>
                                <td class="fw-bold">Rs.{{ number_format($subtotal, 2) }}</td>
                                <td></td>
                            </tr>

                            {{-- Additional Discount --}}
                            <tr>
                                <td colspan="3" class="text-end fw-bold align-middle">
                                    Additional Discount:
                                    @if($additionalDiscount > 0)
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1"
                                        wire:click="removeAdditionalDiscount" title="Remove discount">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                    @endif
                                </td>
                                <td colspan="2">
                                    <div class="input-group input-group-sm">
                                        <input type="number"
                                            class="form-control form-control-sm text-danger rounded-0"
                                            wire:model.live="additionalDiscount"
                                            min="0"
                                            step="{{ $additionalDiscountType === 'percentage' ? '1' : '0.01' }}"
                                            @if($additionalDiscountType === 'percentage') max="100" @endif>
                                        <span class="input-group-text rounded-0">
                                            {{ $additionalDiscountType === 'percentage' ? '%' : 'Rs.' }}
                                        </span>
                                        <button type="button" class="btn btn-outline-secondary rounded-0"
                                            wire:click="toggleDiscountType" title="Switch Discount Type">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="fw-bold text-danger">
                                    @if($additionalDiscount > 0)
                                    - Rs.{{ number_format($additionalDiscountAmount, 2) }}
                                    @if($additionalDiscountType === 'percentage')
                                    <div class="text-muted small">({{ $additionalDiscount }}%)</div>
                                    @endif
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td></td>
                            </tr>

                            {{-- Grand Total --}}
                            <tr>
                                <td colspan="5" class="text-end fw-bold fs-5">Grand Total:</td>
                                <td class="fw-bold fs-5" style="color:#2a83df;">Rs.{{ number_format($grandTotal, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @else
                <div class="text-center text-muted py-5">
                    <i class="bi bi-cart display-4 d-block mb-2 text-muted"></i>
                    No items added yet
                </div>
                @endif
            </div>
            @if(count($cart) > 0)
            <div class="card-footer bg-white">
                <button class="btn btn-danger rounded-0" wire:click="clearCart">
                    <i class="bi bi-trash me-2"></i>Clear All Items
                </button>
            </div>
            @endif
        </div>
    </div>

    <div class="row">
        {{-- Payment Information Card --}}
        <div class="col-md-6 mb-4">
            <div class="card h-100 border-2 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-bold" style="color:#2a83df;">
                        <i class="bi bi-credit-card me-2" style="color:#2a83df;"></i>Payment Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Note:</strong> All payments require admin approval before release.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold" style="color:#2a83df;">Payment Method *</label>
                            <select class="form-select rounded-0 border" wire:model.live="paymentMethod">
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="credit">Credit (Pay Later)</option>
                            </select>
                        </div>

                        {{-- Cash Payment Fields --}}
                        @if($paymentMethod === 'cash')
                        <div class="col-md-12">
                            <label class="form-label fw-semibold" style="color:#2a83df;">Cash Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text rounded-0">Rs.</span>
                                <input type="number" class="form-control rounded-0"
                                    wire:model.live="cashAmount"
                                    min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        @endif

                        {{-- Cheque Payment Fields --}}
                        @if($paymentMethod === 'cheque')
                        <div class="col-md-12">
                            <div class="card bg-light border-0">
                                <div class="card-header bg-white py-2">
                                    <h6 class="mb-0 fw-semibold" style="color:#2a83df;">Add Cheque Details</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Cheque Number *</label>
                                            <input type="text" class="form-control form-control-sm rounded-0"
                                                wire:model="tempChequeNumber" placeholder="Enter cheque number">
                                            @error('tempChequeNumber') <span class="text-danger small">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Bank Name *</label>
                                            <input type="text" class="form-control form-control-sm rounded-0"
                                                wire:model="tempBankName" placeholder="Enter bank name">
                                            @error('tempBankName') <span class="text-danger small">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Cheque Date *</label>
                                            <input type="date" class="form-control form-control-sm rounded-0"
                                                wire:model="tempChequeDate">
                                            @error('tempChequeDate') <span class="text-danger small">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Cheque Amount *</label>
                                            <input type="number" class="form-control form-control-sm rounded-0"
                                                wire:model="tempChequeAmount" min="0" step="0.01" placeholder="0.00">
                                            @error('tempChequeAmount') <span class="text-danger small">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="col-12">
                                            <button type="button" class="btn btn-sm w-100 rounded-0 text-white" 
                                                style="background: linear-gradient(135deg, #2a83df 0%, #1a5fb8 100%);"
                                                wire:click="addCheque">
                                                <i class="bi bi-plus-circle me-1"></i> Add Cheque
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Cheques List --}}
                            @if(count($cheques) > 0)
                            <div class="mt-3">
                                <h6 class="mb-2 fw-semibold">Added Cheques ({{ count($cheques) }})</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Cheque No</th>
                                                <th>Bank</th>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th width="50">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($cheques as $index => $cheque)
                                            <tr>
                                                <td>{{ $cheque['number'] }}</td>
                                                <td>{{ $cheque['bank_name'] }}</td>
                                                <td>{{ date('d/m/Y', strtotime($cheque['date'])) }}</td>
                                                <td class="fw-bold">Rs.{{ number_format($cheque['amount'], 2) }}</td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-0"
                                                        wire:click="removeCheque({{ $index }})">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <td colspan="3" class="text-end fw-bold">Total:</td>
                                                <td colspan="2" class="fw-bold text-success">
                                                    Rs.{{ number_format(collect($cheques)->sum('amount'), 2) }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            @endif
                        </div>
                        @endif

                        {{-- Bank Transfer Fields --}}
                        @if($paymentMethod === 'bank_transfer')
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="color:#2a83df;">Bank Transfer Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text rounded-0">Rs.</span>
                                <input type="number" class="form-control rounded-0"
                                    wire:model.live="bankTransferAmount" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="color:#2a83df;">Bank Name *</label>
                            <input type="text" class="form-control rounded-0"
                                wire:model="bankTransferBankName" placeholder="Enter bank name">
                            @error('bankTransferBankName') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold" style="color:#2a83df;">Reference Number (Optional)</label>
                            <input type="text" class="form-control rounded-0"
                                wire:model="bankTransferReferenceNumber" placeholder="Enter transaction reference">
                        </div>
                        @endif

                        {{-- Credit Payment Info --}}
                        @if($paymentMethod === 'credit')
                        <div class="col-md-12">
                            <div class="alert alert-warning mb-0 rounded-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Credit Sale</strong>
                                <p class="mb-0 mt-2">The full amount of Rs.{{ number_format($grandTotal, 2) }} will be marked as due.</p>
                            </div>
                        </div>
                        @endif

                        {{-- Payment Summary --}}
                        @if($paymentMethod !== 'credit')
                        <div class="col-md-12">
                            <div class="border rounded-0 p-3 bg-light">
                                <h6 class="mb-3 fw-semibold" style="color:#2a83df;">Payment Summary</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Grand Total:</span>
                                    <span class="fw-bold">Rs.{{ number_format($grandTotal, 2) }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Paid Amount:</span>
                                    <span class="fw-bold text-success">Rs.{{ number_format($totalPaidAmount, 2) }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Due Amount:</span>
                                    <span class="fw-bold {{ $dueAmount > 0 ? 'text-warning' : 'text-success' }}">
                                        Rs.{{ number_format($dueAmount, 2) }}
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Status:</span>
                                    <span class="badge bg-{{ $paymentStatus === 'paid' ? 'success' : ($paymentStatus === 'partial' ? 'warning' : 'danger') }} rounded-1">
                                        {{ ucfirst($paymentStatus) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        @if($totalPaidAmount < $grandTotal && $totalPaidAmount > 0)
                        <div class="col-md-12">
                            <div class="alert alert-info small mb-0 rounded-0">
                                <i class="bi bi-info-circle me-1"></i>
                                Partial payment. Remaining Rs.{{ number_format($dueAmount, 2) }} will be marked as due.
                            </div>
                        </div>
                        @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Notes Card --}}
        <div class="col-md-6 mb-4">
            <div class="card h-100 border-2 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-bold" style="color:#2a83df;">
                        <i class="bi bi-chat-text me-2" style="color:#2a83df;"></i>Notes
                    </h5>
                </div>
                <div class="card-body">
                    <textarea class="form-control rounded-0" wire:model="notes" rows="8"
                        placeholder="Add any notes for this sale..."></textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- Create Sale Button --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center bg-light py-4">
                <button class="btn btn-lg px-5 rounded-0 fw-bold text-white" 
                    style="background: linear-gradient(135deg, #2a83df 0%, #1a5fb8 100%);" 
                    wire:click="validateAndCreateSale"
                    {{ count($cart) == 0 ? 'disabled' : '' }}>
                    <i class="bi bi-cart-check me-2"></i>Complete Sale
                </button>
                <p class="text-muted small mt-2 mb-0">
                    <i class="bi bi-shield-check me-1"></i>Payment will be pending admin approval
                </p>
            </div>
        </div>
    </div>

    {{-- Add Customer Modal --}}
    @if($showCustomerModal)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content rounded-0">
                <div class="modal-header text-white rounded-0" style="background: linear-gradient(135deg, #2a83df 0%, #1a5fb8 100%);">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-person-plus me-2"></i>Add New Customer
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeCustomerModal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer Name *</label>
                            <input type="text" class="form-control rounded-0" wire:model="customerName"
                                placeholder="Enter customer name">
                            @error('customerName') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <input type="text" class="form-control rounded-0" wire:model="customerPhone"
                                placeholder="Enter phone number">
                            @error('customerPhone') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control rounded-0" wire:model="customerEmail"
                                placeholder="Enter email address">
                            @error('customerEmail') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer Type *</label>
                            <select class="form-select rounded-0" wire:model="customerType">
                                <option value="retail">Retail</option>
                                <option value="wholesale">Wholesale</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Business Name</label>
                            <input type="text" class="form-control rounded-0" wire:model="businessName"
                                placeholder="Enter business name (optional)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Address *</label>
                            <input type="text" class="form-control rounded-0" wire:model="customerAddress"
                                placeholder="Enter address">
                            @error('customerAddress') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-0">
                    <button type="button" class="btn btn-outline-secondary rounded-0" wire:click="closeCustomerModal">
                        <i class="bi bi-x me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn text-white rounded-0" 
                        style="background: linear-gradient(135deg, #2a83df 0%, #1a5fb8 100%);" 
                        wire:click="createCustomer">
                        <i class="bi bi-check-circle me-1"></i>Create Customer
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Payment Confirm Modal --}}
    @if($showPaymentConfirmModal)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-0">
                <div class="modal-header bg-warning text-dark rounded-0">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-exclamation-triangle me-2"></i>Confirm Due Amount
                    </h5>
                </div>
                <div class="modal-body p-4 text-center">
                    <div class="mb-4">
                        <i class="bi bi-cash-coin display-3 text-warning"></i>
                    </div>
                    <h5 class="mb-3">Payment amount is less than total</h5>
                    <p class="mb-4">
                        A due amount of <strong class="text-danger">Rs.{{ number_format($pendingDueAmount, 2) }}</strong> 
                        will be recorded for this sale.
                    </p>
                    <p class="text-muted small">Do you want to proceed with this sale?</p>
                </div>
                <div class="modal-footer justify-content-center rounded-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary rounded-0 px-4"
                        wire:click="cancelSaleConfirmation">
                        <i class="bi bi-x me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-warning rounded-0 px-4"
                        wire:click="confirmSaleWithDue">
                        <i class="bi bi-check me-1"></i>Proceed with Due
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Sale Complete Modal --}}
    @if($showSaleModal && $createdSale)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content rounded-0">
                <div class="modal-header text-white rounded-0" style="background: linear-gradient(135deg, #28a745 0%, #218838 100%);">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-check-circle me-2"></i>Sale Created Successfully!
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-check-circle-fill text-success display-1"></i>
                        <h4 class="mt-3">Sale #{{ $createdSale->invoice_number }}</h4>
                        <p class="text-muted">Payment is pending admin approval</p>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Sale Details</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td class="text-muted">Invoice Number:</td>
                                    <td class="fw-bold">{{ $createdSale->invoice_number }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Customer:</td>
                                    <td>{{ $createdSale->customer->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Total Amount:</td>
                                    <td class="fw-bold text-success">Rs.{{ number_format($createdSale->total_amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Due Amount:</td>
                                    <td class="fw-bold text-warning">Rs.{{ number_format($createdSale->due_amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Payment Status:</td>
                                    <td>
                                        <span class="badge bg-warning">Pending Approval</span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Items ({{ $createdSale->items->count() }})</h6>
                            <div class="list-group list-group-flush" style="max-height: 200px; overflow-y: auto;">
                                @foreach($createdSale->items as $item)
                                <div class="list-group-item px-0 py-2 border-0">
                                    <div class="d-flex justify-content-between">
                                        <span>{{ $item->product_name }}</span>
                                        <span class="fw-bold">x{{ $item->quantity }}</span>
                                    </div>
                                    <small class="text-muted">Rs.{{ number_format($item->total, 2) }}</small>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-0">
                    <button type="button" class="btn btn-outline-primary rounded-0" wire:click="downloadInvoice">
                        <i class="bi bi-download me-1"></i>Download Invoice
                    </button>
                    <button type="button" class="btn text-white rounded-0" 
                        style="background: linear-gradient(135deg, #2a83df 0%, #1a5fb8 100%);"
                        wire:click="createNewSale">
                        <i class="bi bi-plus-circle me-1"></i>New Sale
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
