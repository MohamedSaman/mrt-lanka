# Customer Balance Management Implementation

## ✅ Completed Changes

### 1. Database Migration

- **File**: `database/migrations/2026_02_05_000002_add_balance_columns_to_customers_table.php`
- **New Columns Added**:
    - `opening_balance` (decimal 12,2): Initial debt/credit the customer has
    - `due_amount` (decimal 12,2): Accumulated debt from credit sales
    - `total_due` (decimal 12,2): Sum of opening_balance + due_amount
    - `overpaid_amount` (decimal 12,2): Advance payments/credits from customer

### 2. Customer Model Updates

- **File**: `app/Models/Customer.php`
- Added new fields to `$fillable` array
- Added `$casts` for decimal precision (2 decimal places)

### 3. ManageCustomer Component Updates

- **File**: `app/Livewire/Admin/ManageCustomer.php`
- Added properties for balance fields
- Added `showMoreInfo` and `showEditMoreInfo` toggles
- Updated `saveCustomer()` method to save balance data
- Updated `updateCustomer()` method to update balance data and recalculate total_due
- Updated `editCustomer()` method to load balance data and auto-show more info if values exist

### 4. Customer Management View Updates

- **File**: `resources/views/livewire/admin/manage-customer.blade.php`
- Added "More Information" button in create modal
- Added "More Information" button in edit modal
- Added collapsible section with opening_balance and overpaid_amount fields
- Includes helpful text explaining each field

## 📋 Next Steps - StoreBilling Integration

### Required Changes in StoreBilling.php

#### 1. Add Balance Properties

```php
public $customerOpeningBalance = 0;
public $customerDueAmount = 0;
public $customerTotalDue = 0;
public $customerOverpaidAmount = 0;
public $showCustomerMoreInfo = false;
```

#### 2. Update Customer Selection Logic

When a customer is selected, load their balance information:

```php
public function updatedCustomerId($value)
{
    if ($value) {
        $customer = Customer::find($value);
        if ($customer) {
            $this->selectedCustomer = $customer;
            $this->customerOpeningBalance = $customer->opening_balance ?? 0;
            $this->customerDueAmount = $customer->due_amount ?? 0;
            $this->customerTotalDue = $customer->total_due ?? 0;
            $this->customerOverpaidAmount = $customer->overpaid_amount ?? 0;

            // Auto-select price type based on customer type
            if ($customer->type === 'wholesale') {
                $this->priceType = 'wholesale';
            } elseif ($customer->type === 'distributor') {
                $this->priceType = 'distribute';
            } else {
                $this->priceType = 'retail';
            }
        }
    }
}
```

#### 3. Update Create Customer Method in StoreBilling

Add opening_balance and overpaid_amount fields to the customer creation modal.

#### 4. Update createSale() Method - Credit Sale Logic

When payment is "credit", add due amount to customer:

```php
// After creating the sale, if it's credit
if ($this->paymentStatus === 'due') {
    $creditAmount = $this->grandTotal - $this->totalPaidAmount;

    $customer->due_amount += $creditAmount;
    $customer->total_due = $customer->opening_balance + $customer->due_amount;
    $customer->save();
}
```

#### 5. Update Payment Processing Logic

Implement the overpayment distribution logic:

```php
public function completeSaleWithPayment()
{
    // Calculate total received
    $totalReceived = $this->cashAmount +
                    array_sum(array_column($this->cheques, 'amount')) +
                    $this->bankTransferAmount;

    $grandTotal = $this->grandTotal;
    $customer = $this->selectedCustomer;

    // Check if it's an overpayment
    if ($totalReceived > $grandTotal) {
        $excessAmount = $totalReceived - $grandTotal;

        // Step 1: Reduce opening balance first
        if ($customer->opening_balance > 0) {
            if ($excessAmount >= $customer->opening_balance) {
                $excessAmount -= $customer->opening_balance;
                $customer->opening_balance = 0;
            } else {
                $customer->opening_balance -= $excessAmount;
                $excessAmount = 0;
            }
        }

        // Step 2: Reduce due amount
        if ($excessAmount > 0 && $customer->due_amount > 0) {
            if ($excessAmount >= $customer->due_amount) {
                $excessAmount -= $customer->due_amount;
                $customer->due_amount = 0;
            } else {
                $customer->due_amount -= $excessAmount;
                $excessAmount = 0;
            }
        }

        // Step 3: Save remaining as overpaid amount
        if ($excessAmount > 0) {
            $customer->overpaid_amount += $excessAmount;
        }

        // Recalculate total due
        $customer->total_due = $customer->opening_balance + $customer->due_amount;
        $customer->save();
    }

    // Continue with normal sale creation
    $this->createSale();
}
```

