# Components Documentation - Feeder Plugin

## Overview

The Feeder plugin provides minimal backend components by design, focusing on a reusable sidebar partial for displaying activity feeds. This document covers all UI components, partials, helpers, and integration points.

## Backend Components

### 1. Feed Sidebar Partial

**File:** `/plugins/omsb/feeder/partials/_feed_sidebar.htm`  
**Lines:** 416  
**Purpose:** Reusable sidebar component for displaying activity feeds in backend forms

#### Features

✅ **Timeline View**
- Vertical timeline with connecting lines
- Chronological order (newest first)
- Visual flow of activities

✅ **User Avatars**
- Circular avatars with user initials
- Color-coded by user ID (6 colors)
- "SY" for system-generated actions

✅ **Action Types**
- Support for 15+ action types
- Color-coded badges
- Human-readable formatting

✅ **Status Transitions**
- Visual "from → to" representation
- Color-coded status badges
- Arrow indicator

✅ **Metadata Display**
- Amounts with currency
- Document numbers
- Custom additional data
- Timestamps in relative format

✅ **Responsive Design**
- Optimized for 300-400px sidebars
- Scrollable content (max 600px)
- Custom scrollbar styling
- Mobile-friendly typography

#### Usage

**Basic Integration:**
```php
<?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
    'feedableType' => get_class($formModel),
    'feedableId' => $formModel->id,
]) ?>
```

**With Custom Options:**
```php
<?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
    'feedableType' => 'Omsb\Procurement\Models\PurchaseRequest',
    'feedableId' => $formModel->id,
    'title' => 'Purchase Request Activity',
    'limit' => 100,
]) ?>
```

**In Layout with Sidebar:**
```php
<div class="layout">
    <div class="layout-row">
        <!-- Main content -->
        <div class="layout-cell flex-grow-1">
            <?= $this->formRender() ?>
        </div>
        
        <!-- Sidebar -->
        <div class="layout-cell layout-sidebar" style="width: 350px;">
            <?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
                'feedableType' => get_class($formModel),
                'feedableId' => $formModel->id,
            ]) ?>
        </div>
    </div>
</div>
```

#### Parameters

**Required:**
- `$feedableType` (string): Fully qualified class name of the model
- `$feedableId` (int): ID of the model instance

**Optional:**
- `$title` (string): Custom title for the feed section (default: "Activity Feed")
- `$limit` (int): Maximum number of feed items to display (default: 50)

#### Helper Functions

The partial includes 5 helper functions defined inline:

##### 1. getUserInitials($user)

**Purpose:** Extracts user initials from backend user model  
**Location:** Lines 41-51

```php
function getUserInitials($user) {
    if (!$user) {
        return 'SY'; // System
    }
    
    $firstInitial = $user->first_name ? mb_substr($user->first_name, 0, 1) : '';
    $lastInitial = $user->last_name ? mb_substr($user->last_name, 0, 1) : '';
    
    $initials = strtoupper($firstInitial . $lastInitial);
    return $initials !== '' ? $initials : 'U';
}
```

**Examples:**
- John Doe → "JD"
- Siti Nurbaya → "SN"
- System (null user) → "SY"
- Unknown (empty names) → "U"

##### 2. getAvatarColor($userId)

**Purpose:** Determines avatar background color based on user ID  
**Location:** Lines 56-71

```php
function getAvatarColor($userId) {
    if (!$userId) {
        return 'bg-secondary'; // System color
    }
    
    $colors = [
        'bg-primary',
        'bg-success',
        'bg-info',
        'bg-warning',
        'bg-danger',
        'bg-secondary',
    ];
    
    return $colors[$userId % count($colors)];
}
```

**Color Mapping:**
| User ID | Color Class | Hex Color | Usage |
|---------|-------------|-----------|-------|
| 1, 7, 13, ... | bg-primary | #3498db | Blue |
| 2, 8, 14, ... | bg-success | #2ecc71 | Green |
| 3, 9, 15, ... | bg-info | #1abc9c | Teal |
| 4, 10, 16, ... | bg-warning | #f39c12 | Orange |
| 5, 11, 17, ... | bg-danger | #e74c3c | Red |
| 6, 12, 18, ... | bg-secondary | #95a5a6 | Gray |
| null (System) | bg-secondary | #95a5a6 | Gray |

##### 3. formatTimestamp($timestamp)

**Purpose:** Converts timestamp to human-readable relative time  
**Location:** Lines 76-81

```php
function formatTimestamp($timestamp) {
    $carbon = Carbon::parse($timestamp);
    $diff = $carbon->diffForHumans();
    
    return $diff;
}
```

**Examples:**
- "1 minute ago"
- "5 hours ago"
- "2 days ago"
- "1 month ago"
- "3 years ago"

