# API Endpoints, Events & Hooks - Feeder Plugin

## Overview

The Feeder plugin is a **data-only plugin** with no HTTP API endpoints, REST routes, or AJAX handlers by design. It focuses on providing a model and database layer for activity tracking. This document covers the plugin's event system, integration hooks, and potential API extensions.

## HTTP API Endpoints

### Current State: None

The Feeder plugin does **NOT** provide any HTTP API endpoints.

**From Plugin.php:**
```php
// No routes.php file exists
// No API controllers defined
// No AJAX handlers registered
```

**Rationale:**
1. Feeds are system-generated and should not be created via external API
2. Feed immutability prevents updates/deletes via API
3. Feed data is sensitive audit information (internal use only)
4. Query operations should be done programmatically via Feed model

### Potential API Endpoints (Future Enhancement)

If API access is needed, consider these endpoints:

#### 1. GET /api/feeds

**Purpose:** Retrieve feeds for a specific model instance  
**Method:** GET  
**Authentication:** Required (Backend API token)  
**Permission:** `omsb.feeder.access_feeds`

**Request:**
```http
GET /api/feeds?feedable_type=Omsb\Procurement\Models\PurchaseRequest&feedable_id=190&limit=50
Authorization: Bearer <token>
```

**Response:**
```json
{
    "data": [
        {
            "id": 1,
            "user_id": 5,
            "user": {
                "id": 5,
                "first_name": "John",
                "last_name": "Doe",
                "full_name": "John Doe"
            },
            "action_type": "approve",
            "feedable_type": "Omsb\\Procurement\\Models\\PurchaseRequest",
            "feedable_id": 190,
            "title": "Purchase Request Approved",
            "body": null,
            "additional_data": {
                "document_number": "PR/HQ/2025/00190",
                "total_amount": 1057.00,
                "currency": "MYR",
                "status_from": "submitted",
                "status_to": "approved"
            },
            "created_at": "2025-01-15T10:30:00.000000Z",
            "updated_at": "2025-01-15T10:30:00.000000Z"
        }
    ],
    "meta": {
        "total": 25,
        "limit": 50
    }
}
```

**Implementation:**
```php
// routes.php
Route::group(['prefix' => 'api/v1', 'middleware' => 'api'], function() {
    Route::get('feeds', 'Omsb\Feeder\Http\Controllers\FeedController@index');
});

// FeedController.php
public function index(Request $request)
{
    $this->checkPermission('omsb.feeder.access_feeds');
    
    $feedableType = $request->input('feedable_type');
    $feedableId = $request->input('feedable_id');
    $limit = $request->input('limit', 50);
    
    $feeds = Feed::getForDocument($feedableType, $feedableId, $limit);
    
    return response()->json([
        'data' => $feeds,
        'meta' => [
            'total' => $feeds->count(),
            'limit' => $limit,
        ],
    ]);
}
```

#### 2. GET /api/feeds/{id}

**Purpose:** Retrieve a specific feed entry  
**Method:** GET  
**Authentication:** Required

**Request:**
```http
GET /api/feeds/123
Authorization: Bearer <token>
```

**Response:**
```json
{
    "data": {
        "id": 123,
        "user_id": 5,
        "user": {...},
        "action_type": "approve",
        "feedable_type": "Omsb\\Procurement\\Models\\PurchaseRequest",
        "feedable_id": 190,
        "title": "Purchase Request Approved",
        "body": null,
        "additional_data": {...},
        "created_at": "2025-01-15T10:30:00.000000Z",
        "updated_at": "2025-01-15T10:30:00.000000Z"
    }
}
```

#### 3. GET /api/feeds/export

**Purpose:** Export feeds to CSV/Excel  
**Method:** GET  
**Authentication:** Required  
**Permission:** `omsb.feeder.access_feeds`

**Request:**
```http
GET /api/feeds/export?feedable_type=...&feedable_id=...&format=csv
Authorization: Bearer <token>
```

**Response:** CSV file download

**Note:** All these endpoints are **NOT currently implemented**. See [09_improvements.md](09_improvements.md) for implementation proposals.

## Events

### Events Dispatched by Feeder

**Current State:** None

The Feeder plugin currently does not dispatch any events.

### Events Listened by Feeder

**Current State:** None

The Feeder plugin currently does not listen to any events.

### Recommended Event System (Enhancement)

#### 1. Feed Created Event

