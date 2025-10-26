# Feeder Plugin

## Overview

The Feeder plugin is a centralized activity tracking system that logs all user actions across the OctoberCMS application. It provides a unified audit trail for all OMSB plugins, eliminating the need for per-plugin activity logging implementations.

**Important:** Feed records are system-generated and cannot be manually created, edited, or deleted. They are automatically created by the system when triggered by certain activities or explicitly called by other methods or events.

## Key Concepts

### Feed Model

The Feed model tracks user activities using polymorphic (morphTo) relationships, allowing it to reference any model in the system that needs activity logging.

**Core Fields:**
- `user_id`: References the backend user (staff member) who performed the action
- `action_type`: Type of action performed (create, update, delete, approve, reject, etc.)
- `feedable_type`: Fully qualified class name of the related model (polymorphic)
- `feedable_id`: ID of the related model record (polymorphic)
- `title`: Optional title for notes/comments
- `body`: Optional body text for notes/comments
- `additional_data`: JSON field storing action-specific details (amounts, statuses, etc.)
- `created_at`: Timestamp when the action occurred
- `updated_at`: Last modification timestamp

**Features:**
- Centralized activity logging (removes need for per-plugin logging)
- Filterable by user, action type, target model, date range
- Customizable feed display per model type
- Immutable records - cannot be edited or deleted once created
- Polymorphic relationships for maximum flexibility
- Support for notes/comments with title and body fields

**Relationships:**
- `belongsTo`: `user` (Backend\Models\User) - The staff member who performed the action
- `morphTo`: `feedable` - The model instance that was acted upon (polymorphic relationship)

**Key Methods:**

`getDescriptionAttribute(): string`
- Returns a formatted, human-readable description of the feed action
- Format: "{User Name} {action} {Model Type}"
- Example: "John Doe created PurchaseRequest"

`scopeActionType($query, string $actionType)`
- Query scope to filter feeds by action type
- Usage: `Feed::actionType('create')->get()`

`scopeFeedableType($query, string $feedableType)`
- Query scope to filter feeds by model type
- Usage: `Feed::feedableType('Omsb\Procurement\Models\PurchaseRequest')->get()`

`scopeByUser($query, int $userId)`
- Query scope to filter feeds by user ID
- Usage: `Feed::byUser(5)->get()`

`beforeDelete()`
- Prevents deletion of feed records
- Always returns false

`beforeUpdate()`
- Prevents modification of feed records after creation
- Throws exception if attempting to modify existing record

`getForDocument(string $feedableType, int $feedableId, int $limit = 50)`
- Static method to retrieve feeds for a specific model instance
- Returns feeds ordered by created_at descending
- Eager loads user relationships
- Usage: `Feed::getForDocument(PurchaseRequest::class, $prId, 25)`

**Validation Rules:**
- `action_type`: Required, string, maximum 50 characters
- `feedable_type`: Required, string, maximum 255 characters
- `feedable_id`: Required, integer

## Backend Interface

The Feeder plugin does not provide a dedicated backend navigation menu. Feed records are intended to be viewed through related models or custom dashboard widgets. The model configuration files (fields.yaml and columns.yaml) can be used by other plugins to display feed information in their interfaces.

**Permissions:**
- `omsb.feeder.access_feeds`: Permission for accessing feed data programmatically

## Database Structure

### Feeds Table (`omsb_feeder_feeds`)

- `id`: Primary key (BIGINT UNSIGNED AUTO_INCREMENT)
- `user_id`: Foreign key to backend_users (INT UNSIGNED, nullable)
- `action_type`: Type of action (VARCHAR(50), indexed)
- `feedable_type`: Model class name (VARCHAR(255), indexed)
- `feedable_id`: Model record ID (BIGINT UNSIGNED, indexed)
- `title`: Optional title for notes/comments (VARCHAR(255), nullable)
- `body`: Optional body text for notes/comments (TEXT, nullable)
- `additional_data`: JSON field for extra information (nullable)
- `created_at`, `updated_at`: Timestamps

**Indexes:**
- `idx_feeds_feedable`: Composite index on (feedable_type, feedable_id) for polymorphic queries
- `idx_feeds_action_type`: Index on action_type for filtering
- `idx_feeds_user_id`: Index on user_id for user-specific queries
- `idx_feeds_created_at`: Index on created_at for date-based queries

**Foreign Keys:**
- `user_id` references `backend_users.id` with NULL ON DELETE

## Usage Examples

### Displaying Feed in a Sidebar

