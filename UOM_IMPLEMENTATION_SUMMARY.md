# UOM Normalization Implementation Summary

## Overview
This document summarizes the UOM (Unit of Measure) normalization refactor implemented for the Procurement and Inventory plugins. The refactor centralizes UOM management in the Organization plugin and ensures all inventory quantities are stored in base units for data integrity.

## Changes Made

### 1. Database Migrations

#### Procurement Plugin
**File**: `plugins/omsb/procurement/updates/add_uom_fields_to_purchaseable_items.php`

Added fields to `omsb_procurement_purchaseable_items`:
- `base_uom_id` - Base UOM for normalization (references Organization plugin)
- `purchase_uom_id` - Preferred purchase UOM
- Foreign keys to `omsb_organization_unit_of_measures`
- Indexes for performance

#### Inventory Plugin
**File**: `plugins/omsb/inventory/updates/add_base_uom_to_warehouse_items.php`

Added fields to `omsb_inventory_warehouse_items`:
- `base_uom_id` - Base UOM for quantity_on_hand (ALWAYS in base units)
- `display_uom_id` - Warehouse preference for displaying quantities
- Foreign keys to `omsb_organization_unit_of_measures`

**File**: `plugins/omsb/inventory/updates/add_uom_audit_to_inventory_ledger.php`

Added fields to `omsb_inventory_inventory_ledgers`:
- `base_uom_id` - Base UOM (matches WarehouseItem)
- `original_transaction_uom_id` - Original UOM used in transaction (audit trail)
- `original_transaction_quantity` - Original quantity in transaction UOM
- Foreign keys to `omsb_organization_unit_of_measures`

### 2. Model Updates

#### PurchaseableItem Model
- Added `base_uom_id` and `purchase_uom_id` to fillable fields
- Added to nullable array for proper empty string handling
- Added validation rules for UOM fields
- Added relationships: `base_uom`, `purchase_uom`
- Added dropdown option methods: `getBaseUomIdOptions()`, `getPurchaseUomIdOptions()`

#### WarehouseItem Model
- Added `base_uom_id` and `display_uom_id` to fillable fields
- Added to nullable array
- Updated validation rules
- Added relationships: `base_uom`, `display_uom`
- Added dropdown option methods: `getBaseUomIdOptions()`, `getDisplayUomIdOptions()`
- **Critical**: `quantity_on_hand` is ALWAYS stored in base UOM

#### InventoryLedger Model
- Added `base_uom_id`, `original_transaction_uom_id`, `original_transaction_quantity` to fillable fields
- Added to nullable array
- Updated validation rules
- Added relationships: `base_uom`, `original_transaction_uom`
- **Critical**: `quantity_change`, `quantity_before`, `quantity_after` are ALWAYS in base UOM

### 3. Test Suite

Created comprehensive test suites following OctoberCMS PluginTestCase patterns:

#### UOMNormalizationServiceTest (20+ test cases)
- Basic normalization to base UOM
- Denormalization from base to target UOM
- Multi-UOM normalization (physical count scenario)
- Breakdown quantity into multiple UOMs
- UOM conversion between compatible UOMs
- Conversion factor calculation
- UOM compatibility checking
- Incompatible conversion exception handling
- Quantity formatting

#### PurchaseableItemTest (15 test cases)
- Creating items with UOM fields
- UOM relationships loading
- Nullable UOM fields for non-inventory items
- UOM validation
- Dropdown options for UOM selection
- Inventory item flag immutability
- Full display attribute with UOM

#### WarehouseItemTest (13 test cases)
- Creating warehouse items with base UOM
- Base UOM relationship loading
- Quantity always stored in base UOM
- Warehouse-item uniqueness constraint
- Available quantity calculation
- Adjust quantity method
- Prevent negative stock
- Below minimum stock level detection
- Reserve quantity functionality

#### InventoryLedgerTest (11 test cases)
- Creating ledger entries with base UOM
- Ledger updates warehouse quantity
- Transaction in different UOM with audit trail
- Ledger entry immutability
- Locked ledger prevents modification
- Cost calculation
- Relationship loading
- Direction attribute (IN/OUT)

## How It Works

### Key Principle: Base UOM Normalization

All quantities are internally stored in **base units** for data integrity:

1. **PurchaseableItem**: Stores `base_uom_id` (for inventory normalization) and `purchase_uom_id` (for display/preference)

2. **WarehouseItem**: 
   - `quantity_on_hand` is ALWAYS stored in base UOM
   - `base_uom_id` defines which UOM is the base
   - `display_uom_id` is for user preference only

3. **InventoryLedger**: 
   - `quantity_change`, `quantity_before`, `quantity_after` are ALWAYS in base UOM
   - `original_transaction_uom_id` and `original_transaction_quantity` preserve the original transaction details for audit trail

### Example Scenario

**Purchasing 2 boxes of tissue paper:**

1. PurchaseableItem has:
   - `base_uom_id` = ROLL (base unit)
   - `purchase_uom_id` = BOX (preferred for purchasing)

2. When receiving goods:
   - User enters: 2 BOX
   - System normalizes: 2 BOX × 72 = 144 ROLL
   - Stores in WarehouseItem: `quantity_on_hand = 144` (in ROLL)
   
3. InventoryLedger records:
   - `base_uom_id` = ROLL
   - `quantity_change` = 144 (in ROLL)
   - `original_transaction_uom_id` = BOX
   - `original_transaction_quantity` = 2 (audit trail)