#### 6. Consider Overpaid Amount in Sales

When creating a sale, if customer has overpaid_amount, allow using it:

```php
// In payment modal, show available overpaid amount
@if($customerOverpaidAmount > 0)
<div class="alert alert-success">
    <i class="bi bi-wallet2"></i>
    Available Credit: Rs. {{ number_format($customerOverpaidAmount, 2) }}
    <button wire:click="applyOverpaidCredit()" class="btn btn-sm btn-success">
        Use Credit
    </button>
</div>
@endif
```

### View Updates for StoreBilling

#### 1. Customer Creation Modal

Add "More Information" section similar to ManageCustomer component.

#### 2. Cart/Checkout Area

Display customer balance information:

```blade
@if($selectedCustomer && $selectedCustomer->id != 1)
<div class="bg-blue-50 p-3 rounded-lg mb-2">
    <div class="text-sm">
        <div class="flex justify-between">
            <span>Opening Balance:</span>
            <span class="font-bold">Rs. {{ number_format($customerOpeningBalance, 2) }}</span>
        </div>
        <div class="flex justify-between">
            <span>Current Due:</span>
            <span class="font-bold">Rs. {{ number_format($customerDueAmount, 2) }}</span>
        </div>
        <div class="flex justify-between border-t pt-1">
            <span class="font-bold">Total Due:</span>
            <span class="font-bold text-red-600">Rs. {{ number_format($customerTotalDue, 2) }}</span>
        </div>
        @if($customerOverpaidAmount > 0)
        <div class="flex justify-between text-green-600">
            <span>Credit Available:</span>
            <span class="font-bold">Rs. {{ number_format($customerOverpaidAmount, 2) }}</span>
        </div>
        @endif
    </div>
</div>
@endif
```

## Testing Checklist

### Test Cases

1. ✅ Create customer with opening balance
2. ✅ Create customer with overpaid amount
3. ✅ Edit customer and update balances
4. ⏳ Create credit sale and verify due_amount increases
5. ⏳ Make full payment and verify due_amount decreases
6. ⏳ Make overpayment and verify distribution logic:
    - First reduces opening_balance
    - Then reduces due_amount
    - Finally adds to overpaid_amount
7. ⏳ Use overpaid_amount credit in new sale
8. ⏳ Verify total_due calculation accuracy

## Database Structure

```sql
customers table:
- opening_balance: decimal(12,2) DEFAULT 0
- due_amount: decimal(12,2) DEFAULT 0
- total_due: decimal(12,2) DEFAULT 0
- overpaid_amount: decimal(12,2) DEFAULT 0

Calculation:
total_due = opening_balance + due_amount
```

## Business Logic Rules

1. **Opening Balance**: Initial debt customer owes (from previous system/manual entry)
2. **Due Amount**: Accumulated from credit sales
3. **Total Due**: Sum of opening_balance + due_amount (always calculated)
4. **Overpaid Amount**: Advance payments/credits customer has

### Payment Distribution Order (Overpayment):

1. Reduce opening_balance (oldest debt)
2. Reduce due_amount (current debt)
3. Add to overpaid_amount (future credit)

### Credit Sale:

- Add to due_amount
- Recalculate total_due

### Full Payment:

- Reduce due_amount by payment
- Recalculate total_due

## Additional Features to Consider

1. **Payment History**: Track all balance adjustments
2. **Customer Statement**: Generate statement showing all transactions
3. **Balance Alerts**: Warn when customer exceeds credit limit
4. **Bulk Import**: Import customer opening balances from Excel
5. **Reports**: Customer balance summary report

## Migration Command

```bash
php artisan migrate
```

Already executed successfully! ✅

## Files Modified

1. `database/migrations/2026_02_05_000002_add_balance_columns_to_customers_table.php` (NEW)
2. `app/Models/Customer.php` (UPDATED)
3. `app/Livewire/Admin/ManageCustomer.php` (UPDATED)
4. `resources/views/livewire/admin/manage-customer.blade.php` (UPDATED)

## Files Needing Updates

1. `app/Livewire/Admin/StoreBilling.php` (PENDING)
2. `resources/views/livewire/admin/store-billing.blade.php` (PENDING)
