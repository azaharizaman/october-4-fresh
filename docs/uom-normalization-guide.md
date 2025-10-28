# UOM Normalization System - Usage Examples

## Overview

The UOM system has been refactored to Organization plugin level with **base UOM normalization**. All quantities are internally stored in **base units** for data integrity, while allowing flexible display in any compatible UOM.

---

## Key Concepts

### 1. Base UOM Hierarchy

Every UOM either:
- **IS a base unit** (`base_uom_id = null`) - e.g., ROLL, EA, KG, L, M
- **Derives from a base unit** (`base_uom_id` points to base) - e.g., PACK6 → ROLL, BOX → ROLL

Example hierarchy for tissue paper:
```
ROLL (base)
├── PACK6 (6 rolls)
├── PACK12 (12 rolls)
├── BOX (72 rolls = 12 packs of 6)
└── DRUM (144 rolls = 2 boxes)
```

### 2. Normalization Strategy

**All database storage uses base units:**
- `WarehouseItem.quantity_on_hand` → Always in base UOM (ROLL)
- `InventoryLedger.quantity_change` → Always in base UOM (ROLL)
- `PurchaseableItem` → Stores base_uom_id for reference

**Display conversion happens at runtime:**
- User purchases in BOX → System stores in ROLL
- User views inventory in PACK6 → System converts from ROLL
- Physical count in mixed UOM → System normalizes to ROLL

---

## Usage Examples

### Example 1: Simple Normalization

```php
use Omsb\Organization\Services\UOMNormalizationService;
use Omsb\Organization\Models\UnitOfMeasure;

$service = new UOMNormalizationService();

// Purchase: 2 BOXES of tissue paper
$box = UnitOfMeasure::where('code', 'BOX')->first();
$result = $service->normalize(2, $box);

// Result:
// [
//     'base_quantity' => 144,  // 2 boxes * 72 rolls/box = 144 rolls
//     'base_uom' => UnitOfMeasure (ROLL),
//     'source_uom' => UnitOfMeasure (BOX),
//     'precision' => 0
// ]

// Store in database: quantity_on_hand = 144 (ROLL)
```

### Example 2: Multi-UOM Physical Count

Stock take scenario: **"1 Box + 11 Packs of 6 + 10 Rolls"**

```php
$service = new UOMNormalizationService();

// Physical count data
$count = [
    'BOX' => 1,      // 1 box
    'PACK6' => 11,   // 11 packs of 6
    'ROLL' => 10     // 10 loose rolls
];

$result = $service->normalizeMultiple($count);

// Result:
// [
//     'total_base_quantity' => 148,  // 1*72 + 11*6 + 10*1 = 148 rolls
//     'base_uom' => UnitOfMeasure (ROLL),
//     'base_uom_code' => 'ROLL',
//     'breakdown' => [
//         ['uom_code' => 'BOX', 'quantity' => 1, 'base_quantity' => 72, 'conversion_factor' => 72],
//         ['uom_code' => 'PACK6', 'quantity' => 11, 'base_quantity' => 66, 'conversion_factor' => 6],
//         ['uom_code' => 'ROLL', 'quantity' => 10, 'base_quantity' => 10, 'conversion_factor' => 1]
//     ],
//     'precision' => 0
// ]

// Update warehouse item: quantity_on_hand = 148 (ROLL)
```

### Example 3: Breakdown for Display

Show 148 ROLLS in mixed UOMs:

```php
$service = new UOMNormalizationService();

$roll = UnitOfMeasure::where('code', 'ROLL')->first();
$targetUoms = ['BOX', 'PACK6', 'ROLL']; // Largest to smallest

$result = $service->breakdownQuantity(148, $roll, $targetUoms);

// Result:
// [
//     'total_base_quantity' => 148,
//     'base_uom' => UnitOfMeasure (ROLL),
//     'breakdown' => [
//         ['uom_code' => 'BOX', 'quantity' => 2, 'base_quantity_represented' => 144, 'conversion_factor' => 72],
//         ['uom_code' => 'PACK6', 'quantity' => 0, 'base_quantity_represented' => 0, 'conversion_factor' => 6],
//         ['uom_code' => 'ROLL', 'quantity' => 4, 'base_quantity_represented' => 4, 'conversion_factor' => 1]
//     ],
//     'remaining_base_units' => 0
// ]

// Display: "2 BOX + 0 PACK6 + 4 ROLL"
// Or simplified: "2 BOX + 4 ROLL"
```

