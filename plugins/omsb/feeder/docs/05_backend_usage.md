# Backend Usage & YAML Configuration - Feeder Plugin

## Overview

This document details how to use the Feeder plugin in OctoberCMS backend interfaces, including YAML configurations, form/list integration, and practical implementation patterns.

## YAML Configuration Files

### fields.yaml

**File:** `/plugins/omsb/feeder/models/feed/fields.yaml`  
**Purpose:** Define form fields for displaying feed records  
**Lines:** 70

```yaml
# Complete fields configuration
fields:
    id:
        label: ID
        disabled: true
    
    user:
        label: User
        type: relation
        nameFrom: full_name
        descriptionFrom: email
        span: left
        disabled: true
    
    action_type:
        label: Action Type
        type: text
        span: right
        disabled: true
    
    feedable_type:
        label: Related Model Type
        type: text
        span: left
        disabled: true
    
    feedable_id:
        label: Related Model ID
        type: number
        span: right
        disabled: true
    
    title:
        label: Title
        type: text
        span: full
        disabled: true
    
    body:
        label: Body
        type: textarea
        size: large
        span: full
        disabled: true
    
    additional_data:
        label: Additional Data
        type: codeeditor
        language: json
        span: full
        disabled: true
        size: large
    
    created_at:
        label: Created At
        type: datepicker
        mode: datetime
        span: left
        disabled: true
    
    updated_at:
        label: Updated At
        type: datepicker
        mode: datetime
        span: right
        disabled: true
```

**Key Features:**
- All fields disabled (read-only) by design
- User shown via relation with full_name
- Additional data displayed as JSON in code editor
- Proper field types (text, textarea, number, datepicker, codeeditor)
- Responsive layout with span directives

**Usage Scenario:**
Although feeds are not meant to be edited, this configuration can be used by other plugins that want to display feed details in a form context.

### columns.yaml

**File:** `/plugins/omsb/feeder/models/feed/columns.yaml`  
**Purpose:** Define list columns for displaying feed records  
**Lines:** 52

```yaml
# Complete columns configuration
columns:
    id:
        label: ID
        searchable: true
        sortable: true
        width: 80px
    
    user:
        label: User
        relation: user
        select: concat(first_name, ' ', last_name)
        searchable: true
        sortable: true
    
    action_type:
        label: Action
        searchable: true
        sortable: true
        width: 120px
    
    feedable_type:
        label: Model Type
        searchable: true
        sortable: true
    
    feedable_id:
        label: Model ID
        searchable: true
        sortable: true
        width: 100px
    
    title:
        label: Title
        searchable: true
        sortable: true
    
    description:
        label: Description
        searchable: false
        sortable: false
    
    created_at:
        label: Date
        type: datetime
        searchable: false
        sortable: true
        width: 180px
```

**Key Features:**
- Most columns searchable and sortable
- User column uses relation with SQL concat
- Description uses model accessor (not sortable)
- Fixed widths for compact columns
- Date column formatted as datetime

**Usage Scenario:**
Can be referenced by other plugins to create list views of feed data, or used in custom dashboard widgets.

## Backend Form Integration

### Option 1: Direct Partial Inclusion (Recommended)

Most flexible approach - include the feed sidebar directly in your view file.

**Example: Purchase Request Update View**

```php
<!-- update.php -->
<?php Block::put('breadcrumb') ?>
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= Backend::url('omsb/procurement/purchaserequests') ?>">
                Purchase Requests
            </a>
        </li>
        <li class="breadcrumb-item active"><?= e($this->pageTitle) ?></li>
    </ol>
<?php Block::endPut() ?>

<?php if (!$this->fatalError): ?>
    <?= Form::open(['class' => 'd-flex h-100']) ?>
        <div class="layout">
            <div class="layout-row">
                <!-- Main content area -->
                <div class="layout-cell flex-grow-1">
                    <?= $this->formRender() ?>
                </div>
                
                <!-- Feed sidebar -->
                <div class="layout-cell layout-sidebar" style="width: 350px;">
                    <?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
                        'feedableType' => get_class($formModel),
                        'feedableId' => $formModel->id,
                        'title' => 'Purchase Request Activity',
                        'limit' => 50,
                    ]) ?>
                </div>
            </div>
        </div>
        
        <!-- Form buttons -->
        <div class="form-buttons">
            <div data-control="loader-container">
                <?= $this->formRender(['section' => 'outside']) ?>
            </div>
        </div>
    <?= Form::close() ?>
<?php endif ?>
```