The Feeder plugin provides a reusable sidebar partial that can be included in any backend form to display activity feeds for the current document.

#### Basic Usage

Add the following code to your controller's view file (e.g., `update.php` or `preview.php`):

```php
<?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
    'feedableType' => get_class($formModel),
    'feedableId' => $formModel->id,
]) ?>
```

#### Advanced Usage with Custom Title and Limit

```php
<?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
    'feedableType' => 'Omsb\Procurement\Models\PurchaseRequest',
    'feedableId' => $model->id,
    'title' => 'Purchase Request Activity',
    'limit' => 100,
]) ?>
```

#### Integration in Form Config with Sidebar

In your `config_form.yaml`, you can add the feed sidebar using the `secondaryTabs` or `outside` fields:

```yaml
# config_form.yaml
secondaryTabs:
    stretch: true
    fields:
        # ... your other tabs ...
        
        _feed_sidebar:
            type: partial
            path: $/omsb/feeder/partials/_feed_sidebar.htm
            context: [update, preview]
            tab: Activity
            cssClass: feed-sidebar-tab
```

Or include it directly in your view file sidebar:

```php
<!-- In your update.php or preview.php -->
<div class="layout">
    <div class="layout-row">
        <!-- Main content -->
        <div class="layout-cell">
            <?= $this->formRender() ?>
        </div>
        
        <!-- Sidebar with feed -->
        <div class="layout-cell layout-sidebar">
            <?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
                'feedableType' => get_class($formModel),
                'feedableId' => $formModel->id,
            ]) ?>
        </div>
    </div>
</div>
```

#### Partial Parameters

Required:
- `feedableType` (string): Fully qualified class name of the model (e.g., `Omsb\Procurement\Models\PurchaseRequest`)
- `feedableId` (int): ID of the model instance

Optional:
- `title` (string): Custom title for the feed section (default: 'Activity Feed')
- `limit` (int): Maximum number of feed items to display (default: 50)

#### Features

The feed sidebar partial automatically displays:
- User avatars with initials and color coding
- Action descriptions (created, updated, approved, etc.)
- Timestamps in relative format (e.g., "1 month ago")
- Status transitions with colored badges
- Optional title and body text for comments/notes
- Additional metadata like amounts, document numbers
- Vertical timeline view with connecting lines

### Recording a Simple Activity

```php
use Omsb\Feeder\Models\Feed;
use BackendAuth;

// When a Purchase Request is created
Feed::create([
    'user_id' => BackendAuth::getUser()->id,
    'action_type' => 'create',
    'feedable_type' => PurchaseRequest::class,
    'feedable_id' => $purchaseRequest->id,
]);
```

### Recording an Activity with Title and Body (Notes/Comments)

```php
use Omsb\Feeder\Models\Feed;
use BackendAuth;

// When adding a comment or note to a document
Feed::create([
    'user_id' => BackendAuth::getUser()->id,
    'action_type' => 'comment',
    'feedable_type' => PurchaseRequest::class,
    'feedable_id' => $purchaseRequest->id,
    'title' => 'Budget Approval Required',
    'body' => 'This purchase request requires additional budget approval from finance department before proceeding.',
]);
```

### Recording an Activity with Additional Data

```php
use Omsb\Feeder\Models\Feed;
use BackendAuth;

// When a Purchase Order is approved with amount details
Feed::create([
    'user_id' => BackendAuth::getUser()->id,
    'action_type' => 'approve',
    'feedable_type' => PurchaseOrder::class,
    'feedable_id' => $purchaseOrder->id,
    'additional_data' => [
        'total_amount' => $purchaseOrder->total_amount,
        'currency' => 'MYR',
        'approval_level' => 'manager',
        'previous_status' => 'submitted',
        'new_status' => 'approved'
    ],
]);
```

### Recording a Model Deletion Activity

```php
use Omsb\Feeder\Models\Feed;
use BackendAuth;

// When a Material Request is deleted (record the action before deletion)
Feed::create([
    'user_id' => BackendAuth::getUser()->id,
    'action_type' => 'delete',
    'feedable_type' => MaterialRequest::class,
    'feedable_id' => $materialRequest->id,
    'title' => 'Material Request Cancelled',
    'body' => 'Request was cancelled by user before approval.',
    'additional_data' => [
        'document_number' => $materialRequest->document_number,
        'reason' => 'Cancelled by user request'
    ],
]);
```

### Immutability Protection

Feed records are immutable once created:

