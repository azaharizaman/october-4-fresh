# HasFeed Trait - Quick Reference

## 30-Second Setup

```php
<?php namespace Vendor\Plugin\Models;

use Model;
use Omsb\Feeder\Traits\HasFeed;

class YourModel extends Model
{
    use HasFeed;
    
    // Configure (all optional):
    protected $feedMessageTemplate = '{actor} {action} {model} {model_identifier}';
    protected $feedableActions = ['created', 'updated', 'deleted', 'approved'];
    protected $feedSignificantFields = ['status', 'amount'];
}
```

**That's it!** Feeds are now created automatically.

## Configuration Properties

| Property | Type | Description | Default |
|----------|------|-------------|---------|
| `feedMessageTemplate` | `string` | Message format with placeholders | `'{actor} {action} {model}'` |
| `feedableActions` | `array` | Actions that create feeds | `['created', 'updated', 'deleted']` |
| `feedSignificantFields` | `array` | Fields that trigger update feeds | `[]` (all fields) |
| `autoFeedEnabled` | `bool` | Enable/disable automatic feeds | `true` |

## Placeholders

| Placeholder | Output Example |
|-------------|----------------|
| `{actor}` | "John Smith" |
| `{action}` | "created", "approved" |
| `{model}` | "Purchase Request" |
| `{model_identifier}` | "PR-2024-00001" |
| `{timestamp}` | "2024-01-15 10:30 AM" |
| Custom placeholders | Your model data |

## Methods

### Relationship

```php
$model->feeds;                    // Get all feeds
$model->feeds()->where(...);      // Query feeds
```

### Helpers

```php
$model->getRecentFeeds(10);       // Last 10 feeds
$model->getFeedsByAction('approved'); // Filter by action
$model->getFeedTimeline();        // Formatted timeline
$model->hasFeeds();               // Check if feeds exist
$model->getFeedCount();           // Count feeds
```

### Custom Actions

```php
// Simple action
$model->recordAction('approved');

// With metadata
$model->recordAction('approved', [
    'approver' => 'Manager',
    'notes' => 'Approved within budget',
]);

// With custom message
$model->recordAction('special', [], 'Custom message here');
```

## Common Patterns

### Workflow Document

```php
protected $feedMessageTemplate = '{actor} {action} {model} {model_identifier}';
protected $feedableActions = [
    'created', 'updated', 'deleted',
    'submitted', 'approved', 'rejected', 'cancelled', 'completed'
];
protected $feedSignificantFields = ['status', 'total_amount', 'priority'];
```

### Catalog/Master Data

```php
protected $feedMessageTemplate = '{actor} {action} {model} "{name}" ({code})';
protected $feedableActions = [
    'created', 'updated', 'deleted',
    'activated', 'deactivated', 'discontinued'
];
protected $feedSignificantFields = ['name', 'code', 'is_active', 'status'];
```

### Inventory Item

```php
protected $feedMessageTemplate = '{actor} {action} {model} for {item_name}';
protected $feedableActions = [
    'created', 'updated', 'deleted',
    'reorder_triggered', 'count_updated'
];
protected $feedSignificantFields = [
    'quantity_on_hand', 'quantity_reserved', 'minimum_stock_level'
];

// Custom placeholder
protected function getFeedTemplatePlaceholders(): array
{
    return [
        '{item_name}' => $this->item->name ?? 'Unknown',
    ];
}
```

## Customization Hooks

### Per-Action Templates

```php
protected function getFeedMessageTemplate($actionType): string
{
    if ($actionType === 'approved') {
        return '{actor} approved {model} {model_identifier} ✓';
    }
    return parent::getFeedMessageTemplate($actionType);
}
```

### Custom Placeholders

```php
protected function getFeedTemplatePlaceholders(): array
{
    return [
        '{custom_field}' => $this->custom_field,
        '{related}' => $this->relation->name ?? 'N/A',
    ];
}
```

### Feed Body Content

```php
protected function getFeedBody($actionType): ?string
{
    if ($actionType === 'approved') {
        return "Amount: {$this->total_amount}\nApprover: {$this->approved_by}";
    }
    return null;
}
```

### Model Identifier

```php
protected function getFeedModelIdentifier(): string
{
    return $this->document_number ?? $this->code ?? "#{$this->id}";
}
```

### Model Display Name

```php
protected function getFeedModelName(): string
{
    return 'Material Received Note'; // Instead of "Mrn"
}
```

### Default Metadata

```php
protected function getDefaultFeedMetadata(): array
{
    return [
        'department' => $this->department->name ?? null,
        'site' => $this->site->code ?? null,
    ];
}
```

## Action Types by Domain

### CRUD
- `created`, `updated`, `deleted`

### Workflow
- `submitted`, `approved`, `rejected`, `cancelled`, `completed`

### Procurement
- `reactivated`, `discontinued`, `suspended`

### Inventory
- `reorder_triggered`, `count_updated`, `variance_review`
- `shipped`, `received`, `issued`, `returned`

### Custom
- Define any action type relevant to your domain

## Testing

```php
public function testFeedCreation()
{
    $model = YourModel::create(['name' => 'Test']);
    
    $this->assertTrue($model->hasFeeds());
    $this->assertEquals(1, $model->getFeedCount());
    $this->assertEquals('created', $model->feeds->first()->action_type);
}

public function testCustomAction()
{
    $model = YourModel::create([...]);
    $model->recordAction('approved', ['approver' => 'Manager']);
    
    $feed = $model->getFeedsByAction('approved')->first();
    $this->assertNotNull($feed);
    $this->assertEquals('Manager', $feed->metadata['approver']);
}
```

## Performance Tips

1. **Use significant fields** to prevent feed spam:
   ```php
   protected $feedSignificantFields = ['status', 'amount']; // Only these create feeds
   ```

2. **Filter actions** to track only what matters:
   ```php
   protected $feedableActions = ['created', 'approved', 'completed'];
   ```

3. **Disable during bulk operations**:
   ```php
   Model::flushEventListeners();
   // Bulk import...
   Model::boot();
   ```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Feeds not creating | Check `feedableActions` includes the action |
| Update feeds not creating | Verify `feedSignificantFields` includes changed field |
| Custom placeholders not working | Check `getFeedTemplatePlaceholders()` returns array |
| No user context | Ensure user is authenticated (`BackendAuth::getUser()`) |

## Full Documentation

See [HasFeed Trait Documentation](./hasfeed-trait.md) for complete guide with examples.

## Integrated Models

HasFeed is already integrated in:

**Procurement (3 models):**
- PurchaseableItem
- PurchaseRequest
- Vendor

**Inventory (10 models):**
- InventoryValuation
- Mri (Material Request Issuance)
- Mrn (Material Received Note)
- MriReturn
- MrnReturn
- PhysicalCount
- StockAdjustment
- StockTransfer
- Warehouse
- WarehouseItem

See model source code for configuration examples.
