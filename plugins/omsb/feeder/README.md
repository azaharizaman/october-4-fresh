# Feeder Plugin

## Overview

The Feeder plugin is a centralized activity tracking system that logs all user actions across the OctoberCMS application. It provides a unified audit trail for all OMSB plugins, eliminating the need for per-plugin activity logging implementations.

## Key Concepts

### Feed Model

The Feed model tracks user activities using polymorphic (morphTo) relationships, allowing it to reference any model in the system that needs activity logging.

**Core Fields:**
- `user_id`: References the backend user (staff member) who performed the action
- `action_type`: Type of action performed (create, update, delete, approve, reject, etc.)
- `feedable_type`: Fully qualified class name of the related model (polymorphic)
- `feedable_id`: ID of the related model record (polymorphic)
- `additional_data`: JSON field storing action-specific details (amounts, statuses, etc.)
- `created_at`: Timestamp when the action occurred
- `updated_at`: Last modification timestamp
- `deleted_at`: Soft delete timestamp for audit trail preservation

**Features:**
- Centralized activity logging (removes need for per-plugin logging)
- Filterable by user, action type, target model, date range
- Customizable feed display per model type
- Comprehensive audit trail with soft deletes
- Polymorphic relationships for maximum flexibility

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

**Validation Rules:**
- `action_type`: Required, string, maximum 50 characters
- `feedable_type`: Required, string, maximum 255 characters
- `feedable_id`: Required, integer

## Backend Interface

### Feeds Controller

The Feeds controller provides a read-only interface for viewing activity logs through the OctoberCMS backend.

**Features:**
- List view with searching, sorting, and pagination
- Preview view for detailed feed entry examination
- Default sort by creation date (newest first)
- 50 records per page for optimal performance
- Bulk delete capability for administrators

**Behaviors:**
- `FormController`: Handles preview operations (read-only)
- `ListController`: Manages the feed list view

**Permissions:**
- `omsb.feeder.access_feeds`: Required to view activity feed

### List View Columns

The list view displays the following information:
- **ID**: Unique feed entry identifier
- **User**: Name of the user who performed the action
- **Action**: Type of action performed
- **Model Type**: Class name of the related model
- **Model ID**: ID of the related model record
- **Description**: Formatted description of the activity
- **Date**: Timestamp when the action occurred

### Preview View

The preview view displays all feed details in a read-only format:
- User information with full name and email
- Action type
- Related model type and ID
- Additional data (JSON formatted)
- Creation and update timestamps

## Database Structure

### Feeds Table (`omsb_feeder_feeds`)

- `id`: Primary key (BIGINT UNSIGNED AUTO_INCREMENT)
- `user_id`: Foreign key to backend_users (INT UNSIGNED, nullable)
- `action_type`: Type of action (VARCHAR(50), indexed)
- `feedable_type`: Model class name (VARCHAR(255), indexed)
- `feedable_id`: Model record ID (BIGINT UNSIGNED, indexed)
- `additional_data`: JSON field for extra information (nullable)
- `created_at`, `updated_at`: Timestamps
- `deleted_at`: Soft delete timestamp (indexed)

**Indexes:**
- `idx_feeds_feedable`: Composite index on (feedable_type, feedable_id) for polymorphic queries
- `idx_feeds_action_type`: Index on action_type for filtering
- `idx_feeds_user_id`: Index on user_id for user-specific queries
- `idx_feeds_created_at`: Index on created_at for date-based queries
- `idx_feeds_deleted_at`: Index on deleted_at for soft delete queries

**Foreign Keys:**
- `user_id` references `backend_users.id` with NULL ON DELETE

## Usage Examples

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

### Recording a Deletion Activity

```php
use Omsb\Feeder\Models\Feed;
use BackendAuth;

// When a Material Request is deleted
Feed::create([
    'user_id' => BackendAuth::getUser()->id,
    'action_type' => 'delete',
    'feedable_type' => MaterialRequest::class,
    'feedable_id' => $materialRequest->id,
    'additional_data' => [
        'document_number' => $materialRequest->document_number,
        'reason' => 'Cancelled by user request'
    ],
]);
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
- `delete`: Record deleted (soft delete)
- `approve`: Record approved in workflow
- `reject`: Record rejected in workflow
- `submit`: Record submitted for approval
- `review`: Record reviewed
- `complete`: Record marked as complete
- `cancel`: Record cancelled

## Development Guidelines

### When to Create Feed Entries

Create feed entries for:
1. **All CRUD operations**: create, update, delete
2. **Workflow transitions**: submit, approve, reject, complete
3. **Important business actions**: Stock transfer, Physical count, Payment processing
4. **Administrative actions**: User role changes, Permission updates

### When NOT to Create Feed Entries

Don't create feed entries for:
1. **Read operations**: Viewing records, generating reports
2. **System operations**: Automated cron jobs, system maintenance
3. **High-frequency actions**: API heartbeats, auto-save drafts
4. **Sensitive operations**: Password changes, authentication attempts (use security audit log instead)

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

5. **Create feed entries after successful operations**: Use database transactions to ensure feed entry is only created if the main operation succeeds

6. **Handle exceptions gracefully**: If feed creation fails, log the error but don't block the main operation

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
- Uses `SoftDelete` trait to preserve audit trail
- Implements polymorphic relationships via `$morphTo` property
- Provides query scopes for common filtering patterns

### Controller Architecture

The Feeds controller is read-only by design:
- Users cannot manually create or edit feed entries (these are created programmatically)
- Preview mode is used for viewing feed details
- List view provides search and filter capabilities
- Bulk delete is available for administrators to clean up old entries

### Security Considerations

1. **Permission-based access**: Only users with `omsb.feeder.access_feeds` permission can view feeds
2. **Soft deletes**: Feed entries are never permanently deleted to maintain audit trail integrity
3. **User attribution**: Every feed entry must have a valid user_id (except system operations)
4. **JSON sanitization**: additional_data field should not contain sensitive information like passwords

### Performance Considerations

1. **Indexes**: Multiple indexes ensure fast queries for common filter patterns
2. **Pagination**: Default 50 records per page balances usability and performance
3. **Eager loading**: Use `with('user', 'feedable')` to avoid N+1 queries
4. **Archival strategy**: Consider implementing periodic archival of old feed entries (>1 year) to a separate table

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
2. Verify the user has `omsb.feeder.access_feeds` permission
3. Check for soft deletes (deleted_at not null)
4. Verify the feedable model exists and is not deleted

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

The Feeder plugin provides a robust, centralized activity tracking system for all OMSB plugins. By following the usage guidelines and best practices outlined in this document, developers can ensure comprehensive audit trails across the entire application.
