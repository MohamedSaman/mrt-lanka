<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\ProductVariant;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use App\Livewire\Concerns\WithDynamicLayout;
use Illuminate\Support\Facades\DB;
use Exception;
use Livewire\WithPagination;

#[Title("Manage Variants")]
class ProductVariants extends Component
{
    use WithDynamicLayout;
    use WithPagination;

    // Livewire events from JS
    protected $listeners = [
        'reorderVariantValues' => 'reorderVariantValues',
        'reorderEditVariantValues' => 'reorderEditVariantValues',
    ];

    public $variant_name = '';
    public $variant_values = [];
    public $variant_value_input = '';
    public $status = 'active';

    // Edit fields
    public $editVariantId;
    public $editVariantName = '';
    public $editVariantValues = [];
    public $editVariantValueInput = '';
    public $editStatus = 'active';

    public $perPage = 10;

    // Delete field
    public $deleteId;

    protected $rules = [
        'variant_name' => 'required|string|min:3|max:50',
        'variant_values' => 'required|array|min:1',
        'variant_values.*' => 'required|string|min:1',
        'status' => 'required|in:active,inactive',
    ];

    public function render()
    {
        // Eager-load products count to avoid N+1 queries in the view
        $variants = ProductVariant::withCount('products')->orderBy('id', 'desc')->paginate($this->perPage);
        return view('livewire.admin.product-variant-list', compact('variants'))->layout($this->layout);
    }

    /**
     * Open create modal
     */
    public function createVariant()
    {
        $this->resetForm();
        $this->dispatch('create-variant');
    }

    /**
     * Add a variant value
     */
    public function addVariantValue()
    {
        if (!empty(trim($this->variant_value_input))) {
            $value = trim($this->variant_value_input);

            if (!in_array($value, $this->variant_values)) {
                $this->variant_values[] = $value;

                // Auto-sort values after adding (numeric sort when all values numeric)
                $this->variant_values = $this->sortValues($this->variant_values);

                $this->variant_value_input = '';
            }
        }
    }

    /**
     * Remove a variant value
     */
    public function removeVariantValue($value)
    {
        $key = array_search($value, $this->variant_values);
        if ($key !== false) {
            unset($this->variant_values[$key]);
            $this->variant_values = array_values($this->variant_values);
        }
    }

    /**
     * Save new variant
     */
    public function saveVariant()
    {
        $this->validate();

        try {
            ProductVariant::create([
                'variant_name' => $this->variant_name,
                'variant_values' => $this->variant_values,
                'status' => $this->status,
            ]);

            $this->resetForm();
            $this->js("$('#createVariantModal').modal('hide')");
            $this->js("Swal.fire('Success!', 'Variant created successfully!', 'success')");
        } catch (Exception $e) {
            $this->js("Swal.fire('Error!', '" . $e->getMessage() . "', 'error')");
        }
    }

    /**
     * Open edit modal
     */
    public function editVariant($id)
    {
        $variant = ProductVariant::find($id);
        if (!$variant) {
            $this->js("Swal.fire('Error!', 'Variant not found', 'error')");
            return;
        }

        $this->editVariantId = $variant->id;
        $this->editVariantName = $variant->variant_name;
        $this->editVariantValues = $variant->variant_values ?? [];
        $this->editStatus = $variant->status;
        $this->editVariantValueInput = '';
        $this->resetErrorBag();
        $this->resetValidation();
        $this->dispatch('edit-variant');
    }

    /**
     * Add value to edit form
     */
    public function addEditVariantValue()
    {
        if (!empty(trim($this->editVariantValueInput))) {
            $value = trim($this->editVariantValueInput);

            if (!in_array($value, $this->editVariantValues)) {
                $this->editVariantValues[] = $value;

                // Auto-sort values after adding (numeric sort when all values numeric)
                $this->editVariantValues = $this->sortValues($this->editVariantValues);

                $this->editVariantValueInput = '';
            }
        }
        
        // Trigger re-initialization of sortable after DOM update
        $this->dispatch('reinit-edit-sortable');
    }

