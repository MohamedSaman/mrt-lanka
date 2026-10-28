# Updated Product Variant System - JSON-Based Implementation

## Overview

The variant system has been redesigned to store variant values in JSON format. This allows for a cleaner structure where one variant record (e.g., "Size") can contain multiple values (e.g., [5, 6, 7, 8]).

## Database Structure

### 1. `product_variants` Table

```sql
- id
- product_id (FK to product_details)
- variant_name (VARCHAR) - e.g., "Size", "Color"
- variant_values (JSON) - e.g., ["5", "6", "7", "8"] or ["Red", "Blue", "Green"]
- status (ENUM: active/inactive)
- created_at, updated_at
```

**Key Changes:**

- ✅ `variant_values` is now JSON (not single value)
- ❌ Removed `variant_value` (single text field)
- ❌ Removed `sku` (not needed at variant level)
- ❌ Removed `stock_quantity` (moved to product_stocks)

### 2. `product_prices` Table (Updated)

```sql
- id
- product_id (FK)
- variant_id (nullable FK to product_variants)
- variant_value (VARCHAR) - Specific value like "5" or "Red"
- pricing_mode (ENUM: single/variant)
- supplier_price, retail_price, wholesale_price, distributor_price, discount_price
- created_at, updated_at
```

**Key Changes:**

- ✅ Added `variant_value` column to store specific value
- ✅ One price record per product-variant-value combination

### 3. `product_stocks` Table (Updated)

```sql
- id
- product_id (FK)
- variant_id (nullable FK to product_variants)
- available_stock, damage_stock, total_stock
- sold_count, restocked_quantity
- created_at, updated_at
```

**Key Changes:**

- ✅ Added `variant_id` to track stock per variant value
- ✅ Each variant value has its own stock record

## How It Works

### Example: Shoe Product with Size Variants

#### Step 1: Create Product Variant

```php
ProductVariant::create([
    'product_id' => 1,
    'variant_name' => 'Size',
    'variant_values' => ['5', '6', '7', '8', '9'], // JSON array
    'status' => 'active'
]);
```

#### Step 2: Create Prices for Each Value

```php
foreach (['5', '6', '7', '8', '9'] as $size) {
    ProductPrice::create([
        'product_id' => 1,
        'variant_id' => $variantId,
        'variant_value' => $size, // Specific size
        'pricing_mode' => 'variant',
        'supplier_price' => 1000,
        'retail_price' => 2000,
        'wholesale_price' => 1500,
        'distributor_price' => 1700,
    ]);
}
```

#### Step 3: Create Stock for Each Value

```php
foreach (['5', '6', '7', '8', '9'] as $size) {
    ProductStock::create([
        'product_id' => 1,
        'variant_id' => $variantId,
        'available_stock' => 20,
        'damage_stock' => 0,
        'total_stock' => 20,
    ]);
}
```

## UI Flow

### Creating a Product with Variants

**Step 1: Enter Variant Name**

```
Variant Name: Size
```

**Step 2: Add Values**

```
[5] [6] [7] [8] [9]
```

Each value is added by typing and clicking "Add Value" or pressing Enter.

**Step 3: Set Prices for Each Value**

```
Table view with columns:
Value | Cost | Retail | Wholesale | Distributor | Stock
  5   | 1000 | 2000  |   1500    |    1700     |  20
  6   | 1000 | 2000  |   1500    |    1700     |  25
  7   | 1000 | 2000  |   1500    |    1700     |  30
  8   | 1000 | 2000  |   1500    |    1700     |  25
  9   | 1000 | 2000  |   1500    |    1700     |  20
```

## Migration Files

### File 1: `2026_01_22_000001_add_distributor_price_to_product_prices_table.php`

Adds `distributor_price` column.

### File 2: `2026_01_22_000002_create_product_variants_table.php`

Creates variant table with JSON values storage.

### File 3: `2026_01_22_000003_add_variant_id_to_product_prices_table.php`

Adds `variant_id` and `variant_value` to prices.

### File 4: `2026_01_22_000004_add_variant_id_to_product_stocks_table.php`

Adds `variant_id` to stocks table.

## Benefits of JSON-Based Approach

### ✅ Advantages

1. **Cleaner Structure**: One variant record instead of multiple
2. **Easier Management**: All values in one place
3. **Better Performance**: Fewer database records
4. **Flexibility**: Easy to add/remove values
5. **Clarity**: Clear separation between variant type and values

### Example Comparison

**Old Approach:**

```
Variant 1: variant_name="Size", variant_value="5"
Variant 2: variant_name="Size", variant_value="6"
Variant 3: variant_name="Size", variant_value="7"
```

**New Approach:**

```
Variant 1: variant_name="Size", variant_values=["5", "6", "7"]
```

## Model Relationships

```
ProductDetail (1)
    ├── ProductVariant (many)
    │       ├── variant_name: "Size"
    │       └── variant_values: ["5", "6", "7", "8"]
    │
    ├── ProductPrice (many)
    │       ├── variant_id: 1, variant_value: "5"
    │       ├── variant_id: 1, variant_value: "6"
    │       └── ... (one per value)
    │
    └── ProductStock (many)
            ├── variant_id: 1 (for "Size:5")
            ├── variant_id: 1 (for "Size:6")
            └── ... (one per value)
```

