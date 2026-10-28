<?php

namespace App\Imports;

use App\Models\ProductDetail;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\BrandList;
use App\Models\CategoryList;
use App\Models\ProductSupplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Validators\Failure;

class ProductsImportWithVariants implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    private $successCount = 0;
    private $skipCount = 0;
    private $errors = [];

    // Default IDs for related records
    private $defaultBrandId;
    private $defaultCategoryId;
    private $defaultSupplierId;

    public function __construct()
    {
        $this->initializeDefaults();
    }

    /**
     * Initialize default brand, category, and supplier
     */
    private function initializeDefaults()
    {
        // Get or create default brand
        $this->defaultBrandId = BrandList::firstOrCreate(
            ['brand_name' => 'Default Brand'],
            ['status' => 'active']
        )->id;

        // Get or create default category
        $this->defaultCategoryId = CategoryList::firstOrCreate(
            ['category_name' => 'Default Category'],
            ['status' => 'active']
        )->id;

        // Get or create default supplier
        $this->defaultSupplierId = ProductSupplier::firstOrCreate(
            ['name' => 'Default Supplier'],
            [
                'phone' => '0000000000',
                'email' => 'default@supplier.com',
                'address' => 'Default Address',
                'status' => 'active'
            ]
        )->id;
    }

    /**
     * Process the imported collection
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            try {
                DB::beginTransaction();

                // Skip if product code is missing
                if (empty($row['code'])) {
                    $this->skipCount++;
                    $this->errors[] = "Row " . ($index + 2) . ": Product code is required";
                    DB::rollBack();
                    continue;
                }

                // Check if product already exists
                $existingProduct = ProductDetail::where('code', $row['code'])->first();
                if ($existingProduct) {
                    $this->skipCount++;
                    $this->errors[] = "Row " . ($index + 2) . ": Product code '{$row['code']}' already exists";
                    DB::rollBack();
                    continue;
                }

                // Determine pricing mode based on presence of variant_name
                $pricingMode = !empty($row['variant_name']) ? 'variant' : 'single';

                // Resolve foreign keys
                $brandId = $this->resolveBrand($row['brand'] ?? null);
                $categoryId = $this->resolveCategory($row['category'] ?? null);
                $supplierId = $this->resolveSupplier($row['supplier'] ?? null);
                $variantId = null;

                // Step 1: Create the product
                $product = ProductDetail::create([
                    'code' => $row['code'],
                    'name' => $row['name'] ?? 'Unnamed Product',
                    'model' => $row['model'] ?? null,
                    'image' => $row['image'] ?? null,
                    'description' => $row['description'] ?? null,
                    'barcode' => $row['barcode'] ?? null,
                    'status' => $row['status'] ?? 'active',
                    'brand_id' => $brandId,
                    'category_id' => $categoryId,
                    'supplier_id' => $supplierId,
                    'variant_id' => null, // Will be set if variant-based
                ]);

                if ($pricingMode === 'single') {
                    // Single pricing mode
                    $this->createSinglePricing($product, $row);
                } else {
                    // Variant-based pricing
                    $variantId = $this->resolveOrCreateVariant($row['variant_name'], $row['variant_values'] ?? null);
                    
                    // Update product with variant_id
                    $product->update(['variant_id' => $variantId]);

                    $this->createVariantPricing($product, $variantId, $row);
                }

                DB::commit();
                $this->successCount++;

                Log::info("Product imported successfully", [
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'pricing_mode' => $pricingMode
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                $this->skipCount++;
                $this->errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                
                Log::error("Product import failed", [
                    'row' => $index + 2,
                    'code' => $row['code'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Create single pricing and stock
     */
    private function createSinglePricing($product, $row)
    {
        // Create price record
        ProductPrice::create([
            'product_id' => $product->id,
            'variant_id' => null,
            'variant_value' => null,
            'pricing_mode' => 'single',
            'supplier_price' => $row['supplier_price'] ?? 0,
            'selling_price' => $row['retail_price'] ?? $row['supplier_price'] ?? 0,
            'retail_price' => $row['retail_price'] ?? 0,
            'wholesale_price' => $row['wholesale_price'] ?? 0,
            'distributor_price' => $row['distributor_price'] ?? 0,
            'discount_price' => $row['discount_price'] ?? 0,
        ]);

        // Create stock record
        $availableStock = $row['available_stock'] ?? 0;
        $damageStock = $row['damage_stock'] ?? 0;

        ProductStock::create([
            'product_id' => $product->id,
            'variant_id' => null,
            'variant_value' => null,
            'available_stock' => $availableStock,
            'damage_stock' => $damageStock,
            'total_stock' => $availableStock + $damageStock,
            'sold_count' => 0,
            'restocked_quantity' => 0,
        ]);
    }

    /**
     * Create variant-based pricing and stock
     */
    private function createVariantPricing($product, $variantId, $row)
    {
        $variant = ProductVariant::find($variantId);
        if (!$variant || empty($variant->variant_values)) {
            throw new \Exception("Variant not found or has no values");
        }

        // Parse variant data from Excel
        // Expected format: variant_value_1_price, variant_value_1_stock, etc.
        // Or: variant_prices (JSON), variant_stocks (JSON)
        
        $variantValues = $variant->variant_values;
        
        foreach ($variantValues as $value) {
            $sanitizedKey = $this->sanitizeVariantKey($value);
            
            // Try to get price and stock for this variant value
            // Option 1: Separate columns (e.g., size_small_price, size_small_stock)
            $priceKey = strtolower($sanitizedKey) . '_price';
            $stockKey = strtolower($sanitizedKey) . '_stock';
            
            $supplierPrice = $row[$priceKey . '_supplier'] ?? $row['supplier_price'] ?? 0;
            $retailPrice = $row[$priceKey . '_retail'] ?? $row['retail_price'] ?? 0;
            $wholesalePrice = $row[$priceKey . '_wholesale'] ?? $row['wholesale_price'] ?? 0;
            $distributorPrice = $row[$priceKey . '_distributor'] ?? $row['distributor_price'] ?? 0;
            $stock = $row[$stockKey] ?? 0;

            // Create price record for this variant value
            ProductPrice::create([
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'variant_value' => (string) $value,
                'pricing_mode' => 'variant',
                'supplier_price' => $supplierPrice,
                'selling_price' => $retailPrice,
                'retail_price' => $retailPrice,
                'wholesale_price' => $wholesalePrice,
                'distributor_price' => $distributorPrice,
                'discount_price' => 0,
            ]);

            // Create stock record for this variant value
            ProductStock::create([
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'variant_value' => (string) $value,
                'available_stock' => $stock,
                'damage_stock' => 0,
                'total_stock' => $stock,
                'sold_count' => 0,
                'restocked_quantity' => 0,
            ]);
        }
    }

    /**
     * Resolve or create brand
     */
    private function resolveBrand($brandName): int
    {
        if (empty($brandName)) {
            return $this->defaultBrandId;
        }

        $brand = BrandList::firstOrCreate(
            ['brand_name' => $brandName],
            ['status' => 'active']
        );

        return $brand->id;
    }

    /**
     * Resolve or create category
     */
    private function resolveCategory($categoryName): int
    {
        if (empty($categoryName)) {
            return $this->defaultCategoryId;
        }

        $category = CategoryList::firstOrCreate(
            ['category_name' => $categoryName],
            ['status' => 'active']
        );

        return $category->id;
    }

    /**
     * Resolve or create supplier
     */
    private function resolveSupplier($supplierName): int
    {
        if (empty($supplierName)) {
            return $this->defaultSupplierId;
        }

        $supplier = ProductSupplier::where('name', $supplierName)->first();
        
        if (!$supplier) {
            $supplier = ProductSupplier::create([
                'name' => $supplierName,
                'phone' => '0000000000',
                'email' => strtolower(str_replace(' ', '', $supplierName)) . '@example.com',
                'address' => 'Auto-created during import',
                'status' => 'active'
            ]);
        }

        return $supplier->id;
    }

    /**
     * Resolve or create variant
     */
    private function resolveOrCreateVariant($variantName, $variantValues): int
    {
        if (empty($variantName)) {
            throw new \Exception("Variant name is required for variant-based pricing");
        }

        // Parse variant values (expect comma-separated string or JSON array)
        if (is_string($variantValues)) {
            $values = array_map('trim', explode(',', $variantValues));
        } else if (is_array($variantValues)) {
            $values = $variantValues;
        } else {
            throw new \Exception("Invalid variant values format");
        }

        // Try to find existing variant with same name and values
        $variant = ProductVariant::where('variant_name', $variantName)
            ->get()
            ->first(function($v) use ($values) {
                $existingValues = $v->variant_values;
                sort($existingValues);
                sort($values);
                return $existingValues === $values;
            });

        if (!$variant) {
            $variant = ProductVariant::create([
                'variant_name' => $variantName,
                'variant_values' => $values,
                'status' => 'active',
            ]);
        }

        return $variant->id;
    }

    /**
     * Sanitize variant key for column naming
     */
    private function sanitizeVariantKey($value): string
    {
        $str = (string) $value;
        $sanitized = preg_replace('/[^A-Za-z0-9]+/', '_', $str);
        return 'v_' . trim($sanitized, '_');
    }

    /**
     * Validation rules
     */
    public function rules(): array
    {
        return [
            'code' => 'required|string|max:100',
            'name' => 'required|string|max:255',
            'supplier_price' => 'nullable|numeric|min:0',
            'retail_price' => 'nullable|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'distributor_price' => 'nullable|numeric|min:0',
            'available_stock' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Get success count
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * Get skip count
     */
    public function getSkipCount(): int
    {
        return $this->skipCount;
    }

    /**
     * Get errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Handle validation failures
     */
    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $this->skipCount++;
            $this->errors[] = "Row {$failure->row()}: " . implode(', ', $failure->errors());
        }
    }
}