##### 4. getActionBadgeClass($actionType)

**Purpose:** Maps action type to Bootstrap badge class  
**Location:** Lines 86-107

```php
function getActionBadgeClass($actionType) {
    $actionType = strtolower($actionType);
    
    $badgeMap = [
        'create' => 'badge-primary',
        'update' => 'badge-info',
        'delete' => 'badge-danger',
        'approve' => 'badge-success',
        'reject' => 'badge-danger',
        'submit' => 'badge-info',
        'review' => 'badge-warning',
        'complete' => 'badge-success',
        'cancel' => 'badge-secondary',
        'comment' => 'badge-light',
        'verified' => 'badge-success',
        'verifying' => 'badge-info',
        'recommended' => 'badge-success',
        'approving' => 'badge-warning',
    ];
    
    return $badgeMap[$actionType] ?? 'badge-secondary';
}
```

**Badge Color Scheme:**
| Action Type | Badge Class | Color | Semantic Meaning |
|-------------|-------------|-------|------------------|
| create | badge-primary | Blue | New record |
| update | badge-info | Teal | Modification |
| delete | badge-danger | Red | Removal |
| approve, verified, complete, recommended | badge-success | Green | Positive action |
| reject | badge-danger | Red | Negative action |
| submit, verifying | badge-info | Teal | In progress |
| review, approving | badge-warning | Orange | Pending review |
| cancel | badge-secondary | Gray | Cancelled |
| comment | badge-light | Light gray | Commentary |
| *(unknown)* | badge-secondary | Gray | Default |

##### 5. formatActionType($actionType)

**Purpose:** Formats action type to human-readable text  
**Location:** Lines 112-114

```php
function formatActionType($actionType) {
    return ucfirst(str_replace('_', ' ', $actionType));
}
```

**Examples:**
- "create" → "Create"
- "update" → "Update"
- "status_change" → "Status change"
- "approve_request" → "Approve request"

#### HTML Structure

```html
<div class="feed-sidebar-container">
    <div class="feed-sidebar-header">
        <h4>Activity Feed</h4>
    </div>
    
    <div class="feed-sidebar-content">
        <div class="feed-timeline">
            <div class="feed-item">
                <div class="feed-avatar">
                    <div class="avatar-circle bg-primary">JD</div>
                </div>
                <div class="feed-content">
                    <div class="feed-header">
                        <span class="feed-user">John Doe</span>
                        <span class="feed-action">approved</span>
                    </div>
                    <div class="feed-title">Purchase Request Approved</div>
                    <div class="feed-body">Request meets all requirements</div>
                    <div class="feed-metadata">
                        <span class="badge badge-info">Submitted</span>
                        <i class="icon-arrow-right"></i>
                        <span class="badge badge-success">Approved</span>
                        <span class="feed-amount">Amount: RM 1,057.00</span>
                    </div>
                    <div class="feed-timestamp">
                        <small class="text-muted">1 month ago</small>
                    </div>
                </div>
            </div>
            <!-- More feed items... -->
        </div>
    </div>
</div>
```

#### CSS Classes

The partial includes embedded CSS (lines 204-416):

**Container Classes:**
- `.feed-sidebar-container`: Main wrapper
- `.feed-sidebar-header`: Header section with title
- `.feed-sidebar-content`: Scrollable content area

**Timeline Classes:**
- `.feed-timeline`: Timeline container
- `.feed-item`: Individual feed entry
- `.feed-item:not(:last-child)::before`: Timeline connector line

**Avatar Classes:**
- `.feed-avatar`: Avatar wrapper
- `.avatar-circle`: Circle with initials
- `.avatar-circle.bg-*`: Color variants (primary, success, info, warning, danger, secondary)

**Content Classes:**
- `.feed-content`: Content wrapper
- `.feed-header`: User name and action
- `.feed-user`: User name text
- `.feed-action`: Action type text
- `.feed-title`: Optional title
- `.feed-body`: Optional body text
- `.feed-metadata`: Metadata container
- `.feed-timestamp`: Relative time display

**Badge Classes:**
- `.badge`: Base badge style
- `.badge-primary`, `.badge-success`, `.badge-info`, etc.: Color variants
- `.feed-amount`: Amount display

**Scrollbar Styling:**
- `::-webkit-scrollbar`: Scrollbar width
- `::-webkit-scrollbar-track`: Scrollbar track background
- `::-webkit-scrollbar-thumb`: Scrollbar thumb styling

#### Visual Example

