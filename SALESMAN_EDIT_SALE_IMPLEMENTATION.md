# Salesman Invoice Edit Implementation

## Overview

This implementation adds the ability for salesmen to edit their pending invoices/sales orders. When a salesman clicks the edit button on a pending sale from their sales list, they are redirected to the billing page where they can make changes to the invoice details.

## Files Modified

### 1. **routes/web.php**

Added new route for editing sales:

```php
Route::get('/billing/{saleId}/edit', SalesmanBilling::class)->name('billing.edit');
```

**Route Details:**

- URL: `/salesman/billing/{saleId}/edit`
- Route Name: `salesman.billing.edit`
- Component: `SalesmanBilling` (same component handles both create and edit)

### 2. **app/Livewire/Salesman/SalesmanBilling.php**

#### Added Properties:

```php
// Edit Mode
public $editMode = false;
public $editingSaleId = null;
public $editingSale = null;
```

#### Updated mount() Method:

- Now accepts optional `$saleId` parameter
- If `$saleId` is provided, calls `loadSaleForEditing()` to load the existing sale

#### New Method: loadSaleForEditing($saleId)

- Fetches the sale from database with customer and items
- Only allows editing of `pending` or `draft` status sales
- Populates the form with existing sale data:
    - Customer information
    - Cart items from sale_items
    - Notes and discounts
- Sets `editMode = true` to trigger UI changes

#### Updated createSale() Method:

- Now checks if `editMode && editingSaleId` is true
- If editing:
    - Updates existing sale record
    - Deletes old sale items
    - Creates new sale items with updated data
    - Shows success message "Sale order updated successfully!"
- If creating new:
    - Generates new sale_id and invoice_number
    - Creates new sale record with status = 'pending'
    - Shows success message "Sale order created successfully! Pending admin approval."

#### Updated createNewSale() Method:

- Also resets edit mode flags:
    - `editMode = false`
    - `editingSaleId = null`
    - `editingSale = null`

#### New Method: cancelEdit()

- Clears the cart and edit mode
- Redirects back to sales list page

### 3. **resources/views/livewire/salesman/salesman-billing.blade.php**

#### Added Edit Mode Alert (at top):

```blade
@if($editMode && $editingSaleId)
<div class="alert alert-warning alert-dismissible fade show mb-4">
    <i class="bi bi-pencil-square me-2"></i>
    <strong>Editing Mode:</strong> You are editing sale <strong>#{{ $editingSale?->sale_id }}</strong>
    <button class="btn btn-sm btn-warning ms-2" wire:click="cancelEdit">Cancel Edit</button>
</div>
@endif
```

- Shows a warning banner when in edit mode
- Displays the sale ID being edited
- Provides a cancel button to exit edit mode

#### Updated Submit Button:

```blade
@if($editMode && $editingSaleId)
    <button class="btn btn-primary btn-lg px-5" wire:click="createSale"
        {{ count($cart) == 0 || !$customerId ? 'disabled' : '' }}>
        <i class="bi bi-floppy me-2"></i>Save Changes
    </button>
@else
    <button class="btn btn-success btn-lg px-5" wire:click="createSale"
        {{ count($cart) == 0 || !$customerId ? 'disabled' : '' }}>
        <i class="bi bi-cart-check me-2"></i>Complete Sale Order
    </button>
@endif
```

- Changes button color and text based on mode
- Edit mode: Blue button with "Save Changes" text
- Create mode: Green button with "Complete Sale Order" text

#### Updated Helper Text:

```blade
<small class="text-muted">
    @if($editMode && $editingSaleId)
        Changes will be saved to sale #{{ $editingSale?->sale_id }}
    @else
        Sale will be sent for admin approval
    @endif
</small>
```

### 4. **resources/views/livewire/salesman/salesman-sales-list.blade.php**

#### Updated Edit Button:

Changed from calling a modal to redirecting to the edit route:

```blade
@if($sale->status === 'pending')
<a href="{{ route('salesman.billing.edit', $sale->id) }}" class="btn btn-sm btn-outline-warning" title="Edit Sale">
    <i class="bi bi-pencil"></i>
</a>
@endif
```

**Benefits:**

- Salesmen can only edit pending sales (before admin approval)
- Clicking the pencil icon redirects to billing page with sale data pre-loaded
- UI remains clean and intuitive

## User Workflow

### Editing a Sale:

1. Salesman navigates to "My Sales" page
2. Finds a sale with "Pending" status
3. Clicks the pencil (edit) icon
4. Gets redirected to `/salesman/billing/{saleId}/edit`
5. Sees:
    - Yellow warning banner showing "Editing Mode"
    - All fields populated with existing sale data
    - Cart populated with existing sale items
    - Submit button changes to "Save Changes"
6. Makes desired changes:
    - Modify quantities
    - Adjust discounts
    - Change customer (if needed)
    - Update notes
    - Add/remove products
7. Clicks "Save Changes"
8. Sale is updated in database
9. Sees success message and can continue to another sale or go back to sales list

### Canceling Edit:

1. While editing, salesman sees "Cancel Edit" button in the warning banner
2. Clicks "Cancel Edit"
3. Gets redirected back to sales list without saving changes
4. Or clicks browser back button (both work)

## Database Changes

**No database schema changes required** - Uses existing:

- `sales` table
- `sale_items` table
- `customers` table

## Key Features

✅ **Edit Validation**

- Only pending/draft sales can be edited
- Admin-approved sales cannot be edited (prevents tampering)

✅ **Smart UI**

- Edit mode clearly indicated with warning banner
- Button text and color change dynamically
- Cancel option available at any time

✅ **Data Integrity**

- Uses database transactions
- Old items deleted, new items created atomically
- Sale ID and invoice number preserved

✅ **User Experience**

- Same billing interface for both create and edit
- All existing features work (product search, variants, discounts, etc.)
- Familiar workflow

## Testing Checklist

- [ ] Create a new sale and verify it works as before
- [ ] Go to "My Sales" and click edit button on a pending sale
- [ ] Verify sale data loads correctly
- [ ] Change product quantities and save
- [ ] Change customer and save
- [ ] Adjust discounts and save
- [ ] Click "Cancel Edit" and verify it doesn't save
- [ ] Try to edit an approved sale (should show error)
- [ ] Verify success messages appear appropriately

## Security Considerations

✅ **Implemented:**

- Salesmen can only edit their own sales (via auth context)
- Cannot edit approved/rejected sales (status check)
- Uses Livewire's built-in CSRF protection
- Requires `staff_type:salesman` middleware

## Future Enhancements

Potential improvements:

1. Add audit trail showing edit history
2. Notify admin when sale is edited
3. Allow editing approved sales with admin override
4. Show "Last edited" timestamp on sales list
5. Add email notification to customer about order changes