**Benefits:**
- Full control over layout and positioning
- Easy to customize parameters
- Can conditionally display sidebar
- Works in any view (update, preview, custom)

### Option 2: Form Config Secondary Tab

Less flexible but useful for tabbed interfaces.

**config_form.yaml:**

```yaml
secondaryTabs:
    stretch: true
    cssClass: secondary-tabs
    fields:
        # Other tabs...
        
        _feed_activity:
            type: section
            label: Activity
            tab: Activity
            context: [update, preview]
        
        _feed_sidebar_content:
            type: partial
            path: $/omsb/feeder/partials/_feed_sidebar.htm
            tab: Activity
            context: [update, preview]
```

**Note:** This approach has limitations:
- Cannot pass dynamic parameters (feedableType, feedableId)
- Requires custom partial that extracts these from form context
- Less commonly used

### Option 3: Conditional Display (Preview Only)

Show feed sidebar only in read-only preview mode.

```php
<!-- In update.php or preview.php -->
<div class="layout">
    <div class="layout-row">
        <div class="layout-cell flex-grow-1">
            <?= $this->formRender() ?>
        </div>
        
        <?php if ($this->formGetContext() === 'preview'): ?>
            <div class="layout-cell layout-sidebar" style="width: 350px;">
                <?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
                    'feedableType' => get_class($formModel),
                    'feedableId' => $formModel->id,
                ]) ?>
            </div>
        <?php endif ?>
    </div>
</div>
```

**Use Case:** When you want to show activity history only when viewing (not editing).

### Option 4: Multiple Sidebars

Display multiple feed sections with different configurations.

```php
<div class="layout-cell layout-sidebar" style="width: 350px;">
    <!-- Recent activity (last 10 items) -->
    <?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
        'feedableType' => get_class($formModel),
        'feedableId' => $formModel->id,
        'title' => 'Recent Activity',
        'limit' => 10,
    ]) ?>
    
    <hr>
    
    <!-- Approval history only -->
    <?php
    $approvalFeeds = Feed::where('feedable_type', get_class($formModel))
        ->where('feedable_id', $formModel->id)
        ->whereIn('action_type', ['approve', 'reject', 'review'])
        ->with('user')
        ->orderBy('created_at', 'desc')
        ->limit(20)
        ->get();
    ?>
    
    <h5>Approval History</h5>
    <?php foreach ($approvalFeeds as $feed): ?>
        <!-- Custom feed display for approvals -->
        <div class="approval-item">
            <strong><?= e($feed->user->full_name) ?></strong>
            <?= e($feed->action_type) ?> -
            <em><?= $feed->created_at->diffForHumans() ?></em>
        </div>
    <?php endforeach ?>
</div>
```

## Backend List Integration

### Displaying Feeds in List Controller

Create a dedicated controller to display feed lists (if needed).

**Controller:**

```php
<?php namespace Omsb\Feeder\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Omsb\Feeder\Models\Feed;

class Feeds extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['omsb.feeder.access_feeds'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Omsb.Feeder', 'feeder', 'feeds');
    }
}
```

**config_list.yaml:**

```yaml
# List configuration
title: Activity Feeds
list: $/omsb/feeder/models/feed/columns.yaml
modelClass: Omsb\Feeder\Models\Feed
recordUrl: omsb/feeder/feeds/preview/:id
recordsPerPage: 50
noRecordsMessage: backend::lang.list.no_records
showSetup: true
showCheckboxes: false
toolbar:
    search:
        prompt: backend::lang.list.search_prompt
    filters:
        - action_type
        - user
        - created_at
```

