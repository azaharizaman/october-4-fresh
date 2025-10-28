# HasFeed Trait Documentation

## Overview

The `HasFeed` trait provides automatic activity feed tracking for any OctoberCMS model. It eliminates the need for manual feed creation in controllers by automatically capturing model lifecycle events (create, update, delete) and custom actions (approve, reject, submit, etc.).

## Features

- ✅ **Automatic Feed Creation**: Feeds created automatically on model events (created, updated, deleted)
- ✅ **Customizable Message Templates**: Define message format per model with placeholder support
- ✅ **Action Filtering**: Control which actions trigger feed creation
- ✅ **Significant Field Tracking**: Only create update feeds when important fields change
- ✅ **Custom Actions**: Record domain-specific actions (approve, reject, submit, etc.)
- ✅ **Relationship Methods**: Easy access to feeds via Eloquent relationships
- ✅ **Metadata Capture**: Store additional context with each feed entry
- ✅ **Custom Placeholders**: Inject model-specific data into feed messages
- ✅ **Zero Breaking Changes**: Works alongside existing model traits and functionality

## Quick Start

### 1. Add the Trait to Your Model

```php
<?php namespace Vendor\Plugin\Models;

use Model;
use Omsb\Feeder\Traits\HasFeed;

class YourModel extends Model
{
    use HasFeed;
    
    // Your existing model code...
}
```

### 2. Configure Feed Behavior (Optional)

Add these properties to customize feed behavior:

```php
/**
 * HasFeed trait configuration
 */
protected $feedMessageTemplate = '{actor} {action} {model} "{name}"';
protected $feedableActions = ['created', 'updated', 'deleted', 'approved', 'rejected'];
protected $feedSignificantFields = ['name', 'status', 'amount'];
```

### 3. That's It!

Feeds will now be created automatically when your model is created, updated, or deleted.

## Configuration Properties

### `$feedMessageTemplate` (string)

Defines the message format for feed entries. Supports placeholders:

**Standard Placeholders:**
- `{actor}`: User who performed the action (e.g., "John Smith")
- `{action}`: Action type (e.g., "created", "updated", "approved")
- `{model}`: Model display name (e.g., "Purchase Request")
- `{model_identifier}`: Document number or code (e.g., "PR-2024-00001")
- `{timestamp}`: Formatted timestamp (e.g., "2024-01-15 10:30 AM")

**Custom Placeholders:**
You can inject model-specific data by overriding `getFeedTemplatePlaceholders()`:

```php
protected function getFeedTemplatePlaceholders(): array
{
    return [
        '{purchaseable_item}' => $this->purchaseable_item ? $this->purchaseable_item->name : 'Unknown',
        '{warehouse}' => $this->warehouse->name ?? 'N/A',
    ];
}
```

**Examples:**

```php
// Generic template
protected $feedMessageTemplate = '{actor} {action} {model}';
// Output: "John Smith created Purchase Request"

// Document-focused template
protected $feedMessageTemplate = '{actor} {action} {model} {model_identifier}';
// Output: "Jane Doe approved Purchase Request PR-2024-00001"

// Detailed template with custom fields
protected $feedMessageTemplate = '{actor} {action} {model} "{name}" ({code})';
// Output: "Admin updated Vendor "ABC Supplies" (V-001)"
```

### `$feedableActions` (array)

Controls which actions trigger automatic feed creation. If not specified, all actions create feeds.

```php
// Only track these specific actions
protected $feedableActions = [
    'created',
    'updated',
    'deleted',
    'submitted',
    'approved',
    'rejected',
    'completed',
];
```

**Common Action Types:**

**CRUD Actions:**
- `created`: Model created
- `updated`: Model updated
- `deleted`: Model soft deleted

**Workflow Actions:**
- `submitted`: Document submitted for approval
- `approved`: Document approved
- `rejected`: Document rejected
- `cancelled`: Document cancelled
- `completed`: Document completed

**Domain-Specific Actions:**
- `discontinued`: Item discontinued (Procurement)
- `reactivated`: Entity reactivated
- `suspended`: Vendor/entity suspended
- `reorder_triggered`: Reorder point reached (Inventory)
- `variance_review`: Physical count variance review (Inventory)
- `shipped`: Stock transfer shipped (Inventory)
- `received`: Goods/transfer received (Inventory)

### `$feedSignificantFields` (array)

Defines which fields are "significant" for update tracking. Updates to other fields won't create feeds.

```php
protected $feedSignificantFields = [
    'status',
    'total_amount',
    'priority',
    'approved_by',
    'name',
    'code',
];
```

