# Models, Services & Database - Feeder Plugin

## Overview

This document provides comprehensive documentation of the Feed model, its relationships, query methods, database schema, and recommended service patterns.

## Feed Model

**File:** `/plugins/omsb/feeder/models/Feed.php`  
**Lines:** 169  
**Namespace:** `Omsb\Feeder\Models`  
**Extends:** `October\Rain\Database\Model`  
**Traits:** `October\Rain\Database\Traits\Validation`

### Model Properties

```php
<?php namespace Omsb\Feeder\Models;

use Model;

class Feed extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /** @var string table name */
    public $table = 'omsb_feeder_feeds';

    /** @var array fillable fields */
    protected $fillable = [
        'user_id',
        'action_type',
        'feedable_type',
        'feedable_id',
        'title',
        'body',
        'additional_data',
    ];

    /** @var array attributes converted to null when empty */
    protected $nullable = [
        'user_id',
        'feedable_id',
        'title',
        'body',
    ];

    /** @var array jsonable fields */
    protected $jsonable = ['additional_data'];

    /** @var array validation rules */
    public $rules = [
        'action_type' => 'required|string|max:50',
        'feedable_type' => 'required|string|max:255',
        'feedable_id' => 'required|integer',
    ];

    /** @var array dates used by the model */
    protected $dates = [];
}
```

### Relationships

#### 1. morphTo: feedable

**Type:** Polymorphic (morphTo)  
**Related:** Any model  
**Purpose:** Links feed to the model that was acted upon

```php
public $morphTo = [
    'feedable' => []
];
```

**Usage:**
```php
$feed = Feed::find(1);
$model = $feed->feedable; // Returns PurchaseRequest, Budget, etc.
```

**Database Columns:**
- `feedable_type`: Fully qualified class name (e.g., `Omsb\Procurement\Models\PurchaseRequest`)
- `feedable_id`: ID of the related model

#### 2. belongsTo: user

**Type:** One-to-One (belongsTo)  
**Related:** `Backend\Models\User`  
**Purpose:** Links feed to the backend user who performed the action

```php
public $belongsTo = [
    'user' => [\Backend\Models\User::class]
];
```

**Usage:**
```php
$feed = Feed::find(1);
$user = $feed->user; // Returns Backend\Models\User instance
$userName = $feed->user->full_name;
```

**Database Column:**
- `user_id`: Foreign key to `backend_users.id` (INT UNSIGNED, nullable)

### Model Accessors

#### getDescriptionAttribute()

**Purpose:** Returns formatted description of feed action  
**Return:** `string`  
**Location:** Lines 85-92

```php
public function getDescriptionAttribute(): string
{
    $userName = $this->user 
        ? $this->user->first_name . ' ' . $this->user->last_name 
        : 'System';
    $action = $this->action_type;
    $modelName = class_basename($this->feedable_type);
    
    return "{$userName} {$action} {$modelName}";
}
```

**Examples:**
- `"John Doe created PurchaseRequest"`
- `"Siti Nurbaya approved Budget"`
- `"System updated StockAdjustment"`

**Usage:**
```php
$feed = Feed::find(1);
echo $feed->description; // Accessor automatically invoked
```

### Query Scopes

#### scopeActionType($query, string $actionType)

**Purpose:** Filter feeds by action type  
**Location:** Lines 101-104

```php
public function scopeActionType($query, string $actionType)
{
    return $query->where('action_type', $actionType);
}
```

**Usage:**
```php
// Get all approval actions
$approvals = Feed::actionType('approve')->get();

// Get all create actions from last week
$recentCreates = Feed::actionType('create')
    ->where('created_at', '>=', now()->subWeek())
    ->get();
```

#### scopeFeedableType($query, string $feedableType)

**Purpose:** Filter feeds by model type  
**Location:** Lines 113-116

```php
public function scopeFeedableType($query, string $feedableType)
{
    return $query->where('feedable_type', $feedableType);
}
```

**Usage:**
```php
// Get all feeds for Purchase Requests
$prFeeds = Feed::feedableType('Omsb\Procurement\Models\PurchaseRequest')->get();

// Or with class constant
use Omsb\Procurement\Models\PurchaseRequest;
$prFeeds = Feed::feedableType(PurchaseRequest::class)->get();
```

#### scopeByUser($query, int $userId)

**Purpose:** Filter feeds by user  
**Location:** Lines 125-128

```php
public function scopeByUser($query, int $userId)
{
    return $query->where('user_id', $userId);
}
```

