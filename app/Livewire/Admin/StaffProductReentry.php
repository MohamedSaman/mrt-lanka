<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\User;
use App\Models\StaffProduct;
use App\Models\ProductStock;
use App\Models\StaffReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Livewire\Concerns\WithDynamicLayout;

#[Title('Staff Product Re-entry')]
class StaffProductReentry extends Component
{
    use WithDynamicLayout;

    public $staffId;
    public $staff;
    public $search = '';
    public $allocatedProducts = [];

    // Modal properties
    public $showReentryModal = false;
    public $selectedStaffProduct;
    public $selectedProduct;
    public $damagedQuantity = 0;
    public $restockQuantity = 0;
    public $availableQuantity = 0;
    public $reason = '';
    public $notes = '';

    public function mount($staffId)
    {
        $this->staffId = $staffId;
        $this->loadStaff();
        $this->loadAllocatedProducts();
    }

    public function loadStaff()
    {
        $this->staff = User::find($this->staffId);

        if (!$this->staff || $this->staff->role !== 'staff') {
            abort(404, 'Staff member not found');
        }
    }

    public function loadAllocatedProducts()
    {
        $query = StaffProduct::where('staff_id', $this->staffId)
            ->whereRaw('quantity > sold_quantity') // Only products with available quantity
            ->with(['product.brand', 'product.category', 'product.price']);

        // Apply search filter
        if ($this->search) {
            $query->whereHas('product', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }

        $this->allocatedProducts = $query->orderBy('created_at', 'desc')->get()
            ->map(function ($item) {
                $item->available_quantity = $item->quantity - $item->sold_quantity;
                return $item;
            });
    }

    public function updatedSearch()
    {
        $this->loadAllocatedProducts();
    }

    public function openReentryModal($staffProductId)
    {
        $this->selectedStaffProduct = StaffProduct::with(['product.brand', 'product.category'])
            ->find($staffProductId);

        if (!$this->selectedStaffProduct) {
            $this->dispatch('error', 'Product allocation not found');
            return;
        }

        $this->selectedProduct = $this->selectedStaffProduct->product;
        $this->availableQuantity = $this->selectedStaffProduct->quantity - $this->selectedStaffProduct->sold_quantity;
        $this->damagedQuantity = 0;
        $this->restockQuantity = 0;
        $this->reason = '';
        $this->notes = '';
        $this->showReentryModal = true;
    }

    public function closeReentryModal()
    {
        $this->showReentryModal = false;
        $this->selectedStaffProduct = null;
        $this->selectedProduct = null;
        $this->reset(['damagedQuantity', 'restockQuantity', 'reason', 'notes']);
    }

    public function updatedDamagedQuantity($value)
    {
        $this->damagedQuantity = max(0, (int)$value);
        $this->validateQuantities();
    }

    public function updatedRestockQuantity($value)
    {
        $this->restockQuantity = max(0, (int)$value);
        $this->validateQuantities();
    }

    private function validateQuantities()
    {
        $total = $this->damagedQuantity + $this->restockQuantity;

        if ($total > $this->availableQuantity) {
            $this->dispatch('error', 'Total quantity cannot exceed available quantity');
            $this->restockQuantity = max(0, $this->availableQuantity - $this->damagedQuantity);
        }
    }

    public function submitReentry()
    {
        // Validation
        $totalQuantity = $this->damagedQuantity + $this->restockQuantity;

        if ($totalQuantity <= 0) {
            $this->dispatch('error', 'Please enter damaged or restock quantity');
            return;
        }

        if ($totalQuantity > $this->availableQuantity) {
            $this->dispatch('error', 'Total quantity exceeds available quantity');
            return;
        }

        try {
            DB::beginTransaction();

            // Record damaged items if any
            if ($this->damagedQuantity > 0) {
                StaffReturn::create([
                    'staff_id' => $this->staffId,
                    'product_id' => $this->selectedStaffProduct->product_id,
                    'customer_id' => null,
                    'quantity' => $this->damagedQuantity,
                    'unit_price' => $this->selectedStaffProduct->unit_price,
                    'total_amount' => $this->damagedQuantity * $this->selectedStaffProduct->unit_price,
                    'is_damaged' => true,
                    'reason' => $this->reason ?: 'Damaged items returned',
                    'notes' => $this->notes,
                    'status' => 'approved',
                ]);

                // Update product stock damage column
                $this->addDamageToStock($this->selectedStaffProduct->product_id, $this->damagedQuantity);
            }

            // Record good items being restocked
            if ($this->restockQuantity > 0) {
                StaffReturn::create([
                    'staff_id' => $this->staffId,
                    'product_id' => $this->selectedStaffProduct->product_id,
                    'customer_id' => null,
                    'quantity' => $this->restockQuantity,
                    'unit_price' => $this->selectedStaffProduct->unit_price,
                    'total_amount' => $this->restockQuantity * $this->selectedStaffProduct->unit_price,
                    'is_damaged' => false,
                    'reason' => $this->reason ?: 'Stock returned to inventory',
                    'notes' => $this->notes,
                    'status' => 'approved',
                ]);

                // Add back to product stock
                $this->addBackToStock($this->selectedStaffProduct->product_id, $this->restockQuantity);
            }

            // Update staff product record
            $this->selectedStaffProduct->quantity -= $totalQuantity;

            // If no quantity left, mark as returned
            if ($this->selectedStaffProduct->quantity <= $this->selectedStaffProduct->sold_quantity) {
                $this->selectedStaffProduct->status = 'returned';
            }

            $this->selectedStaffProduct->save();

            DB::commit();

            $this->dispatch('success', 'Product re-entry completed successfully');
            $this->closeReentryModal();
            $this->loadAllocatedProducts();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Staff Product Re-entry Error: ' . $e->getMessage());
            $this->dispatch('error', 'Error processing re-entry: ' . $e->getMessage());
        }
    }

    private function addBackToStock($productId, $quantity)
    {
        // Find the most recent stock batch or create a new one
        $latestStock = ProductStock::where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($latestStock) {
            $latestStock->available_stock += $quantity;
            $latestStock->updateTotals();
        } else {
            // Create a new stock entry if none exists
            ProductStock::create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'available_stock' => $quantity,
                'batch_number' => 'RET-' . time(),
                'cost_price' => 0,
                'selling_price' => 0,
            ]);
        }
    }

    private function addDamageToStock($productId, $quantity)
    {
        // Find the most recent stock batch or create a new one
        $latestStock = ProductStock::where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($latestStock) {
            $latestStock->damage_stock += $quantity;
            $latestStock->updateTotals(); // Recalculate total_stock = available_stock + damage_stock
        } else {
            // Create a new stock entry if none exists
            ProductStock::create([
                'product_id' => $productId,
                'quantity' => 0,
                'available_stock' => 0,
                'damage_stock' => $quantity,
                'batch_number' => 'DAM-' . time(),
                'cost_price' => 0,
                'selling_price' => 0,
            ]);
        }
    }

    public function render()
    {
        return view('livewire.admin.staff-product-reentry')->layout($this->layout);
    }
}