```php
// This will work - creating a new feed
$feed = Feed::create([
    'user_id' => BackendAuth::getUser()->id,
    'action_type' => 'create',
    'feedable_type' => PurchaseRequest::class,
    'feedable_id' => $pr->id,
]);

// This will throw an exception - attempting to modify existing feed
try {
    $feed->action_type = 'update';
    $feed->save();
} catch (\Exception $e) {
    // Exception: "Feed records cannot be modified once created."
}

// This will fail - attempting to delete feed
$result = $feed->delete(); // Returns false, record not deleted
```

### Querying Recent Activities

```php
use Omsb\Feeder\Models\Feed;

// Get all activities for the last 7 days
$recentFeeds = Feed::where('created_at', '>=', now()->subDays(7))
    ->orderBy('created_at', 'desc')
    ->get();

// Get all 'approve' actions
$approvals = Feed::actionType('approve')
    ->orderBy('created_at', 'desc')
    ->get();

// Get all activities for Purchase Requests
$prActivities = Feed::feedableType('Omsb\Procurement\Models\PurchaseRequest')
    ->orderBy('created_at', 'desc')
    ->get();

// Get all activities by a specific user
$userActivities = Feed::byUser($userId)
    ->orderBy('created_at', 'desc')
    ->get();
```

### Retrieving Feed with Related Models

```php
use Omsb\Feeder\Models\Feed;

// Get feed with user and feedable model loaded
$feed = Feed::with('user', 'feedable')->find($feedId);

// Access related data
echo $feed->user->full_name; // User who performed the action
echo $feed->feedable->name; // Name of the related model (if it has a name attribute)
echo $feed->description; // Formatted description
```

## Integration Points

### With Organization Plugin
- References `Backend\Models\User` for staff members who perform actions
- Staff hierarchy and permissions control who can view feed entries

### With All OMSB Plugins
- All plugins should create feed entries for important actions:
  - **Procurement**: Purchase Request creation/approval, PO creation/approval, Vendor Quotation submission
  - **Inventory**: Material Request creation/approval, Stock Transfer, Stock Adjustment, Physical Count
  - **Workflow**: Status transitions, approvals, rejections
  - **Organization**: Company/Site/Staff creation/updates

### Common Action Types

Standard action types used across plugins:
- `create`: New record created
- `update`: Record modified
- `delete`: Record deleted or cancelled
- `approve`: Record approved in workflow
- `reject`: Record rejected in workflow
- `submit`: Record submitted for approval
- `review`: Record reviewed
- `complete`: Record marked as complete
- `cancel`: Record cancelled
- `comment`: Note or comment added to a record

## Development Guidelines

### When to Create Feed Entries

Create feed entries for:
1. **All CRUD operations**: create, update, delete
2. **Workflow transitions**: submit, approve, reject, complete
3. **Important business actions**: Stock transfer, Physical count, Payment processing
4. **Administrative actions**: User role changes, Permission updates
5. **Comments and notes**: User adds comments or notes to records

### When NOT to Create Feed Entries

Don't create feed entries for:
1. **Read operations**: Viewing records, generating reports
2. **System operations**: Automated cron jobs, system maintenance
3. **High-frequency actions**: API heartbeats, auto-save drafts
4. **Sensitive operations**: Password changes, authentication attempts (use security audit log instead)

### Immutability and Protection

Feed records are immutable by design:
1. **Cannot be edited**: Once created, feed records cannot be modified. The `beforeUpdate()` method throws an exception if modification is attempted.
2. **Cannot be deleted**: The `beforeDelete()` method always returns false, preventing deletion.
3. **Permanent audit trail**: All feed records remain in the database indefinitely for audit purposes.

### Best Practices

1. **Always use BackendAuth::getUser()** to get the current user ID:
   ```php
   'user_id' => BackendAuth::getUser()->id
   ```

2. **Use fully qualified class names** for feedable_type:
   ```php
   'feedable_type' => \Omsb\Procurement\Models\PurchaseRequest::class
   ```

3. **Store relevant context** in additional_data:
   ```php
   'additional_data' => [
       'document_number' => $model->document_number,
       'total_amount' => $model->total_amount,
       'status_from' => $oldStatus,
       'status_to' => $newStatus,
   ]
   ```

4. **Use descriptive action types**: Prefer 'approve_purchase_order' over just 'approve' if it adds clarity

5. **Use title and body for comments**: When recording notes or comments, use the title and body fields:
   ```php
   'title' => 'Budget Approval Required',
   'body' => 'This request requires additional budget approval before proceeding.'
   ```

6. **Create feed entries after successful operations**: Use database transactions to ensure feed entry is only created if the main operation succeeds

