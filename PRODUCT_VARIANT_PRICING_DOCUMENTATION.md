# Product Variant and Distributor Pricing Implementation

## Overview

This implementation adds comprehensive variant-based pricing support to the product management system, along with a new distributor price tier.

## Database Changes

### 1. New `distributor_price` Column

- **Table**: `product_prices`
- **Type**: `decimal(10, 2)`
- **Nullable**: Yes
- **Description**: Stores the distributor-level pricing for products

### 2. New `product_variants` Table

- **Purpose**: Store product variants (e.g., size, color, material)
- **Columns**:
    - `id`: Primary key
    - `product_id`: Foreign key to `product_details`
    - `variant_name`: Name of the variant type (e.g., "Size", "Color")
    - `variant_value`: Specific value (e.g., "Large", "Red")
    - `sku`: Optional unique SKU for the variant
    - `stock_quantity`: Stock available for this variant
    - `status`: Active/Inactive status
    - `created_at`, `updated_at`: Timestamps

### 3. Updated `product_prices` Table

- **New Columns**:
    - `variant_id`: Nullable foreign key to `product_variants`
    - `pricing_mode`: Enum ('single', 'variant') - indicates pricing type
- **Constraint**: Unique combination of `product_id` and `variant_id`

## Models

### ProductVariant Model

New model created at `app/Models/ProductVariant.php`

**Relationships**:

- `product()`: Belongs to ProductDetail
- `prices()`: Has many ProductPrice records
- `price()`: Has one primary ProductPrice

**Scopes**:

- `active()`: Returns only active variants

**Attributes**:

- `full_name`: Formatted variant display (e.g., "Size: Large")

### Updated ProductPrice Model

**New Fields**:

- `distributor_price`: Distributor pricing tier
- `variant_id`: Links to specific variant (null for single pricing)
- `pricing_mode`: Tracks if product uses single or variant-based pricing

**New Methods**:

- `isVariantBased()`: Check if price is variant-specific
- `isSinglePrice()`: Check if price is for entire product

**Relationships**:

- `variant()`: Belongs to ProductVariant

### Updated ProductDetail Model

**New Relationships**:

- `variants()`: Has many ProductVariant records
- `prices()`: Has many ProductPrice records (including variant prices)

**New Methods**:

- `hasVariants()`: Check if product has variants

## Pricing Tiers

The system now supports 4 price tiers:

1. **Cost Price** (supplier_price): Purchase/manufacturing cost
2. **Wholesale Price**: For bulk buyers
3. **Distributor Price**: For distributors/resellers (NEW)
4. **Retail Price**: For end customers

## UI Changes

### Add Product Modal

#### Pricing Mode Selection

Users can choose between two pricing modes:

- **Single Price**: One price set applies to all units
- **Variant-Based Pricing**: Different prices per variant

#### Single Price Mode

When selected, displays 4 price inputs:

- Cost Price
- Retail Price
- Wholesale Price
- Distributor Price (NEW)
- Stock fields (Available Stock, Damage Stock)

#### Variant-Based Pricing Mode

When selected:

- Shows variant management interface
- Each variant has:
    - Variant Name (e.g., "Size")
    - Variant Value (e.g., "Large")
    - SKU (optional)
    - All 4 price tiers
    - Individual stock quantity
    - Status (Active/Inactive)
- "Add Another Variant" button to create multiple variants
- "Remove" button for variants (except the first one)

### Product List Table

Updated to display 4 price columns:

- Cost Price
- Wholesale
- Distributor (NEW)
- Retail

## Backend Logic

### Livewire Component Updates (`Products.php`)

#### New Properties

```php
public $pricing_mode = 'single';
public $distributor_price;
public $variants = [];
```

#### New Methods

- `initializeVariants()`: Sets up default variant structure
- `addVariant()`: Adds new variant to the array
- `removeVariant($index)`: Removes variant from array

#### Updated Validation

- Dynamic validation rules based on `pricing_mode`
- Single mode: Validates individual price fields
- Variant mode: Validates array of variants with nested rules

#### Updated `createProduct()` Method

Handles two flows:

**Single Price Mode**:

1. Creates ProductDetail
2. Creates one ProductPrice with `pricing_mode='single'` and `variant_id=null`
3. Creates ProductStock with specified stock

**Variant-Based Mode**:

1. Creates ProductDetail
2. For each variant:
    - Creates ProductVariant record
    - Creates ProductPrice with `pricing_mode='variant'` and `variant_id` set
3. Creates ProductStock with total stock from all variants

## Migration Files

### Migration 1: Add Distributor Price

**File**: `2026_01_22_000001_add_distributor_price_to_product_prices_table.php`

```bash
php artisan migrate
```

### Migration 2: Create Product Variants Table