    /**
     * Remove value from edit form
     */
    public function removeEditVariantValue($value)
    {
        $key = array_search($value, $this->editVariantValues);
        if ($key !== false) {
            unset($this->editVariantValues[$key]);
            $this->editVariantValues = array_values($this->editVariantValues);
        }
        
        // Trigger re-initialization of sortable after DOM update
        $this->dispatch('reinit-edit-sortable');
    }

    /**
     * Reorder handlers called from SortableJS via Livewire.emit/dispatch
     */
    public function reorderVariantValues($values)
    {
        if (is_string($values)) {
            $values = json_decode($values, true) ?: [$values];
        }
        $this->variant_values = array_values($values ?? []);
    }

    public function reorderEditVariantValues($values)
    {
        if (is_string($values)) {
            $values = json_decode($values, true) ?: [$values];
        }
        $this->editVariantValues = array_values($values ?? []);
        
        // No need to save here - just update the property
        // The user will click "Update Variant" to save
    }

    /**
     * Update variant
     */
    public function updateVariant()
    {
        $this->validate([
            'editVariantName' => 'required|string|min:3|max:50',
            'editVariantValues' => 'required|array|min:1',
            'editVariantValues.*' => 'required|string|min:1',
            'editStatus' => 'required|in:active,inactive',
        ]);

        try {
            $variant = ProductVariant::find($this->editVariantId);
            if ($variant) {
                $variant->update([
                    'variant_name' => $this->editVariantName,
                    'variant_values' => $this->editVariantValues, // This saves the reordered values
                    'status' => $this->editStatus,
                ]);

                $this->resetForm();
                $this->js("$('#editVariantModal').modal('hide')");
                $this->js("Swal.fire('Success!', 'Variant updated successfully!', 'success')");
            }
        } catch (Exception $e) {
            $this->js("Swal.fire('Error!', '" . $e->getMessage() . "', 'error')");
        }
    }

    /**
     * Confirm delete
     */
    public function confirmDelete($id)
    {
        $this->deleteId = $id;
        $this->dispatch('confirm-delete');
    }

    /**
     * Delete variant
     */
    #[On('confirmDelete')]
    public function deleteVariant()
    {
        try {
            $variant = ProductVariant::find($this->deleteId);
            if ($variant) {
                // Check if variant is used in products
                $usageCount = $variant->products()->count();

                if ($usageCount > 0) {
                    $this->js("Swal.fire('Error!', 'Cannot delete! This variant is used in $usageCount product(s).', 'error')");
                    return;
                }

                $variant->delete();
                $this->js("Swal.fire('Success!', 'Variant deleted successfully!', 'success')");
            }
        } catch (Exception $e) {
            $this->js("Swal.fire('Error!', '" . $e->getMessage() . "', 'error')");
        }
    }

    /**
     * Reset form fields
     */
    private function resetForm()
    {
        $this->variant_name = '';
        $this->variant_values = [];
        $this->variant_value_input = '';
        $this->status = 'active';
        $this->editVariantId = null;
        $this->editVariantName = '';
        $this->editVariantValues = [];
        $this->editVariantValueInput = '';
        $this->editStatus = 'active';
        $this->resetValidation();
    }

    /**
     * Sort helper - numeric sorting when all values numeric; otherwise natural case-insensitive sort
     * Ensures unique values and stable ordering
     */
    private function sortValues(array $values)
    {
        // Normalize and unique
        $values = array_values(array_unique(array_map('trim', $values)));

        // Detect numeric-only
        $numericCount = count(array_filter($values, function ($v) {
            return is_numeric($v);
        }));
        if ($numericCount === count($values) && $numericCount > 0) {
            usort($values, function ($a, $b) {
                return floatval($a) <=> floatval($b);
            });
            return $values;
        }

        // Fallback: natural, case-insensitive sort
        natcasesort($values);
        return array_values($values);
    }
}