7. **Handle exceptions gracefully**: If feed creation fails, log the error but don't block the main operation

### Example Service Method with Feed Integration

```php
namespace Omsb\Procurement\Services;

use Omsb\Procurement\Models\PurchaseRequest;
use Omsb\Feeder\Models\Feed;
use BackendAuth;
use Db;

class PurchaseRequestService
{
    public function approvePurchaseRequest(PurchaseRequest $pr, string $comments = null): bool
    {
        return Db::transaction(function () use ($pr, $comments) {
            $oldStatus = $pr->status;
            
            // Perform the approval
            $pr->status = 'approved';
            $pr->approved_by = BackendAuth::getUser()->id;
            $pr->approved_at = now();
            $pr->approval_comments = $comments;
            $pr->save();
            
            // Record the activity
            Feed::create([
                'user_id' => BackendAuth::getUser()->id,
                'action_type' => 'approve',
                'feedable_type' => PurchaseRequest::class,
                'feedable_id' => $pr->id,
                'additional_data' => [
                    'document_number' => $pr->document_number,
                    'total_amount' => $pr->total_amount,
                    'status_from' => $oldStatus,
                    'status_to' => 'approved',
                    'comments' => $comments,
                ],
            ]);
            
            return true;
        });
    }
}
```

## Technical Implementation Details

### Model Architecture

The Feed model follows OctoberCMS conventions:
- Extends `Model` base class
- Uses `Validation` trait for automatic validation
- Implements immutability via `beforeUpdate()` and `beforeDelete()` methods
- Implements polymorphic relationships via `$morphTo` property
- Provides query scopes for common filtering patterns
- Supports title and body fields for notes/comments

### No Backend Controller

The Feeder plugin does not include a backend controller or navigation menu by design:
- Feed entries are system-generated and cannot be manually created or edited
- Feeds are intended to be displayed in the context of their related models
- Other plugins can use the Feed model's field and column configurations to display activity feeds
- Dashboard widgets can be created in other plugins to show recent activity

### Security Considerations

1. **Permission-based access**: Only users with `omsb.feeder.access_feeds` permission can query feeds programmatically
2. **Immutability**: Feed entries cannot be modified or deleted to maintain audit trail integrity
3. **User attribution**: Every feed entry should have a valid user_id (except system operations)
4. **JSON sanitization**: additional_data field should not contain sensitive information like passwords
5. **Protected methods**: `beforeUpdate()` and `beforeDelete()` methods prevent accidental modifications

### Performance Considerations

1. **Indexes**: Multiple indexes ensure fast queries for common filter patterns
2. **Eager loading**: Use `with('user', 'feedable')` to avoid N+1 queries
3. **Archival strategy**: Consider implementing periodic archival of old feed entries (>1 year) to a separate table
4. **Query optimization**: Use scopes and proper indexing for efficient data retrieval

## Future Enhancements

Potential areas for expansion:
- Dashboard widget showing recent activities
- Email notifications for specific action types
- Advanced filtering by date range, multiple action types, multiple model types
- Export functionality (CSV, Excel) for audit reports
- Real-time activity stream using WebSockets
- Activity analytics and trending reports
- Integration with external audit/compliance systems
- Custom action type registration system
- Feed entry consolidation (grouping related actions)

## Troubleshooting

### Feed entries not appearing

1. Check that the feed entry was actually created (check database)
2. Verify the user has `omsb.feeder.access_feeds` permission for programmatic access
3. Verify the feedable model exists and is not deleted

### Cannot modify or delete feed entries

This is by design. Feed entries are immutable:
1. **Modification attempts**: Will throw an exception with message "Feed records cannot be modified once created."
2. **Deletion attempts**: Will fail silently (returns false) and record remains in database
3. **Purpose**: Ensures audit trail integrity and prevents tampering with historical records

### Performance issues with large datasets

1. Implement periodic archival of old entries
2. Add additional indexes for custom query patterns
3. Use query scopes to limit result sets
4. Consider implementing caching for frequently accessed feed lists

### Related model not loading

1. Ensure the feedable_type uses fully qualified class name
2. Check that the related model still exists in the database
3. Verify the model class is properly namespaced and autoloaded

## Conclusion

The Feeder plugin provides a robust, centralized activity tracking system for all OMSB plugins. Feed records are immutable by design, ensuring a reliable audit trail that cannot be tampered with. By following the usage guidelines and best practices outlined in this document, developers can ensure comprehensive audit trails across the entire application.
