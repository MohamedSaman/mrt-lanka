<?php

namespace App\Livewire\ShopStaff;

use App\Models\ProductDetail;
use App\Models\CategoryList;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Title('Shop Staff Dashboard')]
#[Layout('components.layouts.shop-staff')]
class ShopStaffDashboard extends Component
{
    public $totalProducts = 0;
    public $lowStockProducts = 0;
    public $outOfStockProducts = 0;
    public $totalCategories = 0;

    public function mount()
    {
        $this->totalProducts = ProductDetail::count();

        $this->lowStockProducts = ProductDetail::whereHas('stock', function ($q) {
            $q->where('available_stock', '>', 0)
                ->where('available_stock', '<=', 10);
        })->count();

        $this->outOfStockProducts = ProductDetail::whereHas('stock', function ($q) {
            $q->where('available_stock', '<=', 0);
        })->count();

        $this->totalCategories = CategoryList::count();
    }

    public function render()
    {
        return view('livewire.shop-staff.shop-staff-dashboard');
    }
}
