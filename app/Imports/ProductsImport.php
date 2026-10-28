<?php

namespace App\Imports;

use App\Models\ProductDetail;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\BrandList;
use App\Models\CategoryList;
use App\Models\ProductSupplier;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Illuminate\Support\Facades\DB;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure
{
    use SkipsFailures;

    private $defaultBrandId;
    private $defaultCategoryId;
    private $defaultSupplierId;
    private $successCount = 0;
    private $skipCount = 0;

    public function __construct()
    {
        // Set default IDs
        $this->setDefaultIds();
    }

    /**
     * Set default IDs for brand, category, and supplier
     */
    private function setDefaultIds()
    {
        // Get or create default brand
        $defaultBrand = BrandList::firstOrCreate(
            ['brand_name' => 'Default Brand'],
            ['status' => 'active']
        );
        $this->defaultBrandId = $defaultBrand->id;

        // Get or create default category
        $defaultCategory = CategoryList::firstOrCreate(
            ['category_name' => 'Default Category'],
            ['status' => 'active']
        );
        $this->defaultCategoryId = $defaultCategory->id;

        // Get or create default supplier
        $defaultSupplier = ProductSupplier::firstOrCreate(
            ['name' => 'Default Supplier'],
            [
                'phone' => '0000000000',
                'email' => 'default@supplier.com',
                'address' => 'Default Address',
                'status' => 'active'
            ]
        );
        $this->defaultSupplierId = $defaultSupplier->id;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Check if product with same code already exists
        $existingProduct = ProductDetail::where('code', $row['code'])->first();

        if ($existingProduct) {
            $this->skipCount++;
            return null; // Skip duplicate products
        }

        try {
            DB::beginTransaction();

            // Create product detail with CODE and NAME from Excel
            // All other fields get default/null values
            $product = ProductDetail::create([
                'code' => $row['code'],
                'name' => $row['name'],
                'model' => null, // Default null
                'image' => null, // Default null
                'description' => null, // Default null
                'barcode' => null, // Default null
                'status' => 'active', // Default active status
                'brand_id' => $this->defaultBrandId, // Default brand
                'category_id' => $this->defaultCategoryId, // Default category
                'supplier_id' => $this->defaultSupplierId, // Default supplier
            ]);

            // Get price values from Excel or use defaults
            $supplierPrice = isset($row['supplier_price']) && is_numeric($row['supplier_price']) ? (float) $row['supplier_price'] : 0.00;
            $retailPrice = isset($row['retail_price']) && is_numeric($row['retail_price']) ? (float) $row['retail_price'] : 0.00;
            $wholesalePrice = isset($row['wholesale_price']) && is_numeric($row['wholesale_price']) ? (float) $row['wholesale_price'] : 0.00;

            // Create price record with values from Excel
            ProductPrice::create([
                'product_id' => $product->id,
                'supplier_price' => $supplierPrice,
                'retail_price' => $retailPrice,
                'wholesale_price' => $wholesalePrice,
                'selling_price' => $retailPrice, // Set selling_price to retail_price
                'discount_price' => 0.00, // Default 0
            ]);

            // Get available stock from Excel or use default
            $availableStock = isset($row['available_stock']) && is_numeric($row['available_stock']) ? (int) $row['available_stock'] : 0;

            // Create stock record with available_stock = total_stock
            ProductStock::create([
                'product_id' => $product->id,
                'available_stock' => $availableStock,
                'damage_stock' => 0, // Default 0
                'total_stock' => $availableStock, // Set total_stock equal to available_stock
            ]);

            DB::commit();
            $this->successCount++;

            return $product;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->skipCount++;
            return null;
        }
    }

    /**
     * Validation rules for each row
     */
    public function rules(): array
    {
        return [
            'code' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'supplier_price' => 'nullable|numeric|min:0',
            'retail_price' => 'nullable|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'available_stock' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Custom validation messages
     */
    public function customValidationMessages()
    {
        return [
            'code.required' => 'Product code is required',
            'name.required' => 'Product name is required',
            'supplier_price.numeric' => 'Supplier price must be a valid number',
            'supplier_price.min' => 'Supplier price cannot be negative',
            'retail_price.numeric' => 'Retail price must be a valid number',
            'retail_price.min' => 'Retail price cannot be negative',
            'wholesale_price.numeric' => 'Wholesale price must be a valid number',
            'wholesale_price.min' => 'Wholesale price cannot be negative',
            'available_stock.integer' => 'Available stock must be a valid number',
            'available_stock.min' => 'Available stock cannot be negative',
        ];
    }

    /**
     * Get the count of successfully imported products
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * Get the count of skipped products
     */
    public function getSkipCount(): int
    {
        return $this->skipCount;
    }

    /**
     * Get heading row configuration
     */
    public function headingRow(): int
    {
        return 1; // First row contains headers
    }
}
