# Inventory Plugin Model Implementation Guide

## Overview
This document provides comprehensive guidance for completing the remaining models in the Inventory plugin. It includes patterns, conventions, and implementation templates based on the 10 models already fully implemented.

## Completed Models (10/27)

### Foundation & Core (5 models) ✓
1. **UnitOfMeasure** - Base UOM system
2. **UOMConversion** - Conversion rules between UOMs
3. **Warehouse** - Storage locations
4. **WarehouseItem** - SKU-level inventory records
5. **WarehouseItemUOM** - Multi-UOM support per SKU

### Ledger & Periods (2 models) ✓
6. **InventoryLedger** - Double-entry tracking (immutable)
7. **InventoryPeriod** - Month-end closing and period management

### Tracking (2 models) ✓
8. **LotBatch** - Lot/batch tracking for perishable items
9. **SerialNumber** - Individual serial tracking for assets

## Remaining Models (17/27)

### Valuation Models (2 models)
10. **InventoryValuation** - Period-end valuation reports
11. **InventoryValuationItem** - Detail items for valuation

### Warehouse Receipt Operations (4 models)
12. **Mrn** - Material Received Notes (inbound)
13. **MrnItem** - MRN line items
14. **MrnReturn** - MRN returns (damaged/rejected)
15. **MrnReturnItem** - MRN return line items

### Warehouse Issue Operations (4 models)
16. **Mri** - Material Request Issuance (outbound)
17. **MriItem** - MRI line items
18. **MriReturn** - MRI returns (unused items)
19. **MriReturnItem** - MRI return line items

### Stock Management (7 models)
20. **StockAdjustment** - Quantity corrections
21. **StockAdjustmentItem** - Adjustment line items
22. **StockTransfer** - Inter-warehouse transfers
23. **StockTransferItem** - Transfer line items
24. **PhysicalCount** - Inventory counting
25. **PhysicalCountItem** - Count line items
26. **StockReservation** - Allocation tracking

## Implementation Patterns

### Standard Model Template

```php
<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;
use Carbon\Carbon;

/**
 * ModelName Model
 * 
 * Brief description of purpose and functionality.
 *
 * @property int $id
 * @property ... (all properties with types and descriptions)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class ModelName extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_table_name';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        // List all editable fields
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        // List nullable foreign keys and optional fields
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        // Comprehensive validation rules
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        // User-friendly error messages
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        // Date/timestamp fields
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        // Type casts (boolean, decimal, integer, etc.)
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        // Parent relationships
    ];

    public $hasMany = [
        // Child relationships
    ];

    public $morphTo = [
        // Polymorphic relationships (if any)
    ];

    /**
     * Boot the model
     */
    public static function boot(): void
    {
        parent::boot();

        // Auto-set created_by on creation
        static::creating(function ($model) {
            if (BackendAuth::check()) {
                $model->created_by = BackendAuth::getUser()->id;
            }
        });

        // Additional lifecycle hooks as needed
    }

    /**
     * Get display name for dropdowns
     */
    public function getDisplayNameAttribute(): string
    {
        // Return formatted display name
    }

    /**
     * Scopes for common queries
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Additional scopes...

    /**
     * Business logic methods
     */
    // Add methods for common operations

    /**
     * Get options for dropdowns
     */
    // Add methods for form field options
}
```

### Document (Header) Models Pattern

Document models (Mrn, Mri, StockAdjustment, StockTransfer, PhysicalCount) follow this pattern:

**Key Characteristics:**
- Have a unique document number (from Registrar plugin)
- Have status workflow (draft, submitted, approved, completed, etc.)
- Have created_by, approved_by relationships
- Have date tracking (document date, approved date, etc.)
- Have totals (total_value, item_count, etc.)
- Have hasMany relationship to line items
- Have morphMany relationship to Feeder for activity tracking
- Status transition validation in boot() method
- Document can only be edited in draft status
- TODO markers for Workflow plugin integration

**Standard Fields:**
- `document_number` (unique, from Registrar)
- `document_date`
- `status` (enum with workflow states)
- `total_value` (calculated from line items)
- `notes` or `remarks`
- Staff relationships (created_by, approved_by, etc.)
- Foreign keys to related entities

