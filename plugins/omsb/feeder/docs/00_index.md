# Feeder Plugin - Complete Documentation

## Overview

The **Feeder Plugin** is a centralized activity tracking system for OctoberCMS that logs all user actions across the OMSB ERP application. It provides a unified, immutable audit trail that eliminates the need for per-plugin activity logging implementations.

**Version:** 1.0.2  
**Namespace:** `Omsb\Feeder`  
**Dependencies:** `Omsb.Organization`  
**Location:** `/plugins/omsb/feeder`

## Purpose

The Feeder plugin serves as a **system-wide activity logging service** that:

1. **Tracks User Actions**: Records who did what, when, and on which record
2. **Provides Audit Trail**: Immutable records that cannot be edited or deleted
3. **Supports Polymorphic Relationships**: Can track activities on any model in the system
4. **Enables Activity Visualization**: Includes a reusable sidebar partial for displaying activity feeds
5. **Stores Rich Metadata**: JSON field for action-specific details (amounts, status transitions, etc.)

## Key Features

### ✅ Centralized Activity Logging
- Single source of truth for all user activities across OMSB plugins
- Eliminates duplication of logging logic in individual plugins
- Consistent audit trail format across the application

### ✅ Immutable Records
- Feed entries cannot be modified once created (`beforeUpdate()` throws exception)
- Feed entries cannot be deleted (`beforeDelete()` returns false)
- Ensures audit trail integrity and compliance

### ✅ Polymorphic Relationships
- Uses Laravel's `morphTo` relationship for maximum flexibility
- Any model can have associated feed entries
- Single table stores activities for all document types

### ✅ Rich Metadata Support
- JSON `additional_data` field stores action-specific information
- Status transitions (from → to)
- Amounts and currencies
- Document numbers
- Custom business data

### ✅ Reusable UI Component
- Ready-to-use sidebar partial (`_feed_sidebar.htm`)
- Timeline view with user avatars
- Color-coded action badges
- Status transition visualization
- Responsive design for 300-400px sidebars

### ✅ Query Optimization
- Multiple indexes for fast filtering
- Query scopes for common patterns (`actionType()`, `feedableType()`, `byUser()`)
- Eager loading support for related models
- Static `getForDocument()` method for convenient retrieval

## Technical Boundaries

### What the Plugin Does
- Stores activity logs in the `omsb_feeder_feeds` table
- Provides the `Feed` model with validation and relationships
- Offers a reusable sidebar partial for displaying feeds
- Enforces immutability at the model level
- Indexes data for efficient querying

### What the Plugin Does NOT Do
- Does not provide backend navigation or controller (by design)
- Does not automatically track activities (must be explicitly logged)
- Does not provide real-time updates (requires page refresh)
- Does not include filtering UI or search interface
- Does not have API endpoints or frontend components
- Does not send notifications (logging only)

## Quick Start

### 1. Basic Feed Creation

```php
use Omsb\Feeder\Models\Feed;
use BackendAuth;

// Log a simple action
Feed::create([
    'user_id' => BackendAuth::getUser()->id,
    'action_type' => 'create',
    'feedable_type' => PurchaseRequest::class,
    'feedable_id' => $purchaseRequest->id,
]);
```

### 2. Feed with Metadata

```php
// Log with rich metadata
Feed::create([
    'user_id' => BackendAuth::getUser()->id,
    'action_type' => 'approve',
    'feedable_type' => PurchaseOrder::class,
    'feedable_id' => $po->id,
    'additional_data' => [
        'status_from' => 'submitted',
        'status_to' => 'approved',
        'total_amount' => $po->total_amount,
        'currency' => 'MYR',
    ],
]);
```

### 3. Feed with Title and Body

```php
// Log a comment or note
Feed::create([
    'user_id' => BackendAuth::getUser()->id,
    'action_type' => 'comment',
    'feedable_type' => PurchaseRequest::class,
    'feedable_id' => $pr->id,
    'title' => 'Budget Approval Required',
    'body' => 'This request needs additional budget approval before proceeding.',
]);
```

### 4. Display Feed Sidebar in Backend

```php
<!-- In your controller update.php or preview.php -->
<div class="layout-cell layout-sidebar" style="width: 350px;">
    <?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
        'feedableType' => get_class($formModel),
        'feedableId' => $formModel->id,
        'title' => 'Activity Feed',
        'limit' => 50,
    ]) ?>
</div>
```

### 5. Query Feeds

```php
// Get all feeds for a document
$feeds = Feed::getForDocument(PurchaseRequest::class, $prId, 50);

// Filter by action type
$approvals = Feed::actionType('approve')->get();

// Filter by user
$userActivities = Feed::byUser($userId)->get();

// Complex query
$recentApprovals = Feed::actionType('approve')
    ->where('created_at', '>=', now()->subDays(7))
    ->with('user', 'feedable')
    ->orderBy('created_at', 'desc')
    ->get();
```

## Capabilities

### Activity Types Supported
- **CRUD Operations**: create, update, delete
- **Workflow Actions**: submit, approve, reject, review, complete, cancel
- **Special Actions**: comment, verify, recommend
- **Custom Actions**: Any string up to 50 characters