### Example 4: UOM Conversion

Convert between any compatible UOMs:

```php
$service = new UOMNormalizationService();

// Convert 3 BOXES to ROLL
$rolls = $service->convert(3, 'BOX', 'ROLL');
// Result: 216 (3 * 72)

// Convert 216 ROLLS to PACK6
$packs = $service->convert(216, 'ROLL', 'PACK6');
// Result: 36 (216 / 6)

// Convert 1 DRUM to BOX
$boxes = $service->convert(1, 'DRUM', 'BOX');
// Result: 2 (144 / 72)

// Get conversion factor
$factor = $service->getConversionFactor('DRUM', 'PACK6');
// Result: 24 (1 DRUM = 24 PACK6)
```

### Example 5: Incompatible UOM Detection

```php
$service = new UOMNormalizationService();

// Try to convert weight to count
try {
    $service->convert(10, 'KG', 'ROLL');
} catch (ValidationException $e) {
    // Error: "Cannot convert from KG to ROLL (incompatible types)"
}

// Check compatibility first
$compatible = $service->areCompatible('BOX', 'ROLL');  // true (both → ROLL base)
$compatible = $service->areCompatible('KG', 'ROLL');   // false (different bases)
```

---

## Database Schema Updates

### PurchaseableItem (Procurement Plugin)

```php
// Add to migration:
$table->unsignedBigInteger('base_uom_id')->nullable()
    ->comment('Base UOM for normalization (from Organization plugin)');
$table->unsignedBigInteger('purchase_uom_id')->nullable()
    ->comment('Preferred purchase UOM');

$table->foreign('base_uom_id', 'fk_item_base_uom')
    ->references('id')->on('omsb_organization_unit_of_measures')
    ->nullOnDelete();
$table->foreign('purchase_uom_id', 'fk_item_purch_uom')
    ->references('id')->on('omsb_organization_unit_of_measures')
    ->nullOnDelete();
```

### WarehouseItem (Inventory Plugin)

```php
// Add to migration:
$table->unsignedBigInteger('base_uom_id')
    ->comment('Base UOM for quantity_on_hand');
$table->unsignedBigInteger('display_uom_id')->nullable()
    ->comment('Warehouse preference for displaying quantities');
$table->decimal('quantity_on_hand', 15, 6)->default(0)
    ->comment('ALWAYS in base UOM');

$table->foreign('base_uom_id', 'fk_wh_item_base_uom')
    ->references('id')->on('omsb_organization_unit_of_measures')
    ->restrictOnDelete();
$table->foreign('display_uom_id', 'fk_wh_item_display_uom')
    ->references('id')->on('omsb_organization_unit_of_measures')
    ->nullOnDelete();
```

### InventoryLedger (Inventory Plugin)

```php
// Add to migration:
$table->unsignedBigInteger('base_uom_id')
    ->comment('Base UOM (matches WarehouseItem)');
$table->decimal('quantity_change', 15, 6)
    ->comment('ALWAYS in base UOM (+/- from balance)');
$table->decimal('balance_after', 15, 6)
    ->comment('Running balance in base UOM');

// Audit trail for transaction UOM
$table->unsignedBigInteger('transaction_uom_id')->nullable()
    ->comment('Original UOM used in transaction (for audit)');
$table->decimal('transaction_quantity', 15, 6)->nullable()
    ->comment('Original quantity in transaction UOM');

$table->foreign('base_uom_id', 'fk_ledger_base_uom')
    ->references('id')->on('omsb_organization_unit_of_measures')
    ->restrictOnDelete();
$table->foreign('transaction_uom_id', 'fk_ledger_txn_uom')
    ->references('id')->on('omsb_organization_unit_of_measures')
    ->nullOnDelete();
```

---

## Controller Implementation Examples

### Procurement: Purchase Order Line Item