**Standard Methods:**
- `canEdit()` - Check if editable (status == draft)
- `canApprove()` - Check if approvable
- `approve()` - Approve document
- `reject()` - Reject document  
- `complete()` - Complete document
- Status checking methods (isDraft(), isApproved(), etc.)
- Total calculation methods

### Line Item Models Pattern

Line item models (MrnItem, MriItem, StockAdjustmentItem, etc.) follow this pattern:

**Key Characteristics:**
- belongsTo parent document
- belongsTo warehouse_item
- belongsTo UOM for transactions
- Quantity fields with UOM conversion tracking
- Cost/value fields for valuation
- Optional lot_number, serial_numbers (JSON)
- remarks/notes field
- Conversion tracking fields

**Standard Fields:**
- `{document}_id` (parent document)
- `warehouse_item_id`
- `quantity` (main quantity field)
- `unit_cost`, `total_cost`
- `{transaction}_uom_id`
- `quantity_in_{transaction}_uom`
- `quantity_in_default_uom`
- `conversion_factor_used`
- `lot_number` (nullable)
- `serial_numbers` (JSON, nullable)
- `remarks` (nullable)

**Standard Methods:**
- `calculateTotalCost()` - Auto-calc total
- `convertQuantity()` - Convert between UOMs
- Validation for quantity/cost consistency

## Cross-Plugin Dependencies

### Procurement Plugin
```php
'Omsb\Procurement\Models\PurchaseableItem'
'Omsb\Procurement\Models\GoodsReceiptNote'
'Omsb\Procurement\Models\PurchaseOrderItem'
```

### Organization Plugin
```php
'Omsb\Organization\Models\Site'
'Omsb\Organization\Models\Staff'
'Omsb\Organization\Models\Address'
```

### Workflow Plugin
```php
// TODO: Integration for document status transitions
// Each document type needs workflow definition
```

### Feeder Plugin
```php
// TODO: Activity tracking integration
// Use morphMany relationship to Feed model
```

### Registrar Plugin
```php
// TODO: Document numbering integration
// Use Registrar service to generate document numbers
```

## Implementation Checklist for Each Model

- [ ] Create model class with proper namespace
- [ ] Define table name
- [ ] Add fillable array with all editable fields
- [ ] Add nullable array for optional fields
- [ ] Define comprehensive validation rules
- [ ] Add custom validation messages
- [ ] Define dates array
- [ ] Add casts for type conversion
- [ ] Define all relationships (belongsTo, hasMany, morphTo)
- [ ] Implement boot() method with lifecycle hooks
- [ ] Add getDisplayNameAttribute() method
- [ ] Implement scopes for common queries
- [ ] Add business logic methods
- [ ] Add dropdown options methods
- [ ] Add PHPDoc block with properties
- [ ] Add TODO markers for cross-plugin dependencies
- [ ] Test validation rules
- [ ] Test relationships
- [ ] Test business logic methods

## Service Layer Implementation

After models are complete, implement service classes:

### InventoryLedgerService
```php
<?php namespace Omsb\Inventory\Services;

/**
 * InventoryLedgerService
 * 
 * Handles double-entry inventory ledger operations.
 * Ensures every increase has a corresponding decrease.
 */
class InventoryLedgerService
{
    /**
     * Create ledger entry pair for stock receipt
     * 
     * @param array $data
     * @return array [receipt_entry, issue_entry]
     */
    public function createReceiptEntry(array $data): array
    {
        // Create increase entry (receiving warehouse)
        // Create decrease entry (in-transit virtual warehouse)
        // Return both entries
    }

    /**
     * Create ledger entry pair for stock issue
     */
    public function createIssueEntry(array $data): array
    {
        // Create decrease entry (issuing warehouse)
        // Create increase entry (destination/consumption)
    }

    /**
     * Create ledger entries for stock transfer
     */
    public function createTransferEntries(array $data): array
    {
        // Create decrease entry (from warehouse)
        // Create increase entry (to warehouse)
    }

    /**
     * Create ledger entry for stock adjustment
     */
    public function createAdjustmentEntry(array $data)
    {
        // Single entry with +/- quantity
    }
}
```

