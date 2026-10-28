<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1" style="color: #2a83df;">Add Payment</h2>
            <p class="text-muted">Submit payments for customers with due amounts</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('staff.payments-list') }}" class="btn btn-outline-primary rounded-2">
                <i class="bi bi-list-check"></i> View Payments
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-primary border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Customers with Dues</h6>
                            <h3 class="fw-bold" style="color: #2a83df;">{{ $stats['total_customers'] }}</h3>
                        </div>
                        <i class="bi bi-people fs-1 text-primary opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-danger border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Due Amount</h6>
                            <h3 class="fw-bold text-danger">Rs. {{ number_format($stats['total_due_amount'], 2) }}</h3>
                        </div>
                        <i class="bi bi-cash-stack fs-1 text-danger opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-warning border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Pending Approvals</h6>
                            <h3 class="fw-bold text-warning">{{ $stats['pending_approvals'] }}</h3>
                        </div>
                        <i class="bi bi-clock-history fs-1 text-warning opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Customers List -->
        <div class="col-md-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-3 fw-bold" style="color: #2a83df;">
                        <i class="bi bi-people me-2"></i>Customers with Dues
                    </h5>
                    <input type="text" 
                           class="form-control" 
                           wire:model.live="search" 
                           placeholder="Search by name, phone or email...">
                </div>
                <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                    @if ($customers->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach ($customers as $customer)
                                <button wire:click="selectCustomer({{ $customer->id }})" 
                                        class="list-group-item list-group-item-action {{ $selectedCustomerId == $customer->id ? 'active' : '' }}">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1 fw-bold">{{ $customer->name }}</h6>
                                            <small class="{{ $selectedCustomerId == $customer->id ? 'text-white' : 'text-muted' }}">
                                                <i class="bi bi-telephone"></i> {{ $customer->phone }}
                                            </small>
                                            @if ($customer->email)
                                                <br><small class="{{ $selectedCustomerId == $customer->id ? 'text-white' : 'text-muted' }}">
                                                    <i class="bi bi-envelope"></i> {{ $customer->email }}
                                                </small>
                                            @endif
                                        </div>
                                        <div class="text-end">
                                            <span class="badge {{ $selectedCustomerId == $customer->id ? 'bg-white text-primary' : 'bg-danger' }}">
                                                Rs. {{ number_format($customer->total_due, 2) }}
                                            </span>
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-4 text-muted mb-3"></i>
                            <p class="text-muted">No customers with due amounts found</p>
                            @if ($search)
                                <button wire:click="$set('search', '')" class="btn btn-sm btn-outline-primary">
                                    Clear Search
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Pending Sales -->
        <div class="col-md-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold" style="color: #2a83df;">
                        <i class="bi bi-receipt me-2"></i>Pending Sales
                    </h5>
                </div>
                <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                    @if ($selectedCustomerId)
                        @if (count($pendingSales) > 0)
                            <div class="row g-3">
                                @foreach ($pendingSales as $sale)
                                    <div class="col-12">
                                        <div class="card border shadow-sm">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <span class="badge bg-info">{{ $sale['invoice_number'] }}</span>
                                                        </h6>
                                                        <small class="text-muted">
                                                            <i class="bi bi-calendar"></i> 
                                                            {{ \Carbon\Carbon::parse($sale['created_at'])->format('d M, Y') }}
                                                        </small>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="mb-1">
                                                            <small class="text-muted">Total Amount:</small>
                                                            <strong class="d-block">Rs. {{ number_format($sale['total_amount'], 2) }}</strong>
                                                        </div>
                                                        <div>
                                                            <small class="text-muted">Due Amount:</small>
                                                            <strong class="d-block text-danger">Rs. {{ number_format($sale['due_amount'], 2) }}</strong>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mb-2">
                                                    <small class="text-muted fw-bold">Items ({{ count($sale['items']) }}):</small>
                                                    <div class="mt-1">
                                                        @foreach ($sale['items'] as $item)
                                                            <small class="d-block text-muted">
                                                                • {{ $item['product_name'] }} × {{ $item['quantity'] }}
                                                            </small>
                                                        @endforeach
                                                    </div>
                                                </div>

                                                <button wire:click="openPaymentModal({{ $sale['id'] }})" 
                                                        class="btn btn-primary btn-sm w-100">
                                                    <i class="bi bi-cash-coin"></i> Add Payment
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-5">
                                <i class="bi bi-check-circle display-4 text-success mb-3"></i>
                                <p class="text-muted">No pending sales for this customer</p>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-arrow-left-circle display-4 text-muted mb-3"></i>
                            <p class="text-muted">Select a customer to view pending sales</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    @if ($showPaymentModal && $selectedSale)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content rounded-0">
                <div class="modal-header text-white rounded-0" style="background: linear-gradient(135deg, #2a83df 0%, #1a5fb8 100%);">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-cash-coin me-2"></i>Add Payment - {{ $selectedSale->invoice_number }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closePaymentModal"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Sale Summary -->
                    <div class="card bg-light border-0 mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-muted mb-2">Customer</h6>
                                    <p class="mb-0">{{ $selectedSale->customer->name }}</p>
                                    <small class="text-muted">{{ $selectedSale->customer->phone }}</small>
                                </div>
                                <div class="col-md-6 text-end">
                                    <h6 class="fw-bold text-muted mb-2">Sale Amount</h6>
                                    <p class="mb-1"><strong>Total:</strong> Rs. {{ number_format($selectedSale->total_amount, 2) }}</p>
                                    <p class="mb-0 text-danger"><strong>Due:</strong> Rs. {{ number_format($selectedSale->due_amount, 2) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Form -->
                    <form wire:submit.prevent="submitPayment">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Payment Amount <span class="text-danger">*</span></label>
                                <input type="number" 
                                       class="form-control @error('paymentAmount') is-invalid @enderror" 
                                       wire:model="paymentAmount" 
                                       step="0.01" 
                                       min="0.01"
                                       placeholder="Enter amount">
                                @error('paymentAmount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                                <select class="form-select @error('paymentMethod') is-invalid @enderror" 
                                        wire:model="paymentMethod">
                                    <option value="">Select Method</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Card">Card</option>
                                    <option value="Mobile Payment">Mobile Payment</option>
                                </select>
                                @error('paymentMethod')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Payment Note</label>
                                <textarea class="form-control @error('paymentNote') is-invalid @enderror" 
                                          wire:model="paymentNote" 
                                          rows="3" 
                                          placeholder="Add any notes about this payment..."></textarea>
                                @error('paymentNote')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Payment Receipt/Proof (Optional)</label>
                                <input type="file" 
                                       class="form-control @error('paymentAttachment') is-invalid @enderror" 
                                       wire:model="paymentAttachment"
                                       accept=".jpg,.jpeg,.png,.pdf">
                                @error('paymentAttachment')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Supported: JPG, PNG, PDF (Max: 2MB)</small>

                                @if ($attachmentPreview)
                                    <div class="mt-2 p-2 border rounded">
                                        @if ($attachmentPreview['type'] === 'image')
                                            <img src="{{ $attachmentPreview['url'] }}" 
                                                 class="img-thumbnail" 
                                                 style="max-height: 150px;">
                                        @else
                                            <i class="bi bi-file-pdf text-danger fs-1"></i>
                                        @endif
                                        <p class="mb-0 mt-2"><small>{{ $attachmentPreview['name'] }}</small></p>
                                    </div>
                                @endif

                                @if ($paymentAttachment)
                                    <div wire:loading wire:target="paymentAttachment" class="mt-2">
                                        <span class="spinner-border spinner-border-sm"></span> Uploading...
                                    </div>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light rounded-0">
                    <button type="button" class="btn btn-secondary rounded-0" wire:click="closePaymentModal">
                        <i class="bi bi-x me-1"></i>Cancel
                    </button>
                    <button type="button" 
                            class="btn btn-primary rounded-0" 
                            wire:click="submitPayment"
                            wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submitPayment">
                            <i class="bi bi-check-circle me-1"></i>Submit Payment
                        </span>
                        <span wire:loading wire:target="submitPayment">
                            <span class="spinner-border spinner-border-sm me-1"></span>Submitting...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    window.addEventListener('showToast', event => {
        const data = event.detail[0];
        Swal.fire({
            icon: data.type,
            title: data.type === 'success' ? 'Success!' : 'Error!',
            text: data.message,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
        });
    });
</script>
@endpush