**Note:** While technically possible, this is **NOT recommended** as feeds are better viewed in context of their related models.

## Custom YAML for Feed Display

### Creating Custom Feed List Columns

You can create custom column configurations in your plugin to display feeds differently.

**Example: procurement/models/purchaserequest/feed_columns.yaml**

```yaml
columns:
    created_at:
        label: When
        type: datetime
        width: 180px
        sortable: true
    
    user:
        label: Who
        relation: user
        select: full_name
        sortable: true
    
    action_type:
        label: Action
        sortable: true
        width: 120px
    
    title:
        label: Details
        sortable: false
    
    additional_data:
        label: Metadata
        type: jsonviewer
        sortable: false
```

**Usage:**

```php
// In your controller
public function listOverrideColumnValue($record, $columnName, $definition = null)
{
    if ($columnName === 'additional_data') {
        return '<pre>' . json_encode($record->additional_data, JSON_PRETTY_PRINT) . '</pre>';
    }
}
```

### Custom Feed Form Fields

Create simplified form for feed display in modals/popups.

**Example: procurement/models/purchaserequest/feed_fields.yaml**

```yaml
fields:
    _info:
        type: partial
        path: feed_info_partial
        span: full
    
    user:
        label: User
        type: relation
        disabled: true
    
    action_type:
        label: Action
        type: text
        disabled: true
    
    title:
        label: Title
        type: text
        disabled: true
        span: full
    
    body:
        label: Details
        type: textarea
        disabled: true
        span: full
    
    created_at:
        label: Timestamp
        type: datepicker
        mode: datetime
        disabled: true
```

## Widget Integration

### Creating Custom Dashboard Widget

Display recent feeds in a dashboard widget.

**reportwidgets/RecentActivity.php:**

```php
<?php namespace Omsb\Feeder\ReportWidgets;

use Backend\Classes\ReportWidgetBase;
use Omsb\Feeder\Models\Feed;

class RecentActivity extends ReportWidgetBase
{
    public function render()
    {
        $this->vars['feeds'] = Feed::with('user')
            ->orderBy('created_at', 'desc')
            ->limit($this->property('limit', 10))
            ->get();
        
        return $this->makePartial('widget');
    }
    
    public function defineProperties()
    {
        return [
            'limit' => [
                'title' => 'Number of items',
                'default' => 10,
                'type' => 'string',
                'validationPattern' => '^[0-9]+$',
            ],
        ];
    }
}
```

**reportwidgets/recentactivity/partials/_widget.php:**

```php
<div class="report-widget">
    <h3>Recent Activity</h3>
    
    <div class="feed-list">
        <?php foreach ($feeds as $feed): ?>
            <div class="feed-item">
                <div class="feed-user">
                    <?= $feed->user ? e($feed->user->full_name) : 'System' ?>
                </div>
                <div class="feed-action">
                    <?= e($feed->action_type) ?>
                </div>
                <div class="feed-time">
                    <?= $feed->created_at->diffForHumans() ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</div>
```

**Plugin.php:**

```php
public function registerReportWidgets()
{
    return [
        'Omsb\Feeder\ReportWidgets\RecentActivity' => [
            'label' => 'Recent Activity',
            'context' => 'dashboard',
        ],
    ];
}
```

## Partial Parameters Reference

### _feed_sidebar.htm Parameters

**Required:**
- `feedableType` (string): Fully qualified class name
- `feedableId` (int): Model instance ID

**Optional:**
- `title` (string): Custom header title (default: "Activity Feed")
- `limit` (int): Maximum items to display (default: 50)

**Examples:**

```php
// Minimal usage
<?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
    'feedableType' => PurchaseRequest::class,
    'feedableId' => 190,
]) ?>

// Full customization
<?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
    'feedableType' => 'Omsb\Procurement\Models\PurchaseRequest',
    'feedableId' => $model->id,
    'title' => 'Document History',
    'limit' => 100,
]) ?>

// With variable
$feedConfig = [
    'feedableType' => get_class($formModel),
    'feedableId' => $formModel->id,
    'title' => 'PR Activity Log',
    'limit' => 75,
];
<?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', $feedConfig) ?>
```