### WarehouseService
```php
<?php namespace Omsb\Inventory\Services;

/**
 * WarehouseService
 * 
 * Manages warehouse operations and validations.
 */
class WarehouseService
{
    /**
     * Get receiving warehouse for site
     */
    public function getReceivingWarehouse(int $siteId)
    {
        // Find designated receiving warehouse
        // Fallback to first active warehouse if not set
    }

    /**
     * Validate warehouse can accept stock
     */
    public function canAcceptStock(int $warehouseId): bool
    {
        // Check warehouse status
        // Check capacity if applicable
    }

    /**
     * Get available warehouses for item
     */
    public function getAvailableWarehouses(int $purchaseableItemId): array
    {
        // Return warehouses that stock this item
    }
}
```

### UOMConversionService
```php
<?php namespace Omsb\Inventory\Services;

/**
 * UOMConversionService
 * 
 * Handles UOM conversions and validations.
 */
class UOMConversionService
{
    /**
     * Convert quantity between UOMs
     */
    public function convert(float $quantity, int $fromUomId, int $toUomId): ?float
    {
        // Find conversion path
        // Apply conversion factor
        // Return converted quantity or null if no path exists
    }

    /**
     * Get conversion factor between UOMs
     */
    public function getConversionFactor(int $fromUomId, int $toUomId): ?float
    {
        // Find direct or inverse conversion
        // Return factor or null
    }

    /**
     * Validate UOM conversion exists
     */
    public function hasConversion(int $fromUomId, int $toUomId): bool
    {
        // Check if conversion path exists
    }
}
```

### ValuationService
```php
<?php namespace Omsb\Inventory\Services;

/**
 * ValuationService
 * 
 * Calculates inventory valuations using FIFO/LIFO/Average methods.
 */
class ValuationService
{
    /**
     * Calculate FIFO valuation
     */
    public function calculateFIFO(int $warehouseItemId, Carbon $asOfDate): array
    {
        // Get ledger entries in FIFO order
        // Calculate cost layers
        // Return valuation data
    }

    /**
     * Calculate LIFO valuation
     */
    public function calculateLIFO(int $warehouseItemId, Carbon $asOfDate): array
    {
        // Get ledger entries in LIFO order
        // Calculate cost layers
    }

    /**
     * Calculate Average Cost valuation
     */
    public function calculateAverage(int $warehouseItemId, Carbon $asOfDate): array
    {
        // Calculate weighted average cost
    }

    /**
     * Generate period valuation report
     */
    public function generatePeriodValuation(int $periodId): InventoryValuation
    {
        // Generate valuation for all items in period
        // Use configured valuation method
    }
}
```

## Controller Implementation

Each model needs a controller with:

### Controller Structure
```
controllers/
  ModelNames/
    _list_toolbar.php
    config_form.yaml
    config_list.yaml
    create.php
    index.php
    preview.php
    update.php
```

### Config Files

**config_form.yaml:**
```yaml
name: Model Name
form: $/omsb/inventory/models/modelname/fields.yaml
modelClass: Omsb\Inventory\Models\ModelName
defaultRedirect: omsb/inventory/modelnames

create:
    title: backend::lang.form.create_title
    redirect: omsb/inventory/modelnames/update/:id
    redirectClose: omsb/inventory/modelnames

update:
    title: backend::lang.form.update_title
    redirect: omsb/inventory/modelnames
    redirectClose: omsb/inventory/modelnames

preview:
    title: backend::lang.form.preview_title
```

**config_list.yaml:**
```yaml
list: $/omsb/inventory/models/modelname/columns.yaml
modelClass: Omsb\Inventory\Models\ModelName
title: Manage Model Names
recordUrl: omsb/inventory/modelnames/update/:id
noRecordsMessage: backend::lang.list.no_records
recordsPerPage: 20
showPageNumbers: true
showSetup: true
showSorting: true

defaultSort:
    column: id
    direction: desc

showCheckboxes: true

toolbar:
    buttons: list_toolbar
    search:
        prompt: backend::lang.list.search_prompt
```

### Model YAML Files