## Livewire Component Properties

```php
public $pricing_mode = 'single';
public $variant_name = ''; // e.g., "Size"
public $variant_values = []; // e.g., ["5", "6", "7"]
public $variant_value_input = ''; // Input field for adding new value
public $variant_prices = []; // Associative array: ['5' => [...], '6' => [...]]
```

## Usage Examples

### Example 1: T-Shirt with Size Variants

```
Variant Name: Size
Values: ["S", "M", "L", "XL", "XXL"]

Prices:
- S:   Cost=400, Retail=1000, Wholesale=700,  Distributor=800,  Stock=50
- M:   Cost=400, Retail=1000, Wholesale=700,  Distributor=800,  Stock=75
- L:   Cost=400, Retail=1000, Wholesale=700,  Distributor=800,  Stock=60
- XL:  Cost=450, Retail=1100, Wholesale=750,  Distributor=850,  Stock=40
- XXL: Cost=500, Retail=1200, Wholesale=800,  Distributor=900,  Stock=30
```

### Example 2: Paint with Color Variants

```
Variant Name: Color
Values: ["White", "Black", "Red", "Blue", "Green"]

Prices:
- White: Cost=200, Retail=500, Wholesale=350, Distributor=400, Stock=100
- Black: Cost=200, Retail=500, Wholesale=350, Distributor=400, Stock=80
- Red:   Cost=250, Retail=600, Wholesale=400, Distributor=450, Stock=60
- Blue:  Cost=250, Retail=600, Wholesale=400, Distributor=450, Stock=60
- Green: Cost=250, Retail=600, Wholesale=400, Distributor=450, Stock=50
```

### Example 3: Mobile Phone with Storage Variants

```
Variant Name: Storage
Values: ["64GB", "128GB", "256GB", "512GB"]

Prices:
- 64GB:  Cost=30000, Retail=50000, Wholesale=42000, Distributor=45000, Stock=20
- 128GB: Cost=35000, Retail=60000, Wholesale=50000, Distributor=54000, Stock=25
- 256GB: Cost=42000, Retail=75000, Wholesale=63000, Distributor=68000, Stock=15
- 512GB: Cost=55000, Retail=95000, Wholesale=80000, Distributor=86000, Stock=10
```

## Querying Data

### Get All Variants for a Product

```php
$variants = ProductVariant::where('product_id', $productId)
    ->where('status', 'active')
    ->get();

foreach ($variants as $variant) {
    echo $variant->variant_name; // "Size"
    print_r($variant->variant_values); // ["5", "6", "7", "8"]
}
```

### Get Prices for a Specific Variant Value

```php
$price = ProductPrice::where('product_id', $productId)
    ->where('variant_id', $variantId)
    ->where('variant_value', '5')
    ->first();

echo $price->retail_price; // 2000
```

### Get Stock for a Specific Variant Value

```php
$stock = ProductStock::where('product_id', $productId)
    ->where('variant_id', $variantId)
    ->first();

echo $stock->available_stock; // 20
```

## Validation Rules

```php
// Variant mode validation
'variant_name' => 'required|string|max:100',
'variant_values' => 'required|array|min:1',
'variant_prices' => 'required|array|min:1',

// For each value
'variant_prices.{value}.supplier_price' => 'required|numeric|min:0',
'variant_prices.{value}.retail_price' => 'required|numeric|min:0',
'variant_prices.{value}.wholesale_price' => 'required|numeric|min:0',
'variant_prices.{value}.distributor_price' => 'nullable|numeric|min:0',
'variant_prices.{value}.stock' => 'required|integer|min:0',
```

## Running Migrations

```bash
cd "c:\Users\ABC\Desktop\WebXkey Project\Hardmen"
php artisan migrate
```

**Expected Output:**

```
Migrating: 2026_01_22_000001_add_distributor_price_to_product_prices_table
Migrated:  2026_01_22_000001_add_distributor_price_to_product_prices_table

Migrating: 2026_01_22_000002_create_product_variants_table
Migrated:  2026_01_22_000002_create_product_variants_table

Migrating: 2026_01_22_000003_add_variant_id_to_product_prices_table
Migrated:  2026_01_22_000003_add_variant_id_to_product_prices_table

Migrating: 2026_01_22_000004_add_variant_id_to_product_stocks_table
Migrated:  2026_01_22_000004_add_variant_id_to_product_stocks_table
```

## Testing Checklist

- [ ] Run all 4 migrations successfully
- [ ] Create product with single pricing (verify it still works)
- [ ] Create variant with name "Size" and values [5, 6, 7, 8]
- [ ] Set different prices for each size
- [ ] Set different stock for each size
- [ ] Verify JSON is stored correctly in database
- [ ] Check that all 4 price tiers save properly
- [ ] Verify stock is tracked separately per value
- [ ] Test removing a variant value
- [ ] Test adding more values after initial creation

## Troubleshooting

### Issue: Variant values not saving as JSON

**Check:** Ensure model has `'variant_values' => 'array'` in `$casts`

### Issue: Can't add prices for variant values

**Check:** Ensure `variant_value` column exists in `product_prices` table

### Issue: Stock not tracking per variant

**Check:** Ensure `variant_id` column exists in `product_stocks` table

---

**This new JSON-based approach provides a cleaner, more efficient way to manage product variants!**