**Why Use This?**
- Prevents feed spam from trivial updates (e.g., changing a description field)
- Focuses audit trail on business-critical changes
- Improves performance by reducing unnecessary feed creation

**Example:**

```php
// With feedSignificantFields = ['status', 'amount']

$model->description = 'Updated description';
$model->save();
// ❌ No feed created (description not significant)

$model->status = 'approved';
$model->save();
// ✅ Feed created (status is significant)
```

### `$autoFeedEnabled` (boolean)

Globally enable/disable automatic feed creation for this model.

```php
protected $autoFeedEnabled = false; // Disable all automatic feeds
```

**Use Cases:**
- Disable during data migrations
- Disable for performance during bulk operations
- Enable manual-only feed creation

## Usage Examples

### Basic Model with Feed Tracking

```php
<?php namespace Omsb\Procurement\Models;

use Model;
use Omsb\Feeder\Traits\HasFeed;

class Vendor extends Model
{
    use HasFeed;

    protected $feedMessageTemplate = '{actor} {action} Vendor "{name}" ({code})';
    protected $feedableActions = ['created', 'updated', 'deleted', 'approved', 'suspended'];
    protected $feedSignificantFields = ['name', 'code', 'status', 'is_approved'];
}
```

**Automatic Feeds:**
```php
// Creating vendor automatically creates feed
$vendor = Vendor::create([
    'name' => 'ABC Supplies',
    'code' => 'V-001',
    'status' => 'active',
]);
// Feed: "John Smith created Vendor "ABC Supplies" (V-001)"

// Updating significant field creates feed
$vendor->status = 'suspended';
$vendor->save();
// Feed: "Jane Doe updated Vendor "ABC Supplies" (V-001)"
```

### Workflow Document with Custom Actions

```php
<?php namespace Omsb\Procurement\Models;

use Model;
use Omsb\Feeder\Traits\HasFeed;

class PurchaseRequest extends Model
{
    use HasFeed;

    protected $feedMessageTemplate = '{actor} {action} Purchase Request {model_identifier}';
    protected $feedableActions = [
        'created',
        'updated',
        'deleted',
        'submitted',
        'approved',
        'rejected',
        'cancelled',
    ];
    protected $feedSignificantFields = ['status', 'total_amount', 'priority'];
}
```

**Custom Actions:**
```php
$pr = PurchaseRequest::create([...]);
// Feed: "John Smith created Purchase Request PR-2024-00001"

// Record custom actions with metadata
$pr->recordAction('submitted', ['submitted_to' => 'Manager']);
// Feed: "John Smith submitted Purchase Request PR-2024-00001"

$pr->recordAction('approved', [
    'approver' => 'Jane Doe',
    'approval_notes' => 'Approved within budget',
]);
// Feed: "Jane Doe approved Purchase Request PR-2024-00001"
```

### Inventory Item with Custom Placeholders

```php
<?php namespace Omsb\Inventory\Models;

use Model;
use Omsb\Feeder\Traits\HasFeed;

class WarehouseItem extends Model
{
    use HasFeed;

    protected $feedMessageTemplate = '{actor} {action} Warehouse Item for {purchaseable_item}';
    protected $feedableActions = [
        'created',
        'updated',
        'deleted',
        'reorder_triggered',
        'count_updated',
    ];
    protected $feedSignificantFields = [
        'quantity_on_hand',
        'quantity_reserved',
        'minimum_stock_level',
    ];

    /**
     * Inject custom placeholder with item name
     */
    protected function getFeedTemplatePlaceholders(): array
    {
        return [
            '{purchaseable_item}' => $this->purchaseable_item 
                ? $this->purchaseable_item->name 
                : 'Unknown Item',
        ];
    }
}
```

**Usage:**
```php
$warehouseItem = WarehouseItem::create([...]);
// Feed: "John Smith created Warehouse Item for Office Supplies"

$warehouseItem->recordAction('reorder_triggered', [
    'current_qty' => 15,
    'minimum_qty' => 20,
]);
// Feed: "System reorder_triggered Warehouse Item for Office Supplies"
```

## Methods Reference

### Relationship Methods

#### `feeds()`
Returns the morphMany relationship to Feed model.

```php
$model->feeds; // Get all feeds
$model->feeds()->where('action_type', 'approved')->get(); // Filter feeds
```

#### `getRecentFeeds($limit = 10)`
Get most recent feeds for this model.

```php
$recentFeeds = $model->getRecentFeeds(5); // Last 5 feeds
```

#### `getFeedsByAction($actionType)`
Filter feeds by specific action type.