```php
use Omsb\Organization\Services\UOMNormalizationService;

public function onSavePOLineItem()
{
    $service = new UOMNormalizationService();
    
    // User selects: 2 BOXES at RM500/BOX
    $purchaseUom = post('purchase_uom_id'); // BOX
    $quantity = post('quantity'); // 2
    
    // Get purchaseable item
    $item = PurchaseableItem::find(post('purchaseable_item_id'));
    $baseUom = $item->base_uom_id;
    
    // Normalize to base UOM
    $normalized = $service->normalize($quantity, $purchaseUom);
    
    // Save line item
    $lineItem = new PurchaseOrderLineItem([
        'purchaseable_item_id' => $item->id,
        'quantity' => $normalized['base_quantity'], // 144 ROLLS
        'base_uom_id' => $baseUom,
        'purchase_uom_id' => $purchaseUom, // BOX (for display)
        'purchase_quantity' => $quantity, // 2 (for audit)
        'unit_price' => 500, // Per BOX
        'total_amount' => 1000
    ]);
    
    // Display in PO: "2 BOX @ RM500/BOX"
    // Database stores: quantity = 144 (base ROLL)
}
```

### Inventory: Goods Receipt

```php
public function onReceiveGoods()
{
    $service = new UOMNormalizationService();
    
    // Receiving 2 BOXES from PO
    $poLineItem = PurchaseOrderLineItem::find(post('po_line_item_id'));
    $receiveUom = post('receive_uom_id'); // Could be BOX, PACK6, or ROLL
    $receiveQty = post('receive_quantity');
    
    // Normalize
    $normalized = $service->normalize($receiveQty, $receiveUom);
    
    // Update warehouse item (add to QoH)
    $warehouseItem = WarehouseItem::firstOrCreate([
        'purchaseable_item_id' => $poLineItem->purchaseable_item_id,
        'warehouse_id' => post('warehouse_id')
    ], [
        'base_uom_id' => $poLineItem->base_uom_id,
        'quantity_on_hand' => 0
    ]);
    
    $oldBalance = $warehouseItem->quantity_on_hand;
    $newBalance = $oldBalance + $normalized['base_quantity'];
    
    $warehouseItem->quantity_on_hand = $newBalance;
    $warehouseItem->save();
    
    // Create ledger entry
    InventoryLedger::create([
        'warehouse_item_id' => $warehouseItem->id,
        'base_uom_id' => $warehouseItem->base_uom_id,
        'quantity_change' => $normalized['base_quantity'], // +144 ROLL
        'balance_after' => $newBalance,
        'transaction_uom_id' => $receiveUom, // BOX (for audit)
        'transaction_quantity' => $receiveQty, // 2
        'document_type' => 'GoodsReceiptNote',
        'document_id' => post('grn_id')
    ]);
}
```

### Inventory: Physical Count with Multi-UOM

```php
public function onSubmitPhysicalCount()
{
    $service = new UOMNormalizationService();
    
    // User counted: 1 BOX + 11 PACK6 + 10 ROLL
    $countData = post('count'); // ['BOX' => 1, 'PACK6' => 11, 'ROLL' => 10]
    
    // Normalize
    $result = $service->normalizeMultiple($countData);
    $totalRolls = $result['total_base_quantity']; // 148
    
    // Get warehouse item
    $warehouseItem = WarehouseItem::find(post('warehouse_item_id'));
    $oldQoH = $warehouseItem->quantity_on_hand;
    $variance = $totalRolls - $oldQoH;
    
    // Update QoH
    $warehouseItem->quantity_on_hand = $totalRolls;
    $warehouseItem->last_counted_at = now();
    $warehouseItem->save();
    
    // Create adjustment ledger entry
    InventoryLedger::create([
        'warehouse_item_id' => $warehouseItem->id,
        'base_uom_id' => $warehouseItem->base_uom_id,
        'quantity_change' => $variance,
        'balance_after' => $totalRolls,
        'notes' => 'Physical count: ' . json_encode($countData),
        'document_type' => 'PhysicalCount',
        'document_id' => post('physical_count_id')
    ]);
    
    // Display variance
    if ($variance != 0) {
        Flash::warning("Variance: {$service->formatQuantity(abs($variance), 'ROLL')}");
    }
}
```

### Backend Display: List View with UOM Selection