**models/modelname/fields.yaml** - Form field definitions
**models/modelname/columns.yaml** - List column definitions

## Plugin.php Updates

```php
/**
 * registerNavigation used by the backend.
 */
public function registerNavigation()
{
    return [
        'inventory' => [
            'label' => 'Inventory',
            'url' => Backend::url('omsb/inventory/warehouses'),
            'icon' => 'icon-cubes',
            'permissions' => ['omsb.inventory.*'],
            'order' => 300,
            'sideMenu' => [
                'warehouses' => [
                    'label' => 'Warehouses',
                    'icon' => 'icon-warehouse',
                    'url' => Backend::url('omsb/inventory/warehouses'),
                    'permissions' => ['omsb.inventory.warehouses']
                ],
                'warehouse_items' => [
                    'label' => 'Stock Items',
                    'icon' => 'icon-box',
                    'url' => Backend::url('omsb/inventory/warehouseitems'),
                    'permissions' => ['omsb.inventory.items']
                ],
                'mrn' => [
                    'label' => 'Goods Receipt (MRN)',
                    'icon' => 'icon-sign-in',
                    'url' => Backend::url('omsb/inventory/mrns'),
                    'permissions' => ['omsb.inventory.mrn']
                ],
                'mri' => [
                    'label' => 'Material Issue (MRI)',
                    'icon' => 'icon-sign-out',
                    'url' => Backend::url('omsb/inventory/mris'),
                    'permissions' => ['omsb.inventory.mri']
                ],
                // Add more menu items...
            ]
        ]
    ];
}

/**
 * registerPermissions used by the backend.
 */
public function registerPermissions()
{
    return [
        'omsb.inventory.warehouses' => [
            'tab' => 'Inventory',
            'label' => 'Manage Warehouses'
        ],
        'omsb.inventory.items' => [
            'tab' => 'Inventory',
            'label' => 'Manage Stock Items'
        ],
        'omsb.inventory.mrn' => [
            'tab' => 'Inventory',
            'label' => 'Manage Goods Receipt (MRN)'
        ],
        'omsb.inventory.mri' => [
            'tab' => 'Inventory',
            'label' => 'Manage Material Issue (MRI)'
        ],
        // Add more permissions...
    ];
}
```

## Testing Strategy

### Unit Tests
```php
<?php namespace Omsb\Inventory\Tests\Unit;

use Omsb\Inventory\Models\ModelName;
use PluginTestCase;

class ModelNameTest extends PluginTestCase
{
    public function testValidation()
    {
        // Test validation rules
    }

    public function testRelationships()
    {
        // Test model relationships
    }

    public function testBusinessLogic()
    {
        // Test business methods
    }
}
```

### Integration Tests
```php
<?php namespace Omsb\Inventory\Tests\Integration;

use PluginTestCase;

class InventoryFlowTest extends PluginTestCase
{
    public function testGoodsReceiptFlow()
    {
        // Test complete MRN flow
    }

    public function testStockIssueFlow()
    {
        // Test complete MRI flow
    }

    public function testStockTransferFlow()
    {
        // Test inter-warehouse transfer
    }
}
```

## Implementation Priority

### High Priority (Core Operations)
1. StockReservation - blocking other operations
2. Mrn + MrnItem - inbound operations
3. Mri + MriItem - outbound operations
4. InventoryValuation + InventoryValuationItem - financial reporting

### Medium Priority
5. StockAdjustment + StockAdjustmentItem - corrections
6. StockTransfer + StockTransferItem - movements
7. PhysicalCount + PhysicalCountItem - verification

### Lower Priority (Returns)
8. MrnReturn + MrnReturnItem
9. MriReturn + MriReturnItem

## Notes

- All models follow PHP 8.2 standards with return type declarations
- Comprehensive PHPDoc blocks for all properties and methods
- Validation rules with custom user-friendly messages
- Proper use of $nullable for optional fields
- BackendAuth integration for created_by fields
- Soft deletes enabled where appropriate
- TODO markers for cross-plugin dependencies
- Status workflows for document models
- Double-entry compliance for ledger integration
- UOM conversion tracking in all quantity fields
