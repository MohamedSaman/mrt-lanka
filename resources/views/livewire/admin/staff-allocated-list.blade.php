<div class="container-fluid py-4">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 fw-bold">Staff Allocated Products</h4>
    </div>

    {{-- Search and Filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="input-group">
                <span class="input-group-text bg-white">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" class="form-control" wire:model.live.debounce.300ms="search" 
                    placeholder="Search by staff name, email, or contact...">
            </div>
        </div>
    </div>

    {{-- Staff Table --}}
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0 fw-semibold">
                <i class="bi bi-people me-2"></i>Staff Allocation List
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Staff Name</th>
                            <th>Contact</th>
                            <th class="text-center">Total Allocated</th>
                            <th class="text-center">Sold</th>
                            <th class="text-center">Available</th>
                            <th class="text-end">Total Value</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($staffMembers as $index => $staff)
                        <tr wire:key="staff-{{ $staff->id }}">
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                                        <i class="bi bi-person-fill text-primary"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold">{{ $staff->name }}</div>
                                        <small class="text-muted">{{ $staff->email }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $staff->contact ?? 'N/A' }}</td>
                            <td class="text-center">
                                <span class="badge bg-primary">{{ $staff->total_allocated ?? 0 }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success">{{ $staff->total_sold ?? 0 }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info">{{ $staff->available_quantity ?? 0 }}</span>
                            </td>
                            <td class="text-end">
                                <strong>Rs.{{ number_format($staff->total_value ?? 0, 2) }}</strong>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="{{ route('admin.staff-allocated-products.view', $staff->id) }}" 
                                        class="btn btn-sm btn-outline-primary" 
                                        title="View Products">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.staff-product-reentry', $staff->id) }}" 
                                        class="btn btn-sm btn-outline-success" 
                                        title="Re-entry">
                                        <i class="bi bi-arrow-return-left"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                <h6 class="text-muted">No staff members found</h6>
                                <p class="text-muted mb-0">Try adjusting your search criteria</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
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