**File**: `2026_01_22_000002_create_product_variants_table.php`

### Migration 3: Add Variant Support to Prices

**File**: `2026_01_22_000003_add_variant_id_to_product_prices_table.php`

## Usage Examples

### Creating a Product with Single Pricing

1. Open Add Product modal
2. Fill in product details
3. Select "Single Price" mode
4. Enter all 4 price tiers
5. Set stock quantities
6. Click "Save Product"

### Creating a Product with Variants

1. Open Add Product modal
2. Fill in product details
3. Select "Variant-Based Pricing" mode
4. For each variant:
    - Enter variant name (e.g., "Size")
    - Enter variant value (e.g., "Small", "Medium", "Large")
    - Set all 4 price tiers
    - Set stock quantity
5. Click "Add Another Variant" to add more
6. Click "Save Product"

### Example: T-Shirt with Size Variants

```
Product: Premium Cotton T-Shirt
Code: TSHIRT-001

Variant 1:
- Name: Size
- Value: Small
- Cost: Rs. 500
- Wholesale: Rs. 800
- Distributor: Rs. 900
- Retail: Rs. 1200
- Stock: 50

Variant 2:
- Name: Size
- Value: Medium
- Cost: Rs. 500
- Wholesale: Rs. 800
- Distributor: Rs. 900
- Retail: Rs. 1200
- Stock: 75

Variant 3:
- Name: Size
- Value: Large
- Cost: Rs. 550
- Wholesale: Rs. 850
- Distributor: Rs. 950
- Retail: Rs. 1300
- Stock: 60
```

## Database Relationships

```
product_details (1) ────┬──── (many) product_variants
                        │                   │
                        │                   │ (1)
                        │                   │
                        └──── (many) product_prices ──── (many)
                                      │
                                      └─ variant_id (nullable)
```

## Backward Compatibility

- Existing products without variants continue to work
- Old products have `pricing_mode='single'` and `variant_id=null`
- New `distributor_price` is nullable, so existing records are unaffected

## Validation Rules

### Single Price Mode

- `supplier_price`: Required, numeric, >= 0
- `retail_price`: Required, numeric, >= supplier_price
- `wholesale_price`: Required, numeric, >= supplier_price
- `distributor_price`: Optional, numeric, >= supplier_price
- `available_stock`: Required, integer, >= 0

### Variant Mode

- `variants`: Required array, minimum 1 variant
- Each variant:
    - `name`: Required, max 100 characters
    - `value`: Required, max 100 characters
    - `sku`: Optional, unique, max 100 characters
    - `supplier_price`: Required, numeric, >= 0
    - `retail_price`: Required, numeric, >= 0
    - `wholesale_price`: Required, numeric, >= 0
    - `distributor_price`: Optional, numeric, >= 0
    - `stock`: Required, integer, >= 0
    - `status`: Required, 'active' or 'inactive'

## Future Enhancements

Potential features to add:

1. Bulk variant creation (e.g., generate all size combinations)
2. Variant images
3. Variant-specific barcodes
4. Inventory tracking by variant
5. Sales reporting by variant
6. Variant import/export in Excel
7. Edit existing product variants
8. Variant history tracking

## Troubleshooting

### Issue: Prices not showing after migration

**Solution**: Run migrations in order:

```bash
php artisan migrate
```

### Issue: Variant data not saving

**Solution**: Check validation errors in browser console and ensure all required fields are filled

### Issue: Unique constraint violation on SKU

**Solution**: Ensure SKUs are unique across all variants, or leave blank

## Testing Checklist

- [ ] Create product with single pricing
- [ ] Create product with one variant
- [ ] Create product with multiple variants
- [ ] Verify distributor price saves correctly
- [ ] Check product list displays all 4 prices
- [ ] Verify stock calculations for variants
- [ ] Test variant add/remove functionality
- [ ] Validate price hierarchy (retail >= distributor >= wholesale >= cost)
- [ ] Test with empty/optional fields
- [ ] Verify database constraints

## Files Modified

1. **Migrations** (3 new files):
    - `2026_01_22_000001_add_distributor_price_to_product_prices_table.php`
    - `2026_01_22_000002_create_product_variants_table.php`
    - `2026_01_22_000003_add_variant_id_to_product_prices_table.php`

2. **Models**:
    - `app/Models/ProductVariant.php` (NEW)
    - `app/Models/ProductPrice.php` (Updated)
    - `app/Models/ProductDetail.php` (Updated)

3. **Controllers**:
    - `app/Livewire/Admin/Products.php` (Updated)

4. **Views**:
    - `resources/views/livewire/admin/Productes.blade.php` (Updated)

## Support

For questions or issues, please refer to the development team or check the Laravel documentation for relationships and migrations.