### Models with Feed Integration
Currently used by:
- **Procurement**: PurchaseRequest, PurchaseOrder, VendorQuotation, GoodsReceiptNote, DeliveryOrder
- **Inventory**: MaterialReceivedNote (MRN), MaterialRequestIssuance (MRI), StockAdjustment, StockTransfer, PhysicalCount
- **Budget**: Budget, BudgetTransfer, BudgetAdjustment, BudgetReallocation
- **Organization**: Company, Site, Staff (potential)

### Data Stored Per Feed
| Field | Type | Description |
|-------|------|-------------|
| `id` | BIGINT | Primary key |
| `user_id` | INT (nullable) | Backend user who performed action |
| `action_type` | VARCHAR(50) | Type of action performed |
| `feedable_type` | VARCHAR(255) | Fully qualified class name of related model |
| `feedable_id` | BIGINT | ID of related model record |
| `title` | VARCHAR(255) (nullable) | Optional title for notes/comments |
| `body` | TEXT (nullable) | Optional body text for notes/comments |
| `additional_data` | JSON (nullable) | Custom metadata |
| `created_at` | TIMESTAMP | When action occurred |
| `updated_at` | TIMESTAMP | Last modification (rarely changed) |

## Documentation Structure

This documentation is organized as follows:

- **[00_index.md](00_index.md)** (this file) - Overview and quick start
- **[01_integration.md](01_integration.md)** - Cross-plugin integration patterns and diagrams
- **[02_components.md](02_components.md)** - Backend components, partials, and UI elements
- **[03_api_endpoints.md](03_api_endpoints.md)** - API routes, events, and inter-plugin hooks
- **[04_models_services.md](04_models_services.md)** - Model architecture, relationships, and queries
- **[05_backend_usage.md](05_backend_usage.md)** - YAML configurations and backend integration
- **[06_dev_notes.md](06_dev_notes.md)** - Developer comments, TODOs, and implementation notes
- **[07_code_review.md](07_code_review.md)** - Code review findings and recommendations
- **[08_tests_suggestions.md](08_tests_suggestions.md)** - Test coverage and test scenarios
- **[09_improvements.md](09_improvements.md)** - Future enhancements and roadmap
- **[10_automation.md](10_automation.md)** - CI/CD and automation opportunities
- **[assets/](assets/)** - Diagrams, schemas, and visual documentation

## Use Cases

### 1. Document Approval Workflow
Track the complete approval lifecycle of a purchase request:
```
[User A] creates PR → [User B] reviews → [Manager C] approves → [Finance D] verifies
```

### 2. Inventory Movement Tracking
Log every stock transaction for audit trail:
```
Receive goods → Issue to department → Transfer between warehouses → Adjust stock
```

### 3. Budget Management
Track budget allocation changes and approvals:
```
Create budget → Adjust allocation → Transfer between categories → Approve changes
```

### 4. Compliance and Auditing
Retrieve complete history of any document for:
- Internal audits
- External compliance reviews
- Dispute resolution
- Performance analysis

## Getting Started

1. **Read** [01_integration.md](01_integration.md) to understand how Feeder integrates with other plugins
2. **Review** [04_models_services.md](04_models_services.md) to learn about the Feed model and its methods
3. **Study** [02_components.md](02_components.md) to implement the feed sidebar in your controllers
4. **Follow** [05_backend_usage.md](05_backend_usage.md) for YAML configuration examples
5. **Check** [07_code_review.md](07_code_review.md) for best practices and common pitfalls

## Support and Contribution

### Existing Resources
- **README.md**: Basic overview and usage examples
- **IMPLEMENTATION_SUMMARY.md**: Technical implementation details
- **FEATURE_DEMO.md**: Visual representation and features
- **USAGE_EXAMPLE.md**: Complete integration example with Purchase Request
- **verify_sidebar.php**: Verification script for testing installation

### Best Practices
1. Always log activities in database transactions
2. Use `BackendAuth::getUser()->id` for user attribution
3. Store relevant context in `additional_data`
4. Use fully qualified class names for `feedable_type`
5. Create feeds after successful operations, not before
6. Handle feed creation failures gracefully (don't block main operation)

## Performance Considerations

- **Indexed Fields**: Multiple indexes for fast queries
- **Eager Loading**: Use `with('user', 'feedable')` to avoid N+1 queries
- **Limit Results**: Default limit of 50 items prevents overwhelming queries
- **Archival Strategy**: Consider archiving old feeds (>1 year) for large datasets
- **No Real-Time**: Designed for historical audit, not real-time streaming

## Security Considerations

- **Immutability**: Prevents tampering with audit trail
- **Permission-Based**: `omsb.feeder.access_feeds` permission for programmatic access
- **User Attribution**: Every feed should have valid `user_id` (except system actions)
- **JSON Sanitization**: Don't store sensitive data (passwords, tokens) in `additional_data`
- **Protected Methods**: `beforeUpdate()` and `beforeDelete()` enforce immutability

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.1 | - | Initial version |
| 1.0.2 | - | Added feeds table with full schema |

## License

Part of the OMSB ERP system. See root LICENSE.md for details.

---

**Next:** [Integration Guide →](01_integration.md)