**Usage:**
```php
// Get all actions by user ID 5
$userFeeds = Feed::byUser(5)->get();

// Get current user's recent actions
$myRecentActions = Feed::byUser(BackendAuth::getUser()->id)
    ->where('created_at', '>=', now()->subDays(7))
    ->orderBy('created_at', 'desc')
    ->get();
```

### Model Events

#### beforeDelete()

**Purpose:** Prevents deletion of feed records (immutability enforcement)  
**Return:** `false` (always)  
**Location:** Lines 135-138

```php
public function beforeDelete()
{
    return false;
}
```

**Behavior:**
```php
$feed = Feed::find(1);
$result = $feed->delete(); // Returns false, record NOT deleted

// Feed still exists in database
$feed->fresh(); // Returns the feed (not deleted)
```

**Reason:** Ensures audit trail integrity by preventing deletion.

#### beforeUpdate()

**Purpose:** Prevents modification of existing feed records  
**Throws:** `Exception` if attempting to modify  
**Location:** Lines 145-150

```php
public function beforeUpdate()
{
    if ($this->exists && $this->isDirty()) {
        throw new \Exception('Feed records cannot be modified once created.');
    }
}
```

**Behavior:**
```php
// Creating new feed - OK
$feed = Feed::create([...]);

// Attempting to modify existing feed - THROWS EXCEPTION
$feed->action_type = 'different_action';
$feed->save(); // Exception: "Feed records cannot be modified once created."
```

**Reason:** Ensures audit trail accuracy by preventing tampering.

### Static Methods

#### getForDocument($feedableType, $feedableId, $limit = 50)

**Purpose:** Convenience method to retrieve feeds for a specific model  
**Return:** `Illuminate\Database\Eloquent\Collection`  
**Location:** Lines 160-168

```php
public static function getForDocument(string $feedableType, int $feedableId, int $limit = 50)
{
    return static::where('feedable_type', $feedableType)
        ->where('feedable_id', $feedableId)
        ->with('user')
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get();
}
```

**Parameters:**
- `$feedableType` (string): Fully qualified class name
- `$feedableId` (int): Model instance ID
- `$limit` (int, optional): Maximum records to return (default: 50)

**Usage:**
```php
use Omsb\Feeder\Models\Feed;
use Omsb\Procurement\Models\PurchaseRequest;

// Get feeds for a Purchase Request
$feeds = Feed::getForDocument(PurchaseRequest::class, 190, 50);

// Feeds are already ordered by created_at desc
// User relationship is eager-loaded
foreach ($feeds as $feed) {
    echo $feed->user->full_name; // No N+1 query
    echo $feed->action_type;
    echo $feed->created_at->diffForHumans();
}
```

**Benefits:**
- Consistent query pattern
- Eager loads user relationship (avoids N+1)
- Optimized with limit
- Ordered chronologically (newest first)

## Database Schema

### Table: omsb_feeder_feeds

**Migration File:** `/plugins/omsb/feeder/updates/create_feeds_table.php`  
**Lines:** 65  
**Engine:** InnoDB

#### Columns

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary key |
| `user_id` | INT UNSIGNED | Yes | NULL | FK to backend_users.id |
| `action_type` | VARCHAR(50) | No | - | Type of action performed |
| `feedable_type` | VARCHAR(255) | No | - | Model class name (polymorphic) |
| `feedable_id` | BIGINT UNSIGNED | No | - | Model ID (polymorphic) |
| `title` | VARCHAR(255) | Yes | NULL | Optional title for notes |
| `body` | TEXT | Yes | NULL | Optional body for comments |
| `additional_data` | JSON | Yes | NULL | Extra metadata |
| `created_at` | TIMESTAMP | No | - | When action occurred |
| `updated_at` | TIMESTAMP | No | - | Last update timestamp |

#### Indexes

| Index Name | Columns | Type | Purpose |
|------------|---------|------|---------|
| PRIMARY | `id` | Primary Key | Unique identifier |
| `idx_feeds_feedable` | `feedable_type`, `feedable_id` | Composite | Fast polymorphic queries |
| `idx_feeds_action_type` | `action_type` | Single | Filter by action type |
| `idx_feeds_user_id` | `user_id` | Single | Filter by user |
| `idx_feeds_created_at` | `created_at` | Single | Date-based queries |

#### Foreign Keys

| Constraint | Columns | References | On Delete |
|------------|---------|------------|-----------|
| FK_feeds_user | `user_id` | `backend_users(id)` | SET NULL |

**Note:** Uses `unsignedInteger('user_id')` (INT UNSIGNED) to match `backend_users.id` type.

#### Migration Code

