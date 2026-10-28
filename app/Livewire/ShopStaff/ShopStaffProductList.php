<?php

namespace App\Livewire\ShopStaff;

use App\Models\ProductDetail;
use App\Models\CategoryList;
use App\Models\SaleItem;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Title('Product List')]
#[Layout('components.layouts.shop-staff')]
class ShopStaffProductList extends Component
{
    use WithPagination;

    public $search = '';
    public $categoryFilter = '';
    public $stockFilter = 'all'; // all, in_stock, low_stock, out_of_stock
    public $categories = [];

    public function mount()
    {
        $this->categories = CategoryList::orderBy('category_name')->get();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter()
    {
        $this->resetPage();
    }

    public function updatedStockFilter()
    {
        $this->resetPage();
    }

    public function loadProducts()
    {
        $query = ProductDetail::with(['stock', 'price', 'category', 'variant', 'stocks', 'prices']);

        if ($this->categoryFilter) {
            $query->where('category_id', $this->categoryFilter);
        }

        $items = [];
        $products = $query->get();

        foreach ($products as $product) {
            // If product has variant stocks, expand each variant
            if (($product->variant_id ?? null) !== null && $product->stocks && $product->stocks->isNotEmpty()) {
                $orderedValues = [];
                if ($product->variant && is_array($product->variant->variant_values) && count($product->variant->variant_values) > 0) {
                    $orderedValues = $product->variant->variant_values;
                }

                $stocksByValue = [];
                foreach ($product->stocks as $stock) {
                    $stocksByValue[$stock->variant_value] = $stock;
                }

                if (!empty($orderedValues)) {
                    foreach ($orderedValues as $val) {
                        if (!isset($stocksByValue[$val])) continue;
                        $stock = $stocksByValue[$val];
                        $priceRecord = $product->prices->firstWhere('variant_value', $stock->variant_value) ?? $product->price;

                        $pendingQty = SaleItem::whereHas('sale', function ($q) {
                            $q->where('status', 'pending');
                        })
                            ->where('product_id', $product->id)
                            ->where('variant_value', $stock->variant_value)
                            ->sum('quantity');

                        $availableStock = max(0, ($stock->available_stock ?? 0) - $pendingQty);

                        // Apply stock filter
                        if ($this->stockFilter === 'in_stock' && $availableStock <= 10) continue;
                        if ($this->stockFilter === 'low_stock' && ($availableStock <= 0 || $availableStock > 10)) continue;
                        if ($this->stockFilter === 'out_of_stock' && $availableStock > 0) continue;

                        $items[] = [
                            'id' => $product->id . '::' . $stock->variant_value,
                            'product_id' => $product->id,
                            'variant_value' => $stock->variant_value,
                            'name' => $product->name . ' (' . $stock->variant_value . ')',
                            'code' => $product->code,
                            'image' => $product->image ?? '',
                            'category' => $product->category->name ?? 'N/A',
                            'retail_price' => $priceRecord->retail_price ?? 0,
                            'wholesale_price' => $priceRecord->wholesale_price ?? 0,
                            'stock' => $availableStock,
                            'pending' => $pendingQty,
                        ];
                    }

                    foreach ($stocksByValue as $v => $stock) {
                        if (in_array($v, $orderedValues)) continue;
                        $priceRecord = $product->prices->firstWhere('variant_value', $stock->variant_value) ?? $product->price;

                        $pendingQty = SaleItem::whereHas('sale', function ($q) {
                            $q->where('status', 'pending');
                        })
                            ->where('product_id', $product->id)
                            ->where('variant_value', $stock->variant_value)
                            ->sum('quantity');

                        $availableStock = max(0, ($stock->available_stock ?? 0) - $pendingQty);

                        // Apply stock filter
                        if ($this->stockFilter === 'in_stock' && $availableStock <= 10) continue;
                        if ($this->stockFilter === 'low_stock' && ($availableStock <= 0 || $availableStock > 10)) continue;
                        if ($this->stockFilter === 'out_of_stock' && $availableStock > 0) continue;

                        $items[] = [
                            'id' => $product->id . '::' . $stock->variant_value,
                            'product_id' => $product->id,
                            'variant_value' => $stock->variant_value,
                            'name' => $product->name . ' (' . $stock->variant_value . ')',
                            'code' => $product->code,
                            'image' => $product->image ?? '',
                            'category' => $product->category->name ?? 'N/A',
                            'retail_price' => $priceRecord->retail_price ?? 0,
                            'wholesale_price' => $priceRecord->wholesale_price ?? 0,
                            'stock' => $availableStock,
                            'pending' => $pendingQty,
                        ];
                    }
                } else {
                    foreach ($product->stocks as $stock) {
                        if (($stock->available_stock ?? 0) <= 0) continue;
                        $priceRecord = $product->prices->firstWhere('variant_value', $stock->variant_value) ?? $product->price;

                        $pendingQty = SaleItem::whereHas('sale', function ($q) {
                            $q->where('status', 'pending');
                        })
                            ->where('product_id', $product->id)
                            ->where('variant_value', $stock->variant_value)
                            ->sum('quantity');

                        $availableStock = max(0, ($stock->available_stock ?? 0) - $pendingQty);

                        // Apply stock filter
                        if ($this->stockFilter === 'in_stock' && $availableStock <= 10) continue;
                        if ($this->stockFilter === 'low_stock' && ($availableStock <= 0 || $availableStock > 10)) continue;
                        if ($this->stockFilter === 'out_of_stock' && $availableStock > 0) continue;

                        $items[] = [
                            'id' => $product->id . '::' . $stock->variant_value,
                            'product_id' => $product->id,
                            'variant_value' => $stock->variant_value,
                            'name' => $product->name . ' (' . $stock->variant_value . ')',
                            'code' => $product->code,
                            'image' => $product->image ?? '',
                            'category' => $product->category->name ?? 'N/A',
                            'retail_price' => $priceRecord->retail_price ?? 0,
                            'wholesale_price' => $priceRecord->wholesale_price ?? 0,
                            'stock' => $availableStock,
                            'pending' => $pendingQty,
                        ];
                    }
                }
            } else {
                // Single product without variants
                $priceRecord = $product->price;
                $stockQty = $product->stock->available_stock ?? 0;

                $pendingQty = SaleItem::whereHas('sale', function ($q) {
                    $q->where('status', 'pending');
                })
                    ->where('product_id', $product->id)
                    ->sum('quantity');

                $availableStock = max(0, $stockQty - $pendingQty);

                // Apply stock filter
                if ($this->stockFilter === 'in_stock' && $availableStock <= 10) continue;
                if ($this->stockFilter === 'low_stock' && ($availableStock <= 0 || $availableStock > 10)) continue;
                if ($this->stockFilter === 'out_of_stock' && $availableStock > 0) continue;

                $items[] = [
                    'id' => $product->id,
                    'product_id' => $product->id,
                    'variant_value' => null,
                    'name' => $product->name,
                    'code' => $product->code,
                    'image' => $product->image ?? '',
                    'category' => $product->category->name ?? 'N/A',
                    'retail_price' => $priceRecord->retail_price ?? 0,
                    'wholesale_price' => $priceRecord->wholesale_price ?? 0,
                    'stock' => $availableStock,
                    'pending' => $pendingQty,
                ];
            }
        }

        // Filter items by search term (including variant values)
        if ($this->search) {
            $searchTerm = strtolower($this->search);
            $items = array_filter($items, function ($item) use ($searchTerm) {
                return str_contains(strtolower($item['name']), $searchTerm)
                    || str_contains(strtolower($item['code']), $searchTerm)
                    || (isset($item['variant_value']) && str_contains(strtolower($item['variant_value']), $searchTerm));
            });
        }

        return $items;
    }

    public function render()
    {
        $products = $this->loadProducts();

        return view('livewire.shop-staff.shop-staff-product-list', [
            'products' => $products,
            'categories' => $this->categories,
        ]);
    }
}