```
┌─────────────────────────────────────┐
│  Activity Feed                      │ ← Header
├─────────────────────────────────────┤
│                                     │
│  [JD] John Doe approved             │ ← Avatar + action
│       Purchase Request Approved     │ ← Title (optional)
│       Request meets requirements    │ ← Body (optional)
│       [Submitted] ──→ [Approved]   │ ← Status badges
│       Amount: RM 1,057.00          │ ← Metadata
│       1 month ago                   │ ← Timestamp
│       │                             │
│       │                             │ ← Timeline line
│  [SN] Siti Nurbaya updated         │
│       Updated vendor details        │
│       2 weeks ago                   │
│       │                             │
│       │                             │
│  [DM] Dayang Maznah created        │
│       1 month ago                   │
│                                     │
└─────────────────────────────────────┘
```

### 2. No Backend Controllers

**Design Decision:** The Feeder plugin intentionally does not provide backend controllers or navigation menu.

**Reasons:**
1. Feed records are system-generated and should not be manually created
2. Feeds are context-dependent and best viewed alongside their related models
3. Prevents accidental modification or deletion of audit trail
4. Reduces UI complexity

**Alternative Access:**
- View feeds via sidebar partial in related model's form
- Query feeds programmatically via Feed model
- Create custom dashboard widgets for overview

## Frontend Components

### No Frontend Components

The Feeder plugin does not provide any frontend components or CMS components.

**From Plugin.php:**
```php
public function registerComponents()
{
    return []; // No frontend components
}
```

**Reason:** Feeder is a backend-only plugin focused on admin/staff activity tracking. Frontend visitors do not need access to internal audit logs.

## Field and Column Configurations

### Fields Configuration (fields.yaml)

**File:** `/plugins/omsb/feeder/models/feed/fields.yaml`  
**Lines:** 70

This configuration is used when displaying feed records in forms (if needed):

```yaml
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

**Key Points:**
- All fields are `disabled: true` (read-only)
- User displayed via relation with full_name
- Additional data shown in JSON code editor
- Timestamps as datetime pickers

**Usage:** Other plugins can reference this configuration when displaying feeds in their interfaces.

### Columns Configuration (columns.yaml)

**File:** `/plugins/omsb/feeder/models/feed/columns.yaml`  
**Lines:** 52

This configuration is used when displaying feeds in lists:

```yaml
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

**Key Points:**
- Most columns are searchable and sortable
- User column uses relation with concatenated name
- Description uses the model's `getDescriptionAttribute()` accessor
- Width specified for compact columns (id, action_type, feedable_id, created_at)

**Usage:** Can be referenced by other plugins to display feed lists.

## Dashboard Widgets

### No Built-in Dashboard Widgets

The Feeder plugin does not currently provide dashboard widgets.

**Potential Widgets (Future Enhancement):**
1. **Recent Activity Widget**: Shows latest activities across all models
2. **User Activity Widget**: Shows activities by specific user
3. **Model Activity Widget**: Shows activities for specific model type
4. **Activity Chart Widget**: Visualizes activity trends over time

**Implementation Example:**
```php
// In Plugin.php
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

See [09_improvements.md](09_improvements.md) for detailed widget proposals.

## Verification Helper

### verify_sidebar.php

**File:** `/plugins/omsb/feeder/verify_sidebar.php`  
**Lines:** 223  
**Purpose:** Command-line verification script for testing feed sidebar installation

**Usage:**
```bash
php plugins/omsb/feeder/verify_sidebar.php
```

**Checks Performed:**
1. Feed model accessibility
2. Partial file existence and size
3. `getForDocument()` method presence
4. Database connectivity (optional)
5. Partial syntax validation
6. Helper function definitions
7. CSS inclusion

**Output:**
```
==========================================
Feed Sidebar Partial Verification
==========================================

1. Checking Feed model...
   ✓ Feed model found: Omsb\Feeder\Models\Feed

2. Checking partial file...
   ✓ Partial file exists at: /path/to/_feed_sidebar.htm
   ✓ File size: 17,024 bytes

3. Checking Feed::getForDocument() method...
   ✓ getForDocument() method exists
   ✓ Method parameters:
     - $feedableType (string) = required
     - $feedableId (int) = required
     - $limit (int) = 50

4. Sample feed data structure:
   Sample feeds structure:
   - ID: 1, Action: create, User: Siti Nurbaya
     Created: 1 month ago
     Metadata: {"document_number": "PR\\SRT\\2025\\00190", ...}
   ...

5. Checking database connection...
   ✓ Database connected
   ✓ Total feeds in database: 1,234
   ✓ Latest feed:
     - Action: approve
     - User: John Doe
     - Created: 5 minutes ago

6. Checking partial syntax...
   ✓ All required variables are referenced in partial
   ✓ All helper functions are defined
   ✓ CSS styles are included