```php
Schema::create('omsb_feeder_feeds', function(Blueprint $table) {
    $table->engine = 'InnoDB';
    
    $table->id();
    
    // Action type
    $table->string('action_type', 50)->index();
    
    // Polymorphic relationship
    $table->string('feedable_type', 255)->index();
    $table->unsignedBigInteger('feedable_id')->index();
    
    // Title and body for notes/comments
    $table->string('title')->nullable();
    $table->text('body')->nullable();
    
    // Additional data as JSON
    $table->json('additional_data')->nullable();
    
    $table->timestamps();
    
    // Foreign key - Backend user relationship
    $table->unsignedInteger('user_id')->nullable();
    $table->foreign('user_id')
        ->references('id')
        ->on('backend_users')
        ->nullOnDelete();
    
    // Composite index for polymorphic queries
    $table->index(['feedable_type', 'feedable_id'], 'idx_feeds_feedable');
    $table->index('action_type', 'idx_feeds_action_type');
    $table->index('user_id', 'idx_feeds_user_id');
    $table->index('created_at', 'idx_feeds_created_at');
});
```

## Service Layer Patterns

The Feeder plugin does not include dedicated service classes, but provides patterns for service-layer integration.

### Recommended Service Pattern

```php
<?php namespace Omsb\Procurement\Services;

use Omsb\Procurement\Models\PurchaseRequest;
use Omsb\Feeder\Models\Feed;
use BackendAuth;
use Db;
use Exception;
use Log;

class PurchaseRequestService
{
    /**
     * Create a new purchase request with feed tracking
     */
    public function create(array $data): PurchaseRequest
    {
        return Db::transaction(function () use ($data) {
            // 1. Create the model
            $pr = PurchaseRequest::create($data);
            
            // 2. Create feed entry
            try {
                Feed::create([
                    'user_id' => BackendAuth::getUser()->id,
                    'action_type' => 'create',
                    'feedable_type' => PurchaseRequest::class,
                    'feedable_id' => $pr->id,
                    'additional_data' => [
                        'document_number' => $pr->document_number,
                        'total_amount' => $pr->total_amount,
                    ],
                ]);
            } catch (Exception $e) {
                // Log error but don't fail main operation
                Log::error('Failed to create feed: ' . $e->getMessage());
            }
            
            return $pr;
        });
    }
    
    /**
     * Approve purchase request with feed tracking
     */
    public function approve(PurchaseRequest $pr, string $comments = null): bool
    {
        return Db::transaction(function () use ($pr, $comments) {
            $oldStatus = $pr->status;
            
            // 1. Update model
            $pr->status = 'approved';
            $pr->approved_by = BackendAuth::getUser()->id;
            $pr->approved_at = now();
            $pr->save();
            
            // 2. Create feed entry
            Feed::create([
                'user_id' => BackendAuth::getUser()->id,
                'action_type' => 'approve',
                'feedable_type' => PurchaseRequest::class,
                'feedable_id' => $pr->id,
                'title' => 'Purchase Request Approved',
                'body' => $comments,
                'additional_data' => [
                    'document_number' => $pr->document_number,
                    'total_amount' => $pr->total_amount,
                    'status_from' => $oldStatus,
                    'status_to' => 'approved',
                ],
            ]);
            
            return true;
        });
    }
    
    /**
     * Add comment to purchase request
     */
    public function addComment(PurchaseRequest $pr, string $title, string $body): void
    {
        Feed::create([
            'user_id' => BackendAuth::getUser()->id,
            'action_type' => 'comment',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => $pr->id,
            'title' => $title,
            'body' => $body,
        ]);
    }
}
```

### TracksFeedActivity Trait (Reusable Pattern)

```php
<?php namespace Omsb\Feeder\Traits;

use Omsb\Feeder\Models\Feed;
use BackendAuth;

trait TracksFeedActivity
{
    /**
     * Create a feed entry with automatic user attribution
     */
    protected function createFeed(array $data): Feed
    {
        return Feed::create(array_merge([
            'user_id' => BackendAuth::getUser()?->id,
        ], $data));
    }
    
    /**
     * Create feed for model action
     */
    protected function trackModelAction($model, string $action, array $additionalData = []): Feed
    {
        return $this->createFeed([
            'action_type' => $action,
            'feedable_type' => get_class($model),
            'feedable_id' => $model->id,
            'additional_data' => $additionalData,
        ]);
    }
}
```

**Usage:**
```php
class PurchaseRequestService
{
    use TracksFeedActivity;
    
    public function approve(PurchaseRequest $pr)
    {
        $pr->status = 'approved';
        $pr->save();
        
        $this->trackModelAction($pr, 'approve', [
            'status_from' => 'submitted',
            'status_to' => 'approved',
        ]);
    }
}
```

