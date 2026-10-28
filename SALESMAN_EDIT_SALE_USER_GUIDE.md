# Salesman Edit Sale - User Guide

## How It Works

### Step 1: Navigate to My Sales

- Click on "My Sales" in the salesman portal
- You'll see all your sales orders with their status

### Step 2: Find a Pending Sale

- Look for sales with the **"Pending"** badge (yellow color)
- These are sales awaiting admin approval

### Step 3: Click Edit

- In the Actions column on the right side, click the **pencil icon** (✏️)
- The system will load the billing page with all your sale data

### Step 4: Edit Mode Activated

When you're editing, you'll see:

- **Yellow warning banner** at the top saying "Editing Mode"
- **Sale ID** displayed in the banner
- **Cancel Edit button** to exit without saving
- All your previous data filled in

### Step 5: Make Changes

You can modify:

- ✏️ Customer details (change to different customer)
- ➕ Add more products
- ❌ Remove products
- 🔢 Adjust quantities
- 💰 Change discounts per item
- 📝 Update sale notes
- 💸 Adjust total discount percentage/amount

### Step 6: Save Changes

- Click the **"Save Changes"** button (blue, with floppy disk icon 💾)
- The system updates your sale in the database
- You'll see a success message

### Step 7: Return to Sales List

- You can click "New Sale" to create another one
- Or close the modal to go back to your sales list

## Important Notes

⚠️ **Can Only Edit Pending Sales**

- Once admin approves your sale, you cannot edit it anymore
- If you need to change an approved sale, contact your admin

⚠️ **Changes Are Final**

- When you click "Save Changes", the sale is immediately updated
- Any previous data is overwritten
- There's no "undo" button - be careful!

⚠️ **Stock Considerations**

- The system deducts pending orders from available stock
- If you reduce quantity in edit mode, stock becomes available again
- If you increase quantity, it will be deducted from available stock

## What Cannot Be Changed When Editing

❌ Sale ID (auto-generated, stays same)
❌ Invoice Number (stays same)  
❌ Creation Date (stays same)
❌ Admin approval status (handled by admin)

## Benefits of Edit Feature

✅ **Fix Mistakes** - Correct errors before admin approval
✅ **Update Quantities** - Adjust order amounts as needed
✅ **Change Customer** - Switch to correct customer if wrong one selected
✅ **Modify Discounts** - Adjust pricing before approval
✅ **Add/Remove Items** - Change product selection
✅ **Edit Notes** - Add special instructions or requirements

## Common Scenarios

### Scenario 1: Wrong Quantity Added

1. Go to My Sales
2. Find the pending sale
3. Click pencil icon to edit
4. Change the quantity
5. Click "Save Changes"
   ✅ Done! Sale is updated

### Scenario 2: Added Wrong Product

1. Go to My Sales
2. Find the pending sale
3. Click pencil icon to edit
4. Click the trash icon (🗑️) to remove the wrong product
5. Search and add the correct product
6. Click "Save Changes"
   ✅ Done! Sale now has correct product

### Scenario 3: Selected Wrong Customer

1. Go to My Sales
2. Find the pending sale
3. Click pencil icon to edit
4. Change the customer from the dropdown
5. Click "Save Changes"
   ✅ Done! Sale is assigned to correct customer

### Scenario 4: Need to Cancel Editing

1. While editing, click "Cancel Edit" button in the yellow banner
2. Or simply go back using browser back button
3. Your changes won't be saved
   ✅ You're back to the sales list without changes

## Troubleshooting

**Problem:** Can't find edit button

- **Solution:** Make sure the sale status is "Pending" (yellow badge)
- Approved sales (green) cannot be edited

**Problem:** Got error "Only pending sales can be edited"

- **Solution:** Admin has already approved the sale
- You can no longer edit it
- Contact admin if changes are needed

**Problem:** Products won't search

- **Solution:** Type product name or code at least 2 characters
- Wait for dropdown to appear
- Click on the product to add it

**Problem:** Stock showing as 0

- **Solution:** Other pending orders may have reserved that stock
- Try reducing quantity if possible
- Or contact admin/inventory team

## Quick Tips

💡 **Pro Tips:**

1. Use "Pending" filter to see only sales that can be edited
2. Edit immediately after creating if you noticed a mistake
3. Always verify customer name before saving
4. Check quantities match customer's request
5. Review discounts are correct before final save

---

**Last Updated:** 2026-02-03
**Status:** ✅ Ready to Use