```php
// In controller
public function index()
{
    $this->vars['display_uom'] = post('display_uom', 'ROLL'); // Default
    $this->vars['warehouse_items'] = WarehouseItem::with(['purchaseable_item', 'base_uom'])->get();
}

// In view (Twig)
{% for item in warehouse_items %}
    {% set service = create_service('Omsb\\Organization\\Services\\UOMNormalizationService') %}
    {% set displayQty = service.denormalize(item.quantity_on_hand, item.base_uom_id, display_uom) %}
    
    <tr>
        <td>{{ item.purchaseable_item.name }}</td>
        <td>{{ displayQty|number_format(2) }} {{ display_uom }}</td>
        <td class="text-muted">({{ item.quantity_on_hand }} {{ item.base_uom.code }})</td>
    </tr>
{% endfor %}
```

---

## Best Practices

### ✅ DO:
1. **Always store in base UOM** - `quantity_on_hand`, `quantity_change`, `balance_after`
2. **Keep transaction audit trail** - Store original `transaction_uom_id` and `transaction_quantity`
3. **Validate UOM compatibility** - Use `areCompatible()` before conversions
4. **Use UOMNormalizationService** - Centralized logic prevents errors
5. **Allow flexible display** - Let users choose preferred UOM for viewing

### ❌ DON'T:
1. **Never mix UOMs in calculations** - Always normalize first
2. **Don't store in purchase UOM** - Purchase UOM is for display only
3. **Don't hardcode conversion factors** - Use database relationships
4. **Don't skip validation** - Invalid conversions will corrupt data
5. **Don't forget precision** - Use `decimal_places` from UOM model

---

## Migration Strategy

### Step 1: Add UOM columns to existing tables

```bash
php artisan create:migration Omsb.Procurement update_purchaseable_items_add_uom_fields
php artisan create:migration Omsb.Inventory update_warehouse_items_add_base_uom
php artisan create:migration Omsb.Inventory update_inventory_ledger_add_uom_audit
```

### Step 2: Migrate existing data

```php
// For PurchaseableItem: Infer base UOM from old unit_of_measure field
PurchaseableItem::chunk(100, function ($items) {
    foreach ($items as $item) {
        // Map old string to new UOM
        $uom = UnitOfMeasure::where('code', $item->unit_of_measure)->first();
        if ($uom) {
            $item->base_uom_id = $uom->getUltimateBaseUom()->id;
            $item->purchase_uom_id = $uom->id;
            $item->save();
        }
    }
});

// For WarehouseItem: quantity_on_hand already in base if consistent
WarehouseItem::whereNull('base_uom_id')->chunk(100, function ($items) {
    foreach ($items as $item) {
        $item->base_uom_id = $item->purchaseable_item->base_uom_id;
        $item->save();
    }
});
```

### Step 3: Update controllers and views

- Replace hardcoded UOM references with dropdown selections
- Add UOMNormalizationService calls before save operations
- Update list/detail views to allow UOM switching

---

## Testing

```php
// Unit tests
public function testNormalization()
{
    $service = new UOMNormalizationService();
    
    $result = $service->normalize(2, 'BOX');
    $this->assertEquals(144, $result['base_quantity']);
    $this->assertEquals('ROLL', $result['base_uom']->code);
}

public function testMultiUOMSum()
{
    $service = new UOMNormalizationService();
    
    $result = $service->normalizeMultiple([
        'BOX' => 1,
        'PACK6' => 11,
        'ROLL' => 10
    ]);
    
    $this->assertEquals(148, $result['total_base_quantity']);
}

public function testIncompatibleConversion()
{
    $this->expectException(ValidationException::class);
    
    $service = new UOMNormalizationService();
    $service->convert(10, 'KG', 'ROLL');
}
```

---

## Summary

The refactored UOM system provides:

✅ **Data Integrity**: All quantities stored in consistent base units  
✅ **Flexibility**: Users can work in any compatible UOM  
✅ **Audit Trail**: Original transaction UOM preserved  
✅ **Multi-UOM Support**: Complex scenarios like physical counts handled seamlessly  
✅ **Organizational Control**: UOMs approved centrally in Organization plugin  
✅ **Procurement/Inventory Independence**: Both plugins reference shared UOM definitions

**Database always uses base UOM. Display converts to user preference. Integrity maintained.**