**Event:** `omsb.feeder.feed.created`  
**When:** After a feed entry is successfully created  
**Payload:** Feed model instance

**Potential Use Cases:**
- Send notifications when specific actions are logged
- Trigger webhooks for external integrations
- Update analytics/metrics
- Real-time activity streaming

**Implementation:**
```php
// In Feed model
protected static function boot()
{
    parent::boot();
    
    static::created(function ($feed) {
        Event::fire('omsb.feeder.feed.created', [$feed]);
    });
}

// In other plugins
Event::listen('omsb.feeder.feed.created', function($feed) {
    if ($feed->action_type === 'approve' && $feed->feedable_type === PurchaseRequest::class) {
        // Send approval notification
        Notification::send($feed);
    }
});
```

#### 2. Feed Query Event

**Event:** `omsb.feeder.feed.querying`  
**When:** Before feeds are retrieved (for filtering/modification)  
**Payload:** Query builder instance

**Use Case:** Allow other plugins to modify feed queries

**Implementation:**
```php
// In Feed model
public static function getForDocument(string $feedableType, int $feedableId, int $limit = 50)
{
    $query = static::where('feedable_type', $feedableType)
        ->where('feedable_id', $feedableId)
        ->with('user')
        ->orderBy('created_at', 'desc')
        ->limit($limit);
    
    Event::fire('omsb.feeder.feed.querying', [$query]);
    
    return $query->get();
}
```

## Inter-Plugin Hooks

### 1. Model Boot Hook

Other plugins can use model events to automatically create feeds:

```php
// In PurchaseRequest model
protected static function boot()
{
    parent::boot();
    
    // Automatically create feed on creation
    static::created(function ($model) {
        Feed::create([
            'user_id' => BackendAuth::getUser()->id,
            'action_type' => 'create',
            'feedable_type' => static::class,
            'feedable_id' => $model->id,
            'additional_data' => [
                'document_number' => $model->document_number,
            ],
        ]);
    });
    
    // Automatically create feed on update
    static::updated(function ($model) {
        Feed::create([
            'user_id' => BackendAuth::getUser()->id,
            'action_type' => 'update',
            'feedable_type' => static::class,
            'feedable_id' => $model->id,
        ]);
    });
}
```

**Note:** This approach is automatic but less flexible than service-layer creation.

### 2. Workflow Integration Hook

The Workflow plugin could automatically create feeds on status transitions:

```php
// In Workflow plugin
Event::listen('workflow.transition.after', function($workflowInstance) {
    Feed::create([
        'user_id' => BackendAuth::getUser()->id,
        'action_type' => 'status_change',
        'feedable_type' => $workflowInstance->workflowable_type,
        'feedable_id' => $workflowInstance->workflowable_id,
        'additional_data' => [
            'workflow_code' => $workflowInstance->workflow_code,
            'status_from' => $workflowInstance->from_status,
            'status_to' => $workflowInstance->to_status,
        ],
    ]);
});
```

**Status:** Not currently implemented. Recommended for future enhancement.

### 3. Backend User Hook

Automatically attribute actions to current user:

```php
// Helper function or trait
trait TracksFeedActivity
{
    protected function createFeed(array $data): Feed
    {
        return Feed::create(array_merge([
            'user_id' => BackendAuth::getUser()->id,
        ], $data));
    }
}

// Usage in service
class PurchaseRequestService
{
    use TracksFeedActivity;
    
    public function approve(PurchaseRequest $pr)
    {
        $this->createFeed([
            'action_type' => 'approve',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => $pr->id,
        ]);
    }
}
```

## AJAX Handlers

### Current State: None

The feed sidebar partial does not use AJAX handlers. It renders statically on page load.

### Potential AJAX Handlers (Enhancement)

#### 1. onLoadMoreFeeds

**Purpose:** Load additional feed items dynamically  
**When:** User clicks "Load More" button

**Implementation:**
```php
// In custom controller using feed sidebar
public function onLoadMoreFeeds()
{
    $feedableType = post('feedableType');
    $feedableId = post('feedableId');
    $offset = post('offset', 0);
    $limit = post('limit', 50);
    
    $feeds = Feed::where('feedable_type', $feedableType)
        ->where('feedable_id', $feedableId)
        ->with('user')
        ->orderBy('created_at', 'desc')
        ->offset($offset)
        ->limit($limit)
        ->get();
    
    return [
        '#feed-list' => $this->makePartial('feed_items', ['feeds' => $feeds]),
        'hasMore' => $feeds->count() === $limit,
    ];
}
```