4. Display to user:
   - Can show "2 BOX" or "144 ROLL" or "24 PACK6" based on preference
   - Conversion happens at runtime using UOMNormalizationService

## Integration Guide

### For Controller Integration

When saving records that involve quantities:

```php
use Omsb\Organization\Services\UOMNormalizationService;

public function onSave()
{
    $service = new UOMNormalizationService();
    
    // Get user input
    $quantity = post('quantity'); // e.g., 2
    $uomId = post('uom_id'); // e.g., BOX
    
    // Normalize to base UOM
    $normalized = $service->normalize($quantity, $uomId);
    
    // Save to database in base UOM
    $warehouseItem->quantity_on_hand += $normalized['base_quantity']; // 144
    $warehouseItem->save();
    
    // Create ledger entry
    InventoryLedger::createEntry([
        'warehouse_item_id' => $warehouseItem->id,
        'base_uom_id' => $normalized['base_uom']->id,
        'quantity_change' => $normalized['base_quantity'], // 144
        'original_transaction_uom_id' => $uomId, // BOX
        'original_transaction_quantity' => $quantity, // 2
        // ... other fields
    ]);
}
```

### For View Integration

In forms, add UOM dropdown selectors:

```yaml
# fields.yaml
base_uom_id:
    label: Base Unit of Measure
    type: dropdown
    span: left
    comment: Base UOM for quantity normalization

purchase_uom_id:
    label: Purchase UOM
    type: dropdown
    span: right
    comment: Preferred UOM for purchasing
```

In list views, display quantities with UOM conversion:

```php
// In controller
public function listExtendQuery($query)
{
    $query->with(['base_uom', 'display_uom']);
}

// In partial/view
{% set service = create_service('Omsb\\Organization\\Services\\UOMNormalizationService') %}
{% for item in records %}
    {% if item.display_uom %}
        {% set displayQty = service.denormalize(
            item.quantity_on_hand, 
            item.base_uom, 
            item.display_uom
        ) %}
        {{ displayQty|number_format(2) }} {{ item.display_uom.code }}
    {% else %}
        {{ item.quantity_on_hand|number_format(2) }} {{ item.base_uom.code }}
    {% endif %}
{% endfor %}
```

## Migration Steps

### Running Migrations

```bash
# Run migrations
php artisan october:migrate

# Refresh specific plugin (development only - destroys data!)
php artisan plugin:refresh Omsb.Procurement
php artisan plugin:refresh Omsb.Inventory
```

### Data Migration (if existing data)

After running schema migrations, you'll need to populate the new UOM fields:

```php
// For existing PurchaseableItem records
PurchaseableItem::chunk(100, function ($items) use ($service) {
    foreach ($items as $item) {
        // Map old string UOM to new UOM model
        $uom = UnitOfMeasure::where('code', $item->unit_of_measure)->first();
        if ($uom) {
            $item->base_uom_id = $uom->getUltimateBaseUom()->id;
            $item->purchase_uom_id = $uom->id;
            $item->save();
        }
    }
});

// For existing WarehouseItem records
WarehouseItem::whereNull('base_uom_id')->chunk(100, function ($items) {
    foreach ($items as $item) {
        $item->base_uom_id = $item->purchaseable_item->base_uom_id;
        $item->save();
    }
});
```

## Testing

Run the test suite:

```bash
# Run all tests
vendor/bin/phpunit

# Run specific plugin tests
vendor/bin/phpunit plugins/omsb/organization/tests
vendor/bin/phpunit plugins/omsb/procurement/tests
vendor/bin/phpunit plugins/omsb/inventory/tests
```

## Best Practices

### ✅ DO:
1. **Always store in base UOM** - `quantity_on_hand`, `quantity_change`, `balance_after`
2. **Keep transaction audit trail** - Store original `original_transaction_uom_id` and `original_transaction_quantity`
3. **Validate UOM compatibility** - Use `areCompatible()` before conversions
4. **Use UOMNormalizationService** - Centralized logic prevents errors
5. **Allow flexible display** - Let users choose preferred UOM for viewing

### ❌ DON'T:
1. **Never mix UOMs in calculations** - Always normalize first
2. **Don't store in purchase UOM** - Purchase UOM is for display only
3. **Don't hardcode conversion factors** - Use database relationships
4. **Don't skip validation** - Invalid conversions will corrupt data
5. **Don't forget precision** - Use `decimal_places` from UOM model

## Support and Documentation

- UOM Normalization Guide: `docs/uom-normalization-guide.md`
- Organization UnitOfMeasure Model: `plugins/omsb/organization/models/UnitOfMeasure.php`
- UOMNormalizationService: `plugins/omsb/organization/services/UOMNormalizationService.php`
- Test Examples: See test files in `plugins/omsb/*/tests/`

## Summary

This implementation provides:

✅ **Data Integrity**: All quantities stored in consistent base units  
✅ **Flexibility**: Users can work in any compatible UOM  
✅ **Audit Trail**: Original transaction UOM preserved  
✅ **Multi-UOM Support**: Complex scenarios like physical counts handled seamlessly  
✅ **Organizational Control**: UOMs approved centrally in Organization plugin  
✅ **Procurement/Inventory Independence**: Both plugins reference shared UOM definitions

**Database always uses base UOM. Display converts to user preference. Integrity maintained.**