## Styling and Customization

### Overriding Sidebar Styles

The sidebar includes embedded CSS, but you can override styles in your plugin's assets:

**assets/css/custom-feed.css:**

```css
/* Override sidebar container */
.feed-sidebar-container {
    border: 2px solid #3498db;
    border-radius: 8px;
}

/* Override avatar colors */
.avatar-circle.bg-primary {
    background-color: #2c3e50 !important;
}

/* Override badge colors */
.feed-metadata .badge-success {
    background-color: #27ae60 !important;
}

/* Adjust sidebar width */
.layout-cell.layout-sidebar {
    width: 400px !important;
}

/* Custom scrollbar */
.feed-sidebar-content::-webkit-scrollbar {
    width: 10px;
}

.feed-sidebar-content::-webkit-scrollbar-thumb {
    background: #3498db;
}
```

**In your view:**

```php
<?php Block::put('head') ?>
    <link href="<?= Url::asset('plugins/omsb/procurement/assets/css/custom-feed.css') ?>" rel="stylesheet">
<?php Block::endPut() ?>
```

### Creating Custom Partial

Create your own partial based on the original:

**plugins/omsb/procurement/partials/_custom_feed.htm:**

```php
<?php
use Omsb\Feeder\Models\Feed;

$feeds = Feed::getForDocument($feedableType, $feedableId, $limit ?? 50);
?>

<div class="custom-feed-container">
    <h4><?= e($title ?? 'Activity') ?></h4>
    
    <div class="feed-items">
        <?php foreach ($feeds as $feed): ?>
            <div class="feed-item">
                <strong><?= $feed->user->full_name ?? 'System' ?></strong>
                <span><?= $feed->action_type ?></span>
                <time><?= $feed->created_at->format('M d, Y H:i') ?></time>
                
                <?php if ($feed->title): ?>
                    <div><?= e($feed->title) ?></div>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
</div>
```

## Permission-Based Display

Show feed sidebar only to users with specific permissions:

```php
<?php
use BackendAuth;

$user = BackendAuth::getUser();
?>

<?php if ($user->hasAccess('omsb.feeder.access_feeds')): ?>
    <div class="layout-cell layout-sidebar" style="width: 350px;">
        <?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
            'feedableType' => get_class($formModel),
            'feedableId' => $formModel->id,
        ]) ?>
    </div>
<?php endif ?>
```

## Best Practices

### 1. Always Check for Existing Record

Only display feed sidebar for existing records (not during creation):

```php
<?php if ($formModel->exists): ?>
    <div class="layout-cell layout-sidebar">
        <?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
            'feedableType' => get_class($formModel),
            'feedableId' => $formModel->id,
        ]) ?>
    </div>
<?php endif ?>
```

### 2. Use Appropriate Limit

Set reasonable limits based on use case:

```php
// Preview mode - show more history
<?php if ($this->formGetContext() === 'preview'): ?>
    limit: 100
<?php else: ?>
    limit: 20  // Edit mode - show recent only
<?php endif ?>
```

### 3. Consistent Naming

Use consistent partial variable names across your plugin:

```php
// Good - consistent
'feedableType' => get_class($formModel)
'feedableId' => $formModel->id

// Bad - inconsistent
'modelType' => get_class($formModel)
'recordId' => $formModel->id
```

### 4. Error Handling

Handle cases where feeds might not exist:

```php
<?php
try {
    $feeds = Feed::getForDocument($feedableType, $feedableId);
} catch (Exception $e) {
    $feeds = collect(); // Empty collection
}
?>
```

## Conclusion

The Feeder plugin provides flexible backend integration through:
- YAML configurations for forms and lists
- Reusable sidebar partial with customizable parameters
- Multiple integration patterns (direct, config, conditional)
- Styling customization options
- Permission-based access control

**Recommended Approach:** Use direct partial inclusion in view files for maximum flexibility and control.

---

**Previous:** [← Models & Services](04_models_services.md) | **Next:** [Dev Notes →](06_dev_notes.md)
