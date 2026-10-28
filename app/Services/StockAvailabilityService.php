<?php

namespace App\Services;

use App\Models\ProductStock;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;

class StockAvailabilityService
{
    /**
     * Get available stock for a product (actual stock - pending sales quantity)
     * 
     * @param int $productId
     * @param string|null $variantValue
     * @return array ['stock' => int, 'pending' => int, 'available' => int]
     */
    public function getAvailableStock(int $productId, ?string $variantValue = null): array
    {
        // Get actual stock
        $query = ProductStock::where('product_id', $productId);

        if ($variantValue) {
            $query->where('variant_value', $variantValue);
        } else {
            $query->whereNull('variant_value');
        }

        $stock = $query->first();
        $actualStock = $stock ? $stock->available_stock : 0;

        // Get pending quantity from pending sales
        $pendingQuantity = $this->getPendingQuantity($productId, $variantValue);

        // Calculate available stock
        $availableStock = max(0, $actualStock - $pendingQuantity);

        return [
            'stock' => $actualStock,
            'pending' => $pendingQuantity,
            'available' => $availableStock,
        ];
    }

    /**
     * Get pending quantity for a product from pending (not approved) sales
     * 
     * @param int $productId
     * @param string|null $variantValue
     * @return int
     */
    public function getPendingQuantity(int $productId, ?string $variantValue = null): int
    {
        $query = SaleItem::whereHas('sale', function ($q) {
            $q->where('status', 'pending'); // Only pending sales
        })
            ->where('product_id', $productId);

        if ($variantValue) {
            $query->where('variant_value', $variantValue);
        }

        return $query->sum('quantity');
    }

    /**
     * Get available stock for multiple products at once
     * 
     * @param array $productIds Array of product IDs
     * @return array Keyed by product ID
     */
    public function getAvailableStockBulk(array $productIds): array
    {
        $result = [];

        // Get all stocks
        $stocks = ProductStock::whereIn('product_id', $productIds)
            ->get()
            ->groupBy('product_id');

        // Get all pending quantities
        $pendingItems = SaleItem::whereHas('sale', function ($q) {
            $q->where('status', 'pending');
        })
            ->whereIn('product_id', $productIds)
            ->select('product_id', DB::raw('SUM(quantity) as pending_qty'))
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        foreach ($productIds as $productId) {
            $productStocks = $stocks->get($productId, collect());
            $actualStock = $productStocks->sum('available_stock');
            $pendingQty = $pendingItems->get($productId)?->pending_qty ?? 0;

            $result[$productId] = [
                'stock' => $actualStock,
                'pending' => $pendingQty,
                'available' => max(0, $actualStock - $pendingQty),
            ];
        }

        return $result;
    }

    /**
     * Check if enough stock is available for a sale
     * 
     * @param array $items Array of ['product_id' => int, 'quantity' => int, 'variant_value' => string|null]
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateStockAvailability(array $items): array
    {
        $errors = [];

        foreach ($items as $item) {
            $availability = $this->getAvailableStock(
                $item['product_id'],
                $item['variant_value'] ?? null
            );

            if ($item['quantity'] > $availability['available']) {
                $errors[] = [
                    'product_id' => $item['product_id'],
                    'variant_value' => $item['variant_value'] ?? null,
                    'requested' => $item['quantity'],
                    'available' => $availability['available'],
                    'message' => "Insufficient stock. Requested: {$item['quantity']}, Available: {$availability['available']}",
                ];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get stock summary for salesman view
     * Shows actual stock and available (after pending sales)
     * 
     * @param int $productId
     * @param string|null $variantValue
     * @return string Formatted string like "100 (90 available)"
     */
    public function getStockDisplayText(int $productId, ?string $variantValue = null): string
    {
        $availability = $this->getAvailableStock($productId, $variantValue);

        if ($availability['pending'] > 0) {
            return "{$availability['stock']} ({$availability['available']} available)";
        }

        return (string) $availability['stock'];
    }
}