==========================================
Integration Instructions:
==========================================
...
```

**Benefits:**
- Quick validation of installation
- Helpful for troubleshooting
- Provides integration examples
- Can be run during deployment

## No Navigation Menu

From `Plugin.php`:

```php
public function registerNavigation()
{
    return [];
}
```

**Reason:** Feeds are intended to be viewed in context of their related models, not as standalone records.

## Permissions

### Registered Permissions

From `Plugin.php`:

```php
public function registerPermissions()
{
    return [
        'omsb.feeder.access_feeds' => [
            'tab' => 'Feeder',
            'label' => 'Access Activity Feed'
        ],
    ];
}
```

**Permission:** `omsb.feeder.access_feeds`  
**Tab:** Feeder  
**Label:** Access Activity Feed

**Usage:**
- Controls programmatic access to feed data
- Not currently enforced in sidebar partial (shows all feeds)
- Can be used in custom controllers or widgets
- Recommended for API endpoints or export functionality

**Checking Permission:**
```php
use BackendAuth;

if (BackendAuth::getUser()->hasAccess('omsb.feeder.access_feeds')) {
    // User can access feeds
}
```

## Component Integration Examples

### Example 1: Purchase Request Controller

```php
// In PurchaseRequestController update.php
<?php Block::put('breadcrumb') ?>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= Backend::url('omsb/procurement/purchaserequests') ?>">Purchase Requests</a></li>
        <li class="breadcrumb-item active"><?= e($this->pageTitle) ?></li>
    </ol>
<?php Block::endPut() ?>

<?php if (!$this->fatalError): ?>
    <?= Form::open(['class' => 'd-flex h-100']) ?>
        <div class="layout">
            <div class="layout-row">
                <div class="layout-cell flex-grow-1">
                    <?= $this->formRender() ?>
                </div>
                
                <div class="layout-cell layout-sidebar" style="width: 350px;">
                    <?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
                        'feedableType' => get_class($formModel),
                        'feedableId' => $formModel->id,
                        'title' => 'PR Activity',
                    ]) ?>
                </div>
            </div>
        </div>
        
        <?= $this->formRender(['section' => 'outside']) ?>
    <?= Form::close() ?>
<?php endif ?>
```

### Example 2: Conditional Display (Preview Only)

```php
// Show feed only in preview/read-only mode
<?php if ($this->formGetContext() === 'preview'): ?>
    <div class="layout-cell layout-sidebar" style="width: 350px;">
        <?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
            'feedableType' => get_class($formModel),
            'feedableId' => $formModel->id,
        ]) ?>
    </div>
<?php endif ?>
```

### Example 3: Form Config Integration

```yaml
# config_form.yaml
secondaryTabs:
    stretch: true
    fields:
        # ... other tabs ...
        
        _feed_sidebar:
            type: partial
            path: $/omsb/feeder/partials/_feed_sidebar.htm
            context: [update, preview]
            tab: Activity
            cssClass: feed-sidebar-tab
```

**Note:** This approach is less flexible than direct partial inclusion in view files.

## Browser Compatibility

The feed sidebar partial works in:
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Internet Explorer 11+ (with limited CSS support)

**Dependencies:**
- No JavaScript required (pure PHP/HTML rendering)
- Standard CSS3 (flexbox, custom properties)
- OctoberCMS backend CSS framework

## Performance Considerations

### Sidebar Partial
- **Single Query**: Fetches all feeds in one query with eager loading
- **Limited Results**: Default limit of 50 prevents excessive data
- **No AJAX**: Static rendering, no polling or real-time updates
- **Lightweight CSS**: Embedded styles, no external stylesheets

### Optimization Recommendations
1. **Increase Limit Carefully**: Higher limits increase render time
2. **Use Pagination**: For large feed lists (future enhancement)
3. **Cache Results**: Consider caching feed queries for frequently viewed documents
4. **Indexes**: Database indexes ensure fast queries (already implemented)

## Accessibility

### Sidebar Partial
✅ **Semantic HTML**: Proper HTML5 structure  
✅ **Color Contrast**: Meets WCAG AA standards  
✅ **Screen Readers**: Text labels for all elements  
✅ **Keyboard Navigation**: Scrollable with keyboard  
⚠️ **ARIA Labels**: Not currently implemented (enhancement opportunity)

## Conclusion

The Feeder plugin provides a minimal, focused set of components centered around the reusable feed sidebar partial. This design keeps the plugin simple while providing maximum flexibility for integration.

**Key Components:**
1. Feed Sidebar Partial - Timeline view of activities
2. Field/Column Configs - Reusable form/list definitions
3. Verification Helper - Installation testing script

**No Components:**
- No backend controllers (by design)
- No frontend components (backend-only plugin)
- No dashboard widgets (future enhancement)
- No API endpoints (see 03_api_endpoints.md)

---

**Previous:** [← Integration Guide](01_integration.md) | **Next:** [API Endpoints →](03_api_endpoints.md)