#### 2. onRefreshFeeds

**Purpose:** Refresh feed list without page reload  
**When:** User clicks "Refresh" button or on timer

**Implementation:**
```php
public function onRefreshFeeds()
{
    $feedableType = post('feedableType');
    $feedableId = post('feedableId');
    $limit = post('limit', 50);
    
    $feeds = Feed::getForDocument($feedableType, $feedableId, $limit);
    
    return [
        '#feed-sidebar-content' => $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
            'feedableType' => $feedableType,
            'feedableId' => $feedableId,
            'limit' => $limit,
        ]),
    ];
}
```

#### 3. onFilterFeeds

**Purpose:** Filter feeds by action type, user, or date range  
**When:** User selects filter options

**Implementation:**
```php
public function onFilterFeeds()
{
    $feedableType = post('feedableType');
    $feedableId = post('feedableId');
    $actionType = post('actionType');
    $userId = post('userId');
    $dateFrom = post('dateFrom');
    $dateTo = post('dateTo');
    
    $query = Feed::where('feedable_type', $feedableType)
        ->where('feedable_id', $feedableId);
    
    if ($actionType) {
        $query->where('action_type', $actionType);
    }
    
    if ($userId) {
        $query->where('user_id', $userId);
    }
    
    if ($dateFrom) {
        $query->where('created_at', '>=', $dateFrom);
    }
    
    if ($dateTo) {
        $query->where('created_at', '<=', $dateTo);
    }
    
    $feeds = $query->with('user')
        ->orderBy('created_at', 'desc')
        ->limit(100)
        ->get();
    
    return [
        '#feed-list' => $this->makePartial('feed_items', ['feeds' => $feeds]),
    ];
}
```

**Note:** These AJAX handlers are **NOT currently implemented**. See [09_improvements.md](09_improvements.md).

## Console Commands

### Current State: None

The Feeder plugin does not provide any console commands.

### Potential Console Commands (Enhancement)

#### 1. feed:archive

**Purpose:** Archive old feed entries to separate table  
**Usage:** `php artisan feed:archive --days=365`

**Implementation:**
```php
<?php namespace Omsb\Feeder\Console;

use Illuminate\Console\Command;
use Omsb\Feeder\Models\Feed;
use Omsb\Feeder\Models\FeedArchive;

class ArchiveFeeds extends Command
{
    protected $signature = 'feed:archive {--days=365 : Archive feeds older than N days}';
    protected $description = 'Archive old feed entries to separate table';
    
    public function handle()
    {
        $days = $this->option('days');
        $cutoffDate = now()->subDays($days);
        
        $feeds = Feed::where('created_at', '<', $cutoffDate)->get();
        
        $this->info("Archiving {$feeds->count()} feeds older than $days days...");
        
        foreach ($feeds as $feed) {
            FeedArchive::create($feed->toArray());
            $feed->delete(); // Requires removing beforeDelete protection
        }
        
        $this->info("Archived {$feeds->count()} feeds successfully.");
    }
}
```

#### 2. feed:cleanup

**Purpose:** Remove duplicate or invalid feed entries  
**Usage:** `php artisan feed:cleanup --dry-run`

**Implementation:**
```php
<?php namespace Omsb\Feeder\Console;

use Illuminate\Console\Command;
use Omsb\Feeder\Models\Feed;

class CleanupFeeds extends Command
{
    protected $signature = 'feed:cleanup {--dry-run : Show what would be cleaned without actually doing it}';
    protected $description = 'Remove duplicate or invalid feed entries';
    
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        // Find feeds with null feedable (orphaned)
        $orphaned = Feed::whereNull('feedable_id')
            ->orWhere('feedable_id', 0)
            ->get();
        
        $this->info("Found {$orphaned->count()} orphaned feeds.");
        
        if (!$dryRun) {
            // Remove orphaned feeds
            $orphaned->each->delete();
            $this->info("Cleaned up {$orphaned->count()} orphaned feeds.");
        }
    }
}
```

#### 3. feed:report

**Purpose:** Generate activity report  
**Usage:** `php artisan feed:report --model=PurchaseRequest --days=30`

