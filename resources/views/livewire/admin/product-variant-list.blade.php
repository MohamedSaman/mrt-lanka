<div>
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-layers-fill text-success me-2"></i> Product Variant Management
            </h3>
            <p class="text-muted mb-0">Manage and organize your product variants efficiently</p>
        </div>
        <div>
            <button class="btn btn-primary" wire:click="createVariant">
                <i class="bi bi-plus-lg me-2"></i> Add Variant
            </button>
        </div>
    </div>

    <!-- Variant List Table -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">
                            <i class="bi bi-list-ul text-primary me-2"></i> Variant List
                        </h5>
                        <p class="text-muted small mb-0">View and manage all product variants</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label class="text-sm text-muted fw-medium">Show</label>
                        <select wire:model.live="perPage" class="form-select form-select-sm" style="width: 80px;">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                            <option value="500">500</option>
                        </select>
                        <span class="text-sm text-muted">entries</span>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">No</th>
                                    <th>Variant Name</th>
                                    <th>Values</th>
                                    <th>Status</th>
                                    <th>Used In</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if ($variants->count() > 0)
                                    @foreach ($variants as $variant)
                                        <tr>
                                            <td class="ps-4">
                                                <span class="fw-medium text-dark">{{ $loop->iteration }}</span>
                                            </td>
                                            <td>
                                                <span class="fw-medium text-dark">{{ $variant->variant_name }}</span>
                                            </td>
                                            <td class="align-middle">
                                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                                    @foreach($variant->variant_values as $value)
                                                    <span class="badge bg-info badge-value" title="{{ $value }}">{{ $value }}</span>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td>
                                                @if($variant->status === 'active')
                                                <span class="badge bg-success">Active</span>
                                                @else
                                                <span class="badge bg-danger">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">{{ $variant->products_count ?? $variant->products()->count() }} products</span>
                                            </td>
                                            <td class="text-end pe-4 align-middle">
                                                <button class="btn btn-sm btn-outline-primary me-2" wire:click="editVariant({{ $variant->id }})" title="Edit Variant">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" wire:click="confirmDelete({{ $variant->id }})" title="Delete Variant">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="alert alert-primary bg-opacity-10">
                                            <i class="bi bi-info-circle me-2"></i> No product variants found.
                                        </div>
                                    </td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-center">
                            {{ $variants->links('livewire.custom-pagination') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Variant Modal -->
    <div wire:ignore.self class="modal fade" id="createVariantModal" tabindex="-1" aria-labelledby="createVariantModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-plus-circle me-2"></i> Add Variant
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="saveVariant">
                        <!-- Variant Name -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Variant Name</label>
                            <input type="text" class="form-control" wire:model="variant_name" placeholder="e.g., Size, Color, Material" required>
                            @error('variant_name')
                            <span class="text-danger small">* {{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Variant Values -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Variant Values</label>
                            <div class="row mb-3">
                                <div class="col-md-9">
                                    <input type="text" class="form-control" 
                                        wire:model="variant_value_input"
                                        wire:keydown.enter.prevent="addVariantValue"
                                        placeholder="e.g., 5, 6, 7 or Small, Medium">
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-success w-100" wire:click="addVariantValue">
                                        <i class="bi bi-plus-circle me-1"></i> Add Value
                                    </button>
                                </div>
                            </div>

                            @if(count($variant_values) > 0)
                            <div class="alert alert-light">
                                <strong>Added Values ({{ count($variant_values) }}):</strong>
                                <ul class="list-group mt-2" id="variant-values-list">
                                    @foreach($variant_values as $value)
                                    <li class="list-group-item d-flex align-items-center justify-content-between drag-item" data-value="{{ $value }}">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></span>
                                            <span class="fw-medium ms-2">{{ $value }}</span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeVariantValue('{{ $value }}')" aria-label="Remove">&times;</button>
                                    </li>
                                    @endforeach
                                </ul>
                                <div class="text-muted small mt-2">Drag to reorder values or add new ones to auto-sort.</div>
                            </div>
                            @else
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i> No values added yet.
                            </div>
                            @endif

                            @error('variant_values')
                            <span class="text-danger small">* {{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Status -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" wire:model="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            @error('status')
                            <span class="text-danger small">* {{ $message }}</span>
                            @enderror
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2-circle me-1"></i> Create Variant
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Variant Modal -->
    <div wire:ignore.self class="modal fade" id="editVariantModal" tabindex="-1" aria-labelledby="editVariantModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-pencil-square me-2"></i> Edit Variant
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="updateVariant">
                        <!-- Variant Name -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Variant Name</label>
                            <input type="text" class="form-control" wire:model.defer="editVariantName" placeholder="e.g., Size, Color, Material" required>
                            @error('editVariantName')
                            <span class="text-danger small">* {{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Variant Values -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Variant Values</label>
                            <div class="row mb-3">
                                <div class="col-md-9">
                                    <input type="text" class="form-control" 
                                        wire:model="editVariantValueInput"
                                        wire:keydown.enter.prevent="addEditVariantValue"
                                        placeholder="e.g., 5, 6, 7 or Small, Medium">
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-success w-100" wire:click="addEditVariantValue">
                                        <i class="bi bi-plus-circle me-1"></i> Add Value
                                    </button>
                                </div>
                            </div>

                            @if(count($editVariantValues) > 0)
                            <div class="alert alert-light">
                                <strong>Added Values ({{ count($editVariantValues) }}):</strong>
                                <ul class="list-group mt-2" id="edit-variant-values-list">
                                    @foreach($editVariantValues as $value)
                                    <li class="list-group-item d-flex align-items-center justify-content-between drag-item" data-value="{{ $value }}">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></span>
                                            <span class="fw-medium ms-2">{{ $value }}</span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeEditVariantValue('{{ $value }}')" aria-label="Remove">&times;</button>
                                    </li>
                                    @endforeach
                                </ul>
                                <div class="text-muted small mt-2">
                                    <i class="bi bi-arrows-move me-1"></i> Drag to reorder values. Changes are saved when you click "Update Variant".
                                </div>
                            </div>
                            @else
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i> No values added yet.
                            </div>
                            @endif

                            @error('editVariantValues')
                            <span class="text-danger small">* {{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Status -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" wire:model.defer="editStatus" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            @error('editStatus')
                            <span class="text-danger small">* {{ $message }}</span>
                            @enderror
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2-circle me-1"></i> Update Variant
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Global sortable instances storage
        window.variantSortables = {
            create: null,
            edit: null
        };

        // Load SortableJS from CDN
        if (!window.Sortable) {
            const sortableScript = document.createElement('script');
            sortableScript.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js';
            sortableScript.onload = initializeSortables;
            document.head.appendChild(sortableScript);
        } else {
            initializeSortables();
        }

        function initializeSortables() {
            // Initialize sortable for create modal
            function initCreateSortable() {
                const el = document.getElementById('variant-values-list');
                if (!el) return;
                
                // Destroy existing instance
                if (window.variantSortables.create) {
                    window.variantSortables.create.destroy();
                }
                
                window.variantSortables.create = Sortable.create(el, {
                    animation: 150,
                    handle: '.drag-handle',
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    dragClass: 'sortable-drag',
                    onEnd: function(evt) {
                        const values = Array.from(el.querySelectorAll('[data-value]'))
                            .map(item => item.dataset.value);
                        
                        // Use Livewire.dispatch for Livewire 3
                        if (typeof Livewire !== 'undefined') {
                            Livewire.dispatch('reorderVariantValues', { values: values });
                        }
                    }
                });
            }

            // Initialize sortable for edit modal
            function initEditSortable() {
                const el = document.getElementById('edit-variant-values-list');
                if (!el) return;
                
                // Destroy existing instance
                if (window.variantSortables.edit) {
                    window.variantSortables.edit.destroy();
                }
                
                window.variantSortables.edit = Sortable.create(el, {
                    animation: 150,
                    handle: '.drag-handle',
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    dragClass: 'sortable-drag',
                    onEnd: function(evt) {
                        const values = Array.from(el.querySelectorAll('[data-value]'))
                            .map(item => item.dataset.value);
                        
                        // Use Livewire.dispatch for Livewire 3
                        if (typeof Livewire !== 'undefined') {
                            Livewire.dispatch('reorderEditVariantValues', { values: values });
                        }
                    }
                });
            }

            // Livewire event listeners
            if (typeof Livewire !== 'undefined') {
                // Listen for create modal open
                Livewire.on('create-variant', () => {
                    const modal = new bootstrap.Modal(document.getElementById('createVariantModal'));
                    modal.show();
                    
                    // Initialize sortable after modal is shown
                    setTimeout(() => {
                        initCreateSortable();
                    }, 300);
                });

                // Listen for edit modal open
                Livewire.on('edit-variant', () => {
                    const modal = new bootstrap.Modal(document.getElementById('editVariantModal'));
                    modal.show();
                    
                    // Initialize sortable after modal is shown and DOM is ready
                    setTimeout(() => {
                        initEditSortable();
                    }, 300);
                });

                // Listen for re-initialization request
                Livewire.on('reinit-edit-sortable', () => {
                    setTimeout(() => {
                        initEditSortable();
                    }, 100);
                });

                // Confirm delete
                Livewire.on('confirm-delete', () => {
                    Swal.fire({
                        title: 'Are you sure?',
                        text: 'You will not be able to recover this variant!',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Yes, delete it!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Livewire.dispatch('confirmDelete');
                        }
                    });
                });

                // Re-initialize after Livewire updates (for Livewire 3)
                document.addEventListener('livewire:init', () => {
                    Livewire.hook('morph.updated', ({ el, component }) => {
                        // Re-init if the lists exist
                        setTimeout(() => {
                            if (document.getElementById('variant-values-list')) {
                                initCreateSortable();
                            }
                            if (document.getElementById('edit-variant-values-list')) {
                                initEditSortable();
                            }
                        }, 50);
                    });
                });
            }

            // Handle Bootstrap modal events
            const createModal = document.getElementById('createVariantModal');
            const editModal = document.getElementById('editVariantModal');

            if (createModal) {
                createModal.addEventListener('shown.bs.modal', () => {
                    setTimeout(initCreateSortable, 100);
                });
            }

            if (editModal) {
                editModal.addEventListener('shown.bs.modal', () => {
                    setTimeout(initEditSortable, 100);
                });
            }
        }

        // Add CSS for sortable states
        const style = document.createElement('style');
        style.textContent = `
            .sortable-ghost {
                opacity: 0.4;
                background: #f8f9fa;
            }
            .sortable-chosen {
                background: #e3f2fd;
            }
            .sortable-drag {
                opacity: 0.8;
            }
            .drag-handle:hover {
                color: #0d6efd !important;
            }
        `;
        document.head.appendChild(style);
    </script>
    @endpush
</div>