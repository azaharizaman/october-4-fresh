# Controller and View Integration Guide

## Overview
This guide provides specific recommendations for integrating UOM normalization into controllers and views for the Procurement and Inventory plugins.

## Controllers to Update

### Procurement Plugin Controllers

#### 1. PurchaseableItemsController
**Location**: `plugins/omsb/procurement/controllers/PurchaseableItemsController.php`

**Integration Points**:
- Form configuration to include UOM dropdown fields
- Validation of UOM fields on save

**Recommended Changes**:

```php
// In config_form.yaml or fields.yaml
base_uom_id:
    label: Base Unit of Measure
    type: dropdown
    span: left
    comment: Base UOM for quantity normalization (inventory items)
    dependsOn: is_inventory_item
    placeholder: -- Select Base UOM --

purchase_uom_id:
    label: Purchase UOM
    type: dropdown
    span: right
    comment: Preferred UOM for purchasing
    placeholder: -- Select Purchase UOM --
```

#### 2. PurchaseOrderController
**Location**: `plugins/omsb/procurement/controllers/PurchaseOrderController.php`

**Integration Points**:
- PO line item entry - allow user to select purchase UOM
- Before save: normalize quantity to base UOM
- Display: show both original and normalized quantities

**Recommended Implementation**:

```php
use Omsb\Organization\Services\UOMNormalizationService;

public function onSavePOLineItem()
{
    $service = new UOMNormalizationService();
    
    // User input
    $purchaseUomId = post('purchase_uom_id');
    $quantity = post('quantity');
    $itemId = post('purchaseable_item_id');
    
    // Get purchaseable item
    $item = PurchaseableItem::find($itemId);
    
    // Normalize to base UOM
    $normalized = $service->normalize($quantity, $purchaseUomId);
    
    // Save line item
    $lineItem = new PurchaseOrderLineItem([
        'purchaseable_item_id' => $item->id,
        'quantity' => $normalized['base_quantity'], // Store in base UOM
        'base_uom_id' => $item->base_uom_id,
        'purchase_uom_id' => $purchaseUomId, // For display
        'purchase_quantity' => $quantity, // For audit
        'unit_price' => post('unit_price'),
        'total_amount' => post('total_amount')
    ]);
    
    $lineItem->save();
    
    return [
        'success' => true,
        'message' => "Added {$quantity} {$item->purchase_uom->code} ({$normalized['base_quantity']} {$item->base_uom->code})"
    ];
}
```

### Inventory Plugin Controllers

#### 3. GoodsReceiptNoteController
**Location**: `plugins/omsb/inventory/controllers/GoodsReceiptNoteController.php`

**Integration Points**:
- Receiving goods from PO - allow flexible UOM entry
- Create WarehouseItem with base UOM
- Create InventoryLedger with UOM audit trail

**Recommended Implementation**:

```php
use Omsb\Organization\Services\UOMNormalizationService;

public function onReceiveGoods()
{
    $service = new UOMNormalizationService();
    
    // User can receive in any compatible UOM
    $receiveUomId = post('receive_uom_id');
    $receiveQty = post('receive_quantity');
    $poLineItemId = post('po_line_item_id');
    
    // Get PO line item
    $poLineItem = PurchaseOrderLineItem::find($poLineItemId);
    $item = $poLineItem->purchaseable_item;
    
    // Normalize to base UOM
    $normalized = $service->normalize($receiveQty, $receiveUomId);
    
    // Get or create warehouse item
    $warehouseItem = WarehouseItem::firstOrCreate([
        'purchaseable_item_id' => $item->id,
        'warehouse_id' => post('warehouse_id')
    ], [
        'base_uom_id' => $item->base_uom_id,
        'display_uom_id' => $item->purchase_uom_id,
        'quantity_on_hand' => 0,
        'cost_method' => 'FIFO',
        'is_active' => true
    ]);
    
    // Create ledger entry (this updates warehouse item quantity)
    InventoryLedger::createEntry([
        'warehouse_item_id' => $warehouseItem->id,
        'base_uom_id' => $item->base_uom_id,
        'document_type' => 'GoodsReceiptNote',
        'document_id' => post('grn_id'),
        'transaction_type' => 'receipt',
        'quantity_change' => $normalized['base_quantity'], // In base UOM
        'unit_cost' => $poLineItem->unit_price,
        'transaction_date' => now(),
        'original_transaction_uom_id' => $receiveUomId,
        'original_transaction_quantity' => $receiveQty,
        // Legacy fields for backward compatibility
        'transaction_uom_id' => post('legacy_uom_id'),
        'quantity_in_transaction_uom' => $receiveQty,
        'quantity_in_default_uom' => $normalized['base_quantity'],
        'conversion_factor_used' => $normalized['source_uom']->conversion_to_base_factor ?? 1
    ]);
    
    Flash::success("Received {$receiveQty} {$service->resolveUom($receiveUomId)->code} ({$normalized['base_quantity']} {$item->base_uom->code})");
}
```

