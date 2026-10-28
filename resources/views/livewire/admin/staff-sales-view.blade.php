<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1" style="color: #2a83df;">Staff Sales Summary</h2>
            <p class="text-muted">View sales summary for each staff member</p>
        </div>
        <div class="d-flex gap-2">
            <button wire:click="loadStaffSummary" class="btn btn-outline-primary rounded-2">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Search Staff</label>
                    <input type="text" class="form-control" wire:model.live="searchTerm" 
                        placeholder="Search by staff name or email...">
                </div>
            </div>
        </div>
    </div>

    <!-- Staff Summary Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="card-title mb-0 fw-bold" style="color: #2a83df;">
                <i class="bi bi-people me-2"></i>Staff Sales Overview
            </h5>
        </div>
        <div class="card-body p-0">
            @if (!empty($staffSummary) && count($staffSummary) > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Staff Member</th>
                                <th class="text-center">Total Sales</th>
                                <th class="text-end">Total Amount</th>
                                <th class="text-end">Paid Amount</th>
                                <th class="text-end">Pending Approval</th>
                                <th class="text-end">Due Amount</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($staffSummary as $staff)
                                <tr>
                                    <td>
                                        <div>
                                            <strong>{{ $staff['staff_name'] }}</strong>
                                            @if($staff['staff_email'])
                                                <div class="text-muted small">{{ $staff['staff_email'] }}</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info rounded-pill">{{ $staff['total_sales'] }}</span>
                                    </td>
                                    <td class="text-end">
                                        <strong class="text-primary">Rs. {{ number_format($staff['total_amount'], 2) }}</strong>
                                    </td>
                                    <td class="text-end">
                                        <strong class="text-success">Rs. {{ number_format($staff['paid_amount'], 2) }}</strong>
                                    </td>
                                    <td class="text-end">
                                        <strong class="text-warning">Rs. {{ number_format($staff['pending_approval_amount'], 2) }}</strong>
                                    </td>
                                    <td class="text-end">
                                        <strong class="text-danger">Rs. {{ number_format($staff['due_amount'], 2) }}</strong>
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('admin.staff-sales-detail', $staff['staff_id']) }}" 
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> View Sales
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-3 text-muted mb-3"></i>
                    <p class="text-muted">No staff members found</p>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endpush