```php
$approvals = $model->getFeedsByAction('approved');
$rejections = $model->getFeedsByAction('rejected');
```

#### `getFeedTimeline()`
Get formatted timeline array for display.

```php
$timeline = $model->getFeedTimeline();
// [
//     [
//         'action' => 'approved',
//         'message' => 'Jane Doe approved Purchase Request PR-2024-00001',
//         'timestamp' => '2024-01-15 10:30 AM',
//         'user' => 'Jane Doe',
//         'metadata' => [...]
//     ],
//     ...
// ]
```

### Utility Methods

#### `hasFeeds()`
Check if model has any feeds.

```php
if ($model->hasFeeds()) {
    // Display activity feed
}
```

#### `getFeedCount()`
Get total number of feeds.

```php
$count = $model->getFeedCount();
```

#### `deleteAllFeeds()`
Delete all feeds for this model (for cleanup/testing).

```php
$model->deleteAllFeeds();
```

### Action Recording

#### `recordAction($action, $additionalData = [], $customMessage = null)`
Manually record a custom action with optional metadata.

```php
// Simple action
$model->recordAction('approved');

// With metadata
$model->recordAction('approved', [
    'approver' => 'Jane Doe',
    'approval_date' => now()->format('Y-m-d'),
    'notes' => 'Approved within budget',
]);

// With custom message
$model->recordAction('special_event', [], 'Custom message here');
```

### Customization Hooks

Override these methods to customize feed behavior:

#### `getFeedMessageTemplate($actionType)`
Customize template per action type.

```php
protected function getFeedMessageTemplate($actionType): string
{
    if ($actionType === 'approved') {
        return '{actor} approved {model} {model_identifier} ✓';
    }
    
    return parent::getFeedMessageTemplate($actionType);
}
```

#### `getFeedTemplatePlaceholders()`
Inject custom placeholders.

```php
protected function getFeedTemplatePlaceholders(): array
{
    return [
        '{custom_field}' => $this->custom_field,
        '{related_model}' => $this->relation->name ?? 'N/A',
    ];
}
```

#### `getFeedBody($actionType)`
Provide detailed body content for feed.

```php
protected function getFeedBody($actionType): ?string
{
    if ($actionType === 'approved') {
        return "Total Amount: {$this->total_amount}\nApproved By: {$this->approved_by}";
    }
    
    return null;
}
```

#### `getDefaultFeedMetadata()`
Add model-specific metadata to all feeds.

```php
protected function getDefaultFeedMetadata(): array
{
    return [
        'model_type' => 'workflow_document',
        'department' => $this->department->name ?? null,
    ];
}
```

#### `getFeedModelName()`
Customize model display name.

```php
protected function getFeedModelName(): string
{
    return 'Material Received Note'; // Instead of "Mrn"
}
```

#### `getFeedModelIdentifier()`
Use document number instead of database ID.

```php
protected function getFeedModelIdentifier(): string
{
    return $this->document_number ?? $this->code ?? "#{$this->id}";
}
```

## Integration Patterns

### With Workflow Plugin

HasFeed works seamlessly with workflow documents:

```php
class PurchaseOrder extends Model
{
    use HasFeed;
    use HasWorkflow; // Existing trait
    
    protected $feedMessageTemplate = '{actor} {action} Purchase Order {model_identifier}';
    protected $feedableActions = ['created', 'updated', 'submitted', 'approved', 'completed'];
}

// Workflow transitions automatically create feeds
$po->submitForApproval();
// Feed: "User submitted Purchase Order PO-2024-00001"

$po->approve();
// Feed: "Manager approved Purchase Order PO-2024-00001"
```

### With Controlled Document Numbers

HasFeed respects `HasControlledDocumentNumber` trait:

```php
class StockTransfer extends Model
{
    use HasFeed;
    use HasControlledDocumentNumber; // Document numbering
    
    protected $documentTypeCode = 'STRF';
    
    protected function getFeedModelIdentifier(): string
    {
        return $this->document_number; // Uses controlled document number
    }
}
```

### With Multi-User Systems

Feeds automatically capture the authenticated user:

```php
// User 1 creates
Auth::login($user1);
$model = Model::create([...]);
// Feed created with user1 as actor

// User 2 approves
Auth::login($user2);
$model->recordAction('approved');
// Feed created with user2 as actor
```

## Performance Considerations

### Bulk Operations

Disable auto-feeds during bulk operations:

```php
// Disable auto-feeds
Model::flushEventListeners();

// Bulk import
foreach ($data as $row) {
    Model::create($row); // No feeds created
}

// Re-enable by rebooting
Model::boot();
```

### Significant Fields Strategy

