# Feed Sidebar Partial - Feature Demonstration

This document demonstrates the visual representation and features of the Feed Sidebar Partial.

## Visual Representation

The feed sidebar displays as a card-like component with the following structure:

```
┌─────────────────────────────────────┐
│  Activity Feed                      │ ← Header with title
├─────────────────────────────────────┤
│                                     │
│  [SN] Siti Nurbaya created          │ ← Avatar circle + action
│       1 month ago                   │ ← Relative timestamp
│       │                             │
│       │                             │ ← Timeline connector
│  [DN] Dayang Maznah updated this... │
│       Purchase Request Updated      │ ← Optional title
│       Updated vendor and pricing... │ ← Optional body
│       1 month ago                   │
│       │                             │
│       │                             │
│  [DN] Dayang Maznah request approval│
│       Submitted ──→ Approving      │ ← Status transition
│       1 month ago                   │
│       │                             │
│       │                             │
│  [DA] Dayang Maznah approved this...│
│       Approving ──→ Approved       │ ← Status badges
│       1 month ago                   │
│       │                             │
│       │                             │
│  [NM] Nurul Azmiza verified this... │
│       Verified                      │ ← Single status badge
│       1 month ago                   │
│                                     │
└─────────────────────────────────────┘
```

## Features

### 1. User Avatar Circles
- **Initials Display**: Shows first letter of first name + first letter of last name
- **Color Coding**: Different colors based on user ID (consistent per user)
- **System Actions**: Shows "SY" for system-generated actions
- **Colors Available**:
  - Blue (primary)
  - Green (success)
  - Teal (info)
  - Orange (warning)
  - Red (danger)
  - Gray (secondary)

### 2. Timeline View
- **Vertical Line**: Connects all feed items chronologically
- **Chronological Order**: Latest activities appear at the top
- **Visual Flow**: Easy to follow the sequence of events

### 3. Action Types
The partial supports various action types with appropriate formatting:
- `create` - Record creation
- `update` - Record modification
- `delete` - Record deletion
- `approve` - Approval actions
- `reject` - Rejection actions
- `submit` - Submission for approval
- `review` - Review actions
- `complete` - Completion actions
- `cancel` - Cancellation actions
- `comment` - Comments/notes
- `verified` - Verification status
- `verifying` - In verification
- `recommended` - Recommendation
- `approving` - In approval process

### 4. Status Transitions
When `additional_data` contains `status_from` and `status_to`:
```
[Badge: Draft] ──→ [Badge: Submitted]
```
- Color-coded badges for each status
- Arrow indicator showing direction of transition
- Automatically extracts from feed data

### 5. Metadata Display
The partial intelligently displays additional data:
- **Amount**: Shows currency and formatted amount
- **Document Numbers**: References to related documents
- **Status Changes**: Visual representation of transitions
- **Comments**: Approval/rejection reasons

### 6. Responsive Design
- **Fixed Width Sidebar**: Designed for 300-400px sidebars
- **Scrollable Content**: Handles long feed lists with custom scrollbar
- **Max Height**: 600px with overflow scroll
- **Mobile-Friendly**: Responsive typography and spacing

### 7. Empty State
When no feeds exist:
```
┌─────────────────────────────────────┐
│  Activity Feed                      │
├─────────────────────────────────────┤
│                                     │
│         No activity yet.            │
│                                     │
└─────────────────────────────────────┘
```

## Example Feed Display

Based on the screenshot provided, here's how a Purchase Request feed would look:

```
Activity Feed
─────────────────────────────────────

[SN] Siti Nurbaya Binti Tamin updated this Purchase Request
     1 month ago

[SN] Siti Nurbaya Binti Tamin approved this request by Dayang Maznah Mohd Sebli
     [Approving] ──→ [Approved]
     1 month ago

[DA] Dayang Maznah Mohd Sebli updated this Purchase Request
     1 month ago

[DA] Dayang Maznah Mohd Sebli request verification from Nurul Azmiza Binti Mahmud
     [Verifying] ──→ [Verified]
     1 month ago

[DA] Dayang Maznah Mohd Sebli updated this Purchase Request
     1 month ago

[NM] Nurul Azmiza Binti Mahmud updated this Purchase Request
     1 month ago

[NM] Nurul Azmiza Binti Mahmud recommended this request by Nurul Azmiza Binti Mahmud
     [Recommended] ──→ [Verifying]
     1 month ago
```

## Color Scheme

### Avatar Colors
- Primary (Blue): #3498db
- Success (Green): #2ecc71
- Info (Teal): #1abc9c
- Warning (Orange): #f39c12
- Danger (Red): #e74c3c
- Secondary (Gray): #95a5a6

### Status Badge Colors
- **Primary** (Draft, Create): Blue
- **Success** (Approved, Verified, Complete): Green
- **Info** (Submitted, Update, Approving): Teal
- **Warning** (Review, Verifying): Orange
- **Danger** (Rejected, Delete): Red
- **Secondary** (Cancelled): Gray
- **Light** (Comment): Light gray

## CSS Classes

The partial includes embedded CSS with the following structure:

```css
.feed-sidebar-container      /* Main container */
.feed-sidebar-header         /* Header section */
.feed-sidebar-content        /* Scrollable content area */
.feed-timeline               /* Timeline container */
.feed-item                   /* Individual feed entry */
.feed-avatar                 /* Avatar circle container */
.avatar-circle               /* Avatar circle with initials */
.feed-content                /* Feed content area */
.feed-header                 /* User name + action */
.feed-title                  /* Optional title */
.feed-body                   /* Optional body text */
.feed-metadata               /* Additional data badges */
.feed-timestamp              /* Relative time */
```

## Browser Compatibility

The partial uses standard HTML/CSS and should work in:
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Internet Explorer 11+ (with limited support)

## Performance Considerations

- **Lazy Loading**: Not implemented (loads all feeds up to limit)
- **Query Optimization**: Uses eager loading for user relationships
- **Memory Efficient**: Limits to 50 items by default
- **No JavaScript**: Pure PHP/HTML rendering (no AJAX)

## Accessibility

- Semantic HTML structure
- Color contrast meets WCAG AA standards
- Screen reader friendly with proper text labels
- Keyboard navigation support (scrollable with keyboard)

## Integration with OctoberCMS

The partial follows OctoberCMS conventions:
- Uses `$this->makePartial()` for inclusion
- Supports variable passing
- Compatible with FormController and other behaviors
- Can be used in any backend view (update, preview, custom)

## Customization Options

### 1. Custom Title
```php
'title' => 'Document History'
```

### 2. Limit Items
```php
'limit' => 100
```

### 3. Custom Styling
You can override styles by adding your own CSS after the partial:
```css
.feed-sidebar-container {
    background: #f8f9fa;
}
```

### 4. Custom Action Colors
Modify the `getActionBadgeClass()` function in the partial to add custom mappings.

## Future Enhancements

Potential improvements for future versions:
- [ ] AJAX-based infinite scroll
- [ ] Real-time updates with WebSockets
- [ ] Filter by action type
- [ ] Search within feeds
- [ ] Export to PDF/CSV
- [ ] Attachments display
- [ ] Grouped feeds by day/week
- [ ] User mention support (@username)
- [ ] Emoji support in comments
- [ ] Rich text formatting