**Implementation:**
```php
<?php namespace Omsb\Feeder\Console;

use Illuminate\Console\Command;
use Omsb\Feeder\Models\Feed;

class GenerateReport extends Command
{
    protected $signature = 'feed:report 
                            {--model= : Filter by model class}
                            {--days=30 : Report for last N days}
                            {--format=table : Output format (table, json, csv)}';
    protected $description = 'Generate activity report from feeds';
    
    public function handle()
    {
        $model = $this->option('model');
        $days = $this->option('days');
        $format = $this->option('format');
        
        $query = Feed::where('created_at', '>=', now()->subDays($days));
        
        if ($model) {
            $query->where('feedable_type', $model);
        }
        
        $stats = $query->selectRaw('action_type, COUNT(*) as count')
            ->groupBy('action_type')
            ->get();
        
        if ($format === 'table') {
            $this->table(['Action Type', 'Count'], $stats->map(function($stat) {
                return [$stat->action_type, $stat->count];
            })->toArray());
        } else if ($format === 'json') {
            $this->line($stats->toJson(JSON_PRETTY_PRINT));
        }
    }
}
```

## Webhooks

### Current State: None

The Feeder plugin does not support webhooks.

### Potential Webhook System (Enhancement)

**Purpose:** Notify external systems when specific actions occur

**Implementation:**
```php
// In config/feeder.php
return [
    'webhooks' => [
        'enabled' => env('FEEDER_WEBHOOKS_ENABLED', false),
        'endpoints' => [
            [
                'url' => env('FEEDER_WEBHOOK_URL'),
                'actions' => ['approve', 'reject'],
                'models' => ['Omsb\Procurement\Models\PurchaseRequest'],
            ],
        ],
    ],
];

// In Feed model
protected static function boot()
{
    parent::boot();
    
    static::created(function ($feed) {
        if (config('feeder.webhooks.enabled')) {
            WebhookService::dispatch($feed);
        }
    });
}
```

## GraphQL API

### Current State: None

The Feeder plugin does not provide a GraphQL API.

### Potential GraphQL Schema (Enhancement)

```graphql
type Feed {
  id: ID!
  userId: Int
  user: User
  actionType: String!
  feedableType: String!
  feedableId: Int!
  title: String
  body: String
  additionalData: JSON
  createdAt: DateTime!
  updatedAt: DateTime!
}

type Query {
  feeds(
    feedableType: String!
    feedableId: Int!
    limit: Int = 50
    offset: Int = 0
  ): [Feed!]!
  
  feed(id: ID!): Feed
}

type Subscription {
  feedCreated(
    feedableType: String
    feedableId: Int
  ): Feed!
}
```

## Integration Summary

| Feature | Status | Implementation Effort |
|---------|--------|----------------------|
| HTTP REST API | ❌ Not Implemented | Medium |
| GraphQL API | ❌ Not Implemented | Large |
| Events (Dispatched) | ❌ Not Implemented | Small |
| Events (Listened) | ❌ Not Implemented | Small |
| AJAX Handlers | ❌ Not Implemented | Medium |
| Console Commands | ❌ Not Implemented | Medium |
| Webhooks | ❌ Not Implemented | Medium |
| Real-time (WebSockets) | ❌ Not Implemented | Large |

## Security Considerations

### API Security (If Implemented)

1. **Authentication Required**: All API endpoints must require authentication
2. **Permission Checks**: Enforce `omsb.feeder.access_feeds` permission
3. **Rate Limiting**: Prevent abuse with rate limiting
4. **Input Validation**: Validate all query parameters
5. **Output Filtering**: Don't expose sensitive data in responses

### Event Security

1. **Event Payload Sanitization**: Don't include sensitive data in event payloads
2. **Listener Authorization**: Listeners should verify permissions before acting
3. **Exception Handling**: Prevent event listener failures from exposing system details

### Webhook Security

1. **HMAC Signatures**: Sign webhook payloads for verification
2. **HTTPS Only**: Require HTTPS for webhook endpoints
3. **Retry Logic**: Implement exponential backoff for failed deliveries
4. **Timeout**: Set reasonable timeout for webhook requests

## Conclusion

The Feeder plugin currently operates as a **data-layer plugin** with no external API surface. This design prioritizes:

1. **Simplicity**: Easy to integrate and use
2. **Security**: No external attack surface
3. **Flexibility**: Programmatic access via Feed model
4. **Performance**: No HTTP overhead for internal operations

**Future enhancements** could add REST API, events, AJAX handlers, and console commands to provide more integration options while maintaining security and performance.

---

**Previous:** [← Components Guide](02_components.md) | **Next:** [Models & Services →](04_models_services.md)