Use `feedSignificantFields` to prevent feed spam:

```php
// ❌ Too broad - creates feeds for everything
protected $feedSignificantFields = ['*'];

// ✅ Focused - only business-critical fields
protected $feedSignificantFields = ['status', 'total_amount', 'approved_by'];
```

### Feed Archiving

For high-volume models, implement feed archiving:

```php
// Archive feeds older than 1 year
$model->feeds()
    ->where('created_at', '<', now()->subYear())
    ->delete();
```

## Testing

### Unit Testing

```php
public function testFeedCreatedOnModelCreate()
{
    $model = TestModel::create(['name' => 'Test']);
    
    $this->assertTrue($model->hasFeeds());
    $this->assertEquals(1, $model->getFeedCount());
    $this->assertEquals('created', $model->feeds->first()->action_type);
}
```

### Integration Testing

```php
public function testWorkflowFeedIntegration()
{
    $pr = PurchaseRequest::create([...]);
    
    $pr->recordAction('submitted');
    $pr->recordAction('approved');
    
    $timeline = $pr->getFeedTimeline();
    $this->assertCount(3, $timeline); // created + submitted + approved
}
```

## Migration Guide

### From Manual Feed Creation

**Before (Manual):**
```php
// In controller
public function onApprove()
{
    $model = Model::find(post('id'));
    $model->status = 'approved';
    $model->save();
    
    // Manual feed creation
    Feed::create([
        'user_id' => BackendAuth::getUser()->id,
        'feedable_type' => Model::class,
        'feedable_id' => $model->id,
        'action_type' => 'approved',
        'message' => BackendAuth::getUser()->full_name . ' approved ' . $model->name,
        'metadata' => ['status' => 'approved'],
    ]);
}
```

**After (HasFeed):**
```php
// In model
use HasFeed;

protected $feedableActions = ['created', 'updated', 'deleted', 'approved'];

// In controller
public function onApprove()
{
    $model = Model::find(post('id'));
    $model->recordAction('approved'); // That's it!
}
```

**Code Reduction:** ~15 lines → 1 line (93% less code)

## Troubleshooting

### Feeds Not Creating

**Check 1:** Ensure trait is added and configured
```php
use Omsb\Feeder\Traits\HasFeed;

protected $feedableActions = ['created', 'updated', 'deleted']; // Include action
```

**Check 2:** Verify user is authenticated
```php
$user = BackendAuth::getUser();
if (!$user) {
    // Feeds require authenticated user
}
```

**Check 3:** Check autoFeedEnabled
```php
protected $autoFeedEnabled = true; // Should be true (or omit, default is true)
```

### Update Feeds Not Creating

**Check:** Verify significant fields are changing
```php
protected $feedSignificantFields = ['status', 'amount'];

// This creates feed (status is significant)
$model->status = 'new value';
$model->save();

// This doesn't (description not significant)
$model->description = 'new value';
$model->save();
```

### Custom Placeholders Not Working

**Check:** Override getFeedTemplatePlaceholders correctly
```php
protected function getFeedTemplatePlaceholders(): array
{
    // Must return array of placeholder => value
    return [
        '{custom}' => $this->custom_field,
    ];
}
```

## Best Practices

1. **Keep Templates Concise:** Clear, brief messages work best
   ```php
   ✅ '{actor} {action} {model} {model_identifier}'
   ❌ '{actor} has performed the action of {action} on the {model} with identifier {model_identifier} at {timestamp}'
   ```

2. **Use Significant Fields Wisely:** Only track business-critical changes
   ```php
   ✅ ['status', 'total_amount', 'approved_by']
   ❌ ['*'] // Too broad
   ```

3. **Leverage Metadata:** Store context for future reference
   ```php
   $model->recordAction('approved', [
       'approver' => $approver->name,
       'approval_date' => now()->format('Y-m-d'),
       'notes' => 'Approved with conditions',
   ]);
   ```

4. **Customize Identifiers:** Use business identifiers, not database IDs
   ```php
   protected function getFeedModelIdentifier(): string
   {
       return $this->document_number ?? $this->code ?? "#{$this->id}";
   }
   ```

5. **Test Feed Creation:** Include feed assertions in model tests
   ```php
   $this->assertTrue($model->hasFeeds());
   $this->assertEquals('approved', $model->feeds()->latest()->first()->action_type);
   ```

## See Also

- [Feed Model Documentation](./feed-model.md)
- [Integration Examples](./integration-examples.md)
- [API Reference](./api-reference.md)
- [OctoberCMS Model Events](https://docs.octobercms.com/4.x/extend/database/model.html#model-events)