## Query Examples

### Basic Queries

```php
use Omsb\Feeder\Models\Feed;

// Get all feeds
$allFeeds = Feed::all();

// Get feeds with user
$feedsWithUser = Feed::with('user')->get();

// Get feeds ordered by date
$recentFeeds = Feed::orderBy('created_at', 'desc')->limit(10)->get();
```

### Filtered Queries

```php
// Get all approval actions
$approvals = Feed::where('action_type', 'approve')->get();

// Get feeds for specific model type
$prFeeds = Feed::where('feedable_type', 'Omsb\Procurement\Models\PurchaseRequest')->get();

// Get feeds by user
$userFeeds = Feed::where('user_id', 5)->get();

// Get feeds for last 7 days
$recentFeeds = Feed::where('created_at', '>=', now()->subDays(7))->get();
```

### Combined Queries

```php
// Get recent approvals for Purchase Requests
$prApprovals = Feed::where('action_type', 'approve')
    ->where('feedable_type', 'Omsb\Procurement\Models\PurchaseRequest')
    ->where('created_at', '>=', now()->subMonth())
    ->with('user')
    ->orderBy('created_at', 'desc')
    ->get();

// Get all actions by current user on a specific model
$myActions = Feed::where('user_id', BackendAuth::getUser()->id)
    ->where('feedable_type', PurchaseRequest::class)
    ->where('feedable_id', 190)
    ->orderBy('created_at', 'desc')
    ->get();
```

### Scope Queries

```php
// Using scopes
$approvals = Feed::actionType('approve')->get();
$prFeeds = Feed::feedableType(PurchaseRequest::class)->get();
$userFeeds = Feed::byUser(5)->get();

// Combining scopes
$userApprovals = Feed::byUser(5)
    ->actionType('approve')
    ->orderBy('created_at', 'desc')
    ->get();
```

### Aggregation Queries

```php
// Count feeds by action type
$stats = Feed::selectRaw('action_type, COUNT(*) as count')
    ->groupBy('action_type')
    ->get();

// Count feeds by user
$userStats = Feed::selectRaw('user_id, COUNT(*) as count')
    ->groupBy('user_id')
    ->orderBy('count', 'desc')
    ->get();

// Daily feed count for last 30 days
$dailyStats = Feed::selectRaw('DATE(created_at) as date, COUNT(*) as count')
    ->where('created_at', '>=', now()->subDays(30))
    ->groupBy('date')
    ->orderBy('date')
    ->get();
```

## Performance Optimization

### Eager Loading

Always eager load relationships to avoid N+1 queries:

```php
// ❌ BAD - N+1 query problem
$feeds = Feed::all();
foreach ($feeds as $feed) {
    echo $feed->user->full_name; // New query for each feed
}

// ✅ GOOD - Single query with eager loading
$feeds = Feed::with('user')->get();
foreach ($feeds as $feed) {
    echo $feed->user->full_name; // No additional query
}
```

### Query Optimization

Use indexes efficiently:

```php
// ✅ GOOD - Uses composite index
$feeds = Feed::where('feedable_type', PurchaseRequest::class)
    ->where('feedable_id', 190)
    ->get();

// ✅ GOOD - Uses action_type index
$feeds = Feed::where('action_type', 'approve')->get();

// ⚠️ CAUTION - May not use index (LIKE on indexed column)
$feeds = Feed::where('action_type', 'LIKE', '%approve%')->get();
```

### Limit Results

Always limit large result sets:

```php
// ✅ GOOD
$recentFeeds = Feed::orderBy('created_at', 'desc')
    ->limit(100)
    ->get();

// ❌ BAD - Loads all feeds (could be thousands)
$allFeeds = Feed::all();
```

## Console Commands

No console commands provided. See [03_api_endpoints.md](03_api_endpoints.md) for potential command implementations.

## Facades

No facades provided. Feed model is accessed directly:

```php
use Omsb\Feeder\Models\Feed;

Feed::create([...]);
```

## Conclusion

The Feed model provides a simple, robust foundation for activity tracking with:

- **Immutability**: Enforced via model events
- **Relationships**: Polymorphic and belongsTo
- **Query Scopes**: Convenient filtering
- **Optimized Schema**: Multiple indexes for fast queries
- **Service Patterns**: Recommended integration approaches

---

**Previous:** [← API Endpoints](03_api_endpoints.md) | **Next:** [Backend Usage →](05_backend_usage.md)