#### 4. PhysicalCountController
**Location**: `plugins/omsb/inventory/controllers/PhysicalCountController.php`

**Integration Points**:
- Multi-UOM physical counting
- Normalize total to base UOM
- Calculate variance

**Recommended Implementation**:

```php
use Omsb\Organization\Services\UOMNormalizationService;

public function onSubmitPhysicalCount()
{
    $service = new UOMNormalizationService();
    
    // User counted in multiple UOMs: 1 BOX + 11 PACK6 + 10 ROLL
    $countData = post('count'); // ['BOX' => 1, 'PACK6' => 11, 'ROLL' => 10]
    
    // Normalize all to base UOM
    $result = $service->normalizeMultiple($countData);
    $totalBaseQty = $result['total_base_quantity']; // 148 rolls
    
    // Get warehouse item
    $warehouseItem = WarehouseItem::find(post('warehouse_item_id'));
    $oldQoH = $warehouseItem->quantity_on_hand;
    $variance = $totalBaseQty - $oldQoH;
    
    // Create physical count record
    $physicalCount = PhysicalCount::create([
        'warehouse_item_id' => $warehouseItem->id,
        'counted_date' => now(),
        'counted_by' => BackendAuth::getUser()->id,
        'system_quantity' => $oldQoH,
        'counted_quantity' => $totalBaseQty,
        'variance' => $variance,
        'count_details' => json_encode($result['breakdown'])
    ]);
    
    // Create adjustment ledger entry if variance
    if ($variance != 0) {
        InventoryLedger::createEntry([
            'warehouse_item_id' => $warehouseItem->id,
            'base_uom_id' => $warehouseItem->base_uom_id,
            'document_type' => 'PhysicalCount',
            'document_id' => $physicalCount->id,
            'transaction_type' => 'adjustment',
            'quantity_change' => $variance,
            'transaction_date' => now(),
            'notes' => 'Physical count adjustment: ' . json_encode($countData),
            'transaction_uom_id' => post('legacy_uom_id'),
            'quantity_in_transaction_uom' => abs($variance),
            'quantity_in_default_uom' => abs($variance),
            'conversion_factor_used' => 1
        ]);
        
        Flash::warning("Variance detected: {$variance} {$warehouseItem->base_uom->code}");
    }
}
```

## View Updates

### Form Fields Configuration

#### PurchaseableItem Form
**File**: `plugins/omsb/procurement/models/purchaseableitem/fields.yaml`

Add after `unit_of_measure` field:

```yaml
base_uom_id:
    label: Base Unit of Measure
    type: dropdown
    span: left
    comment: Base UOM for inventory normalization
    dependsOn: is_inventory_item
    trigger:
        action: show
        field: is_inventory_item
        condition: checked
    placeholder: -- Select Base UOM --

purchase_uom_id:
    label: Preferred Purchase UOM
    type: dropdown
    span: right
    comment: Default UOM for purchasing this item
    placeholder: -- Select Purchase UOM --
```

#### WarehouseItem Form
**File**: `plugins/omsb/inventory/models/warehouseitem/fields.yaml`

Add in appropriate section:

```yaml
base_uom_id:
    label: Base Unit of Measure
    type: dropdown
    span: left
    comment: Base UOM - quantity_on_hand is always stored in this unit
    disabled: true
    comment: Inherited from Purchaseable Item

display_uom_id:
    label: Display UOM
    type: dropdown
    span: right
    comment: Preferred UOM for displaying quantities in this warehouse
    placeholder: -- Select Display UOM --
```

### List Views with UOM Display

#### WarehouseItem List
**File**: `plugins/omsb/inventory/models/warehouseitem/columns.yaml`

Update quantity display:

```yaml
quantity_on_hand:
    label: Quantity on Hand
    type: partial
    path: $/omsb/inventory/models/warehouseitem/_column_quantity.htm
    sortable: true
    searchable: false
```

**Partial File**: `plugins/omsb/inventory/models/warehouseitem/_column_quantity.htm`

```twig
{% set service = create_service('Omsb\\Organization\\Services\\UOMNormalizationService') %}

{# Display in base UOM #}
<strong>{{ record.quantity_on_hand|number_format(2) }}</strong> 
<span class="text-muted">{{ record.base_uom.code }}</span>

{# Also show in display UOM if set #}
{% if record.display_uom and record.display_uom.id != record.base_uom.id %}
    {% set displayQty = service.denormalize(
        record.quantity_on_hand, 
        record.base_uom, 
        record.display_uom
    ) %}
    <br><small class="text-info">
        ({{ displayQty|number_format(2) }} {{ record.display_uom.code }})
    </small>
{% endif %}
```

### AJAX Handlers for UOM Selection

#### Dynamic UOM Dropdown
**In Controller**:

```php
public function onGetCompatibleUoms()
{
    $itemId = post('item_id');
    $item = PurchaseableItem::find($itemId);
    
    if (!$item || !$item->base_uom_id) {
        return ['options' => []];
    }
    
    // Get all UOMs compatible with this item's base UOM
    $baseUom = $item->base_uom;
    $compatibleUoms = UnitOfMeasure::where('is_active', true)
        ->where('is_approved', true)
        ->where(function($q) use ($baseUom) {
            $q->whereNull('base_uom_id') // Base UOMs
              ->orWhere('base_uom_id', $baseUom->id); // Derived from same base
        })
        ->pluck('display_name', 'id')
        ->toArray();
    
    return ['options' => $compatibleUoms];
}
```

**In View**:

```html
<div data-request="onGetCompatibleUoms"
     data-request-update="'@uom-dropdown': '#uomDropdown'"
     data-trigger="change"
     data-trigger-on="#itemId">
    
    <select id="uomDropdown" name="uom_id" class="form-control">
        <option value="">-- Select UOM --</option>
    </select>
</div>
```

### Reporting Views

#### Inventory Report with Multiple UOM Display

```php
// In controller
public function onGenerateReport()
{
    $service = new UOMNormalizationService();
    $displayUom = post('display_uom'); // User preference
    
    $items = WarehouseItem::with(['purchaseable_item', 'base_uom'])->get();
    
    $reportData = $items->map(function($item) use ($service, $displayUom) {
        $baseQty = $item->quantity_on_hand;
        
        // Convert to display UOM if requested
        if ($displayUom && $displayUom != $item->base_uom->code) {
            $displayQty = $service->convert($baseQty, $item->base_uom->code, $displayUom);
            return [
                'item' => $item->purchaseable_item->name,
                'quantity' => $displayQty,
                'uom' => $displayUom,
                'base_quantity' => $baseQty,
                'base_uom' => $item->base_uom->code
            ];
        }
        
        return [
            'item' => $item->purchaseable_item->name,
            'quantity' => $baseQty,
            'uom' => $item->base_uom->code
        ];
    });
    
    return ['data' => $reportData];
}
```

## Widget Integration

### UOM Selector Widget

Create a reusable widget for UOM selection:

**File**: `plugins/omsb/inventory/formwidgets/UomSelector.php`

```php
<?php namespace Omsb\Inventory\FormWidgets;

use Backend\Classes\FormWidgetBase;
use Omsb\Organization\Models\UnitOfMeasure;

class UomSelector extends FormWidgetBase
{
    public $compatibleWith = null;
    
    protected $defaultAlias = 'uom_selector';
    
    public function render()
    {
        $this->prepareVars();
        return $this->makePartial('uom_selector');
    }
    
    protected function prepareVars()
    {
        $this->vars['name'] = $this->formField->getName();
        $this->vars['value'] = $this->getLoadValue();
        $this->vars['options'] = $this->getOptions();
    }
    
    protected function getOptions()
    {
        $query = UnitOfMeasure::where('is_active', true)
            ->where('is_approved', true);
            
        if ($this->compatibleWith) {
            // Filter for compatible UOMs only
            $baseUom = UnitOfMeasure::find($this->compatibleWith);
            if ($baseUom) {
                $query->where(function($q) use ($baseUom) {
                    $q->where('id', $baseUom->id)
                      ->orWhere('base_uom_id', $baseUom->id);
                });
            }
        }
        
        return $query->pluck('display_name', 'id')->toArray();
    }
}
```

**Usage in fields.yaml**:

```yaml
receive_uom_id:
    label: Receive in UOM
    type: uom_selector
    compatibleWith: base_uom_id
```

## Summary Checklist

### Controllers
- [ ] Update PurchaseableItemsController form config
- [ ] Integrate UOMNormalizationService in PurchaseOrderController
- [ ] Integrate UOMNormalizationService in GoodsReceiptNoteController
- [ ] Integrate UOMNormalizationService in PhysicalCountController
- [ ] Add AJAX handlers for dynamic UOM selection

### Views
- [ ] Add UOM fields to PurchaseableItem forms
- [ ] Add UOM fields to WarehouseItem forms
- [ ] Update list columns to show UOM with quantities
- [ ] Create partials for UOM display
- [ ] Add UOM selector widgets
- [ ] Update reports to support UOM preferences

### Testing
- [ ] Test form submissions with UOM normalization
- [ ] Test display conversions in list views
- [ ] Test multi-UOM physical counts
- [ ] Test UOM compatibility validation
- [ ] Test audit trail preservation

## Notes

- Always use `UOMNormalizationService` for conversions
- Never store quantities in non-base UOM in the database
- Always preserve original transaction UOM for audit trail
- Validate UOM compatibility before conversions
- Handle edge cases (null UOM, incompatible UOM)
- Provide clear user feedback on conversions
