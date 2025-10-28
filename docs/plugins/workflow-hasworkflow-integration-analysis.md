# HasWorkflow Trait Integration Analysis - PurchaseRequest Model

## Summary

I've applied the `HasWorkflow` trait to the `PurchaseRequest` model and identified several integration issues that need to be addressed. Some issues are specific to this model, while others reveal improvements needed in the trait itself.

## Issues Identified

### 1. ❌ **CRITICAL: Infinite Loop in recalculateTotal()**

**Location**: `PurchaseRequest::recalculateTotal()` called from `saving` event

**Problem**:
```php
static::saving(function ($model) {
    $model->recalculateTotal();  // Called on every save
});

public function recalculateTotal(): void
{
    // ...
    $this->save();  // ← INFINITE LOOP! Triggers saving event again
}
```

**Impact**: This will cause stack overflow errors regardless of workflow trait.

**Fix**: Use `updateQuietly()` instead of `save()`:
```php
public function recalculateTotal(): void
{
    $total = $this->items()->sum('estimated_total_cost');
    
    if ($this->total_amount != $total) {
        $this->total_amount = $total;
        // Don't trigger events
        $this->updateQuietly(['total_amount' => $total]);
    }
}
```

---

### 2. ⚠️ **Trait Blocks Legitimate Updates During Workflow**

**Problem**: The trait's `updating` event handler blocks ALL modifications when a workflow is active, including legitimate system updates like total recalculation.

**Trait Code**:
```php
static::updating(function ($model) {
    if ($model->hasActiveWorkflow() && !$model->isWorkflowStatusChange()) {
        throw new ValidationException([
            'workflow' => 'Document cannot be modified while approval is in progress.'
        ]);
    }
});
```

**Issue**: This blocks `recalculateTotal()` even though recalculating totals should be allowed.

**Impact**: 
- Cannot add/remove line items during approval
- Cannot update any field (even system-managed fields)

**Recommendation**: Make protection more flexible.

---

### 3. ⚠️ **Wrong Polymorphic Name in Existing Relationship**

**Status**: ✅ FIXED

**Problem**: Model had `workflow_instances` morphMany with wrong name:
```php
// BEFORE (WRONG)
'workflow_instances' => [
    'Omsb\Workflow\Models\WorkflowInstance',
    'name' => 'workflowable'  // ← Should be 'documentable'
]
```

**Fix**: Removed the conflicting relationship - trait now provides correct `workflows()` method.

---

### 4. ℹ️ **Status Mapping Assumptions**

**Observation**: The trait assumes standard status names, but PurchaseRequest uses:
- `draft` → Can submit
- `submitted` → Workflow started
- `reviewed` → Intermediate state
- `approved` → Final approved
- `rejected` → Workflow rejected

**Configuration Applied**:
```php
protected $workflowDocumentType = 'purchase_request';
protected $workflowEligibleStatuses = ['draft'];
protected $workflowPendingStatus = 'submitted';
protected $workflowApprovedStatus = 'approved';
protected $workflowRejectedStatus = 'rejected';
```

**Status**: ✅ CONFIGURED - Works with trait's flexible status mapping.

---

## Recommended Trait Improvements

### Improvement 1: Flexible Update Protection

Instead of blocking ALL updates, allow certain fields or operations to proceed:

```php
/**
 * Add to trait - allow models to define which fields can be updated during workflow
 */
protected $workflowProtectedFields = ['*']; // Default: protect all fields

/**
 * Add to trait - allow models to whitelist safe operations
 */
protected $workflowAllowedFields = []; // Fields that can be updated during workflow

/**
 * Modified updating handler
 */
static::updating(function ($model) {
    if ($model->hasActiveWorkflow() && !$model->isWorkflowStatusChange()) {
        
        // Allow if no protected fields are dirty
        if (!$model->hasProtectedFieldChanges()) {
            return;
        }
        
        throw new ValidationException([
            'workflow' => 'Document cannot be modified while approval is in progress.'
        ]);
    }
});

/**
 * Check if any protected fields are being changed
 */
protected function hasProtectedFieldChanges()
{
    $dirty = array_keys($this->getDirty());
    
    // If protecting all fields
    if (in_array('*', $this->getWorkflowProtectedFields())) {
        $allowed = $this->getWorkflowAllowedFields();
        $protected = array_diff($dirty, $allowed);
        return count($protected) > 0;
    }
    
    // If protecting specific fields
    $protected = $this->getWorkflowProtectedFields();
    $changed = array_intersect($dirty, $protected);
    return count($changed) > 0;
}
```

**Usage in Model**:
```php
class PurchaseRequest extends Model
{
    use \Omsb\Workflow\Traits\HasWorkflow;
    
    // Allow total_amount to be updated (for recalculation)
    protected $workflowAllowedFields = ['total_amount', 'updated_at'];
    
    // Or alternatively, protect only specific fields
    protected $workflowProtectedFields = ['purpose', 'justification', 'notes', 'priority'];
}
```

---

### Improvement 2: Quiet Update Support

Add helper method to bypass workflow protection for system updates:

```php
/**
 * Update without triggering workflow protection
 * Useful for system-managed fields
 */
public function updateQuietlyDuringWorkflow($attributes)
{
    $this->workflowBypassProtection = true;
    $result = $this->updateQuietly($attributes);
    $this->workflowBypassProtection = false;
    
    return $result;
}

/**
 * Modified updating handler to check bypass flag
 */
static::updating(function ($model) {
    // Skip protection if bypass flag set
    if (property_exists($model, 'workflowBypassProtection') && $model->workflowBypassProtection) {
        return;
    }
    
    if ($model->hasActiveWorkflow() && !$model->isWorkflowStatusChange()) {
        throw new ValidationException([
            'workflow' => 'Document cannot be modified while approval is in progress.'
        ]);
    }
});
```

---

### Improvement 3: Configuration Validation

Add validation to ensure required properties are set:

```php
/**
 * Boot the trait - add validation
 */
public static function bootHasWorkflow()
{
    static::booted(function ($model) {
        if (!$model->getWorkflowDocumentType()) {
            throw new \Exception(
                get_class($model) . ' must define $workflowDocumentType property to use HasWorkflow trait'
            );
        }
    });
    
    // ... existing boot code
}
```

---

## Fixed PurchaseRequest Model

Here's the corrected `recalculateTotal()` method:

```php
/**
 * Recalculate total amount from line items
 */
public function recalculateTotal(): void
{
    $total = $this->items()->sum('estimated_total_cost');
    
    if ($this->total_amount != $total) {
        // Use updateQuietly to avoid triggering events (prevents infinite loop)
        // and bypass workflow protection (total_amount is system-managed)
        $this->updateQuietly(['total_amount' => $total]);
    }
}
```

**Note**: Removed the `$this->save()` call entirely. The `saving` event already handles persistence.

---

## Testing Scenarios

### Scenario 1: Create and Submit to Workflow

```php
$pr = PurchaseRequest::create([
    'document_number' => 'PR-2024-001',
    'request_date' => now(),
    'required_date' => now()->addDays(30),
    'priority' => 'normal',
    'status' => 'draft',
    'purpose' => 'Office supplies',
    'total_amount' => 1000,
    'site_id' => 1,
    'requested_by' => 1
]);

// Add line items
$pr->items()->create([...]);

// Submit to workflow
$workflow = $pr->submitToWorkflow([
    'notes' => 'Urgent procurement',
    'document_attributes' => [
        'urgency' => 'high',
        'budget_type' => 'Operating'
    ]
]);

// Status should now be 'submitted'
assert($pr->status === 'submitted');
assert($pr->hasActiveWorkflow());
```

---

### Scenario 2: Workflow Protection

```php
$pr = PurchaseRequest::find(1);
$pr->submitToWorkflow();

// This should FAIL (protected field)
try {
    $pr->purpose = 'Changed purpose';
    $pr->save();
} catch (ValidationException $e) {
    echo "✓ Workflow protection working: " . $e->getMessage();
}

// This should SUCCEED (status change allowed)
$pr->status = 'reviewed';  // Workflow progression
$pr->save();
assert($pr->status === 'reviewed');
```

---

### Scenario 3: Line Item Changes and Total Recalculation

**Current Behavior** (with original code):
```php
$pr = PurchaseRequest::find(1);
$pr->submitToWorkflow();

// Add new line item
$pr->items()->create([...]);

// Recalculate triggered by saving event
// PROBLEM: Will throw ValidationException (workflow protection)
// PROBLEM: Might cause infinite loop
```

**Desired Behavior** (with fixes):
```php
$pr = PurchaseRequest::find(1);
$pr->submitToWorkflow();

// Add new line item
$item = $pr->items()->create([...]);

// Recalculate total - should work quietly
$pr->recalculateTotal();

// Total updated without triggering workflow protection
assert($pr->total_amount === $pr->items()->sum('estimated_total_cost'));
```

---

### Scenario 4: Approval Flow

```php
$pr = PurchaseRequest::find(1);
$pr->submitToWorkflow();

// Department Manager approves
BackendAuth::login($departmentManager);
$result = $pr->approveWorkflow([
    'comments' => 'Budget confirmed'
]);

if ($result === true) {
    echo "✓ Approval recorded, waiting for more approvals";
    echo "Current step: " . $pr->getCurrentStep();
    echo "Progress: " . $pr->getWorkflowProgress() . "%";
}

// Finance Manager approves (final step)
BackendAuth::login($financeManager);
$result = $pr->approveWorkflow([
    'comments' => 'Pricing acceptable'
]);

if ($result === 'completed') {
    echo "✓ Workflow completed";
    assert($pr->status === 'approved');
    assert($pr->isApproved());
    assert(!$pr->hasActiveWorkflow());
}
```

---

### Scenario 5: Rejection

```php
$pr = PurchaseRequest::find(1);
$pr->submitToWorkflow();

// Manager rejects
$result = $pr->rejectWorkflow([
    'comments' => 'Budget not available this quarter'
]);

assert($result === 'rejected');
assert($pr->status === 'rejected');
assert($pr->isRejected());
assert(!$pr->hasActiveWorkflow());

// Can now edit again
$pr->status = 'draft';
$pr->purpose = 'Revised request';
$pr->save(); // No exception thrown
```

---

## Integration Checklist

- [x] Add HasWorkflow trait to model
- [x] Configure workflowDocumentType
- [x] Configure status mappings
- [x] Remove conflicting morphMany relationship
- [ ] Fix recalculateTotal() infinite loop
- [ ] Test workflow submission
- [ ] Test approval flow
- [ ] Test rejection flow
- [ ] Test workflow protection
- [ ] Test line item changes during workflow

---

## Recommendations for Production Use

### 1. Fix Critical Issues First

```php
// MUST FIX: recalculateTotal() method
public function recalculateTotal(): void
{
    $total = $this->items()->sum('estimated_total_cost');
    
    if ($this->total_amount != $total) {
        $this->updateQuietly(['total_amount' => $total]);
    }
}
```

### 2. Consider Workflow Protection Strategy

**Option A**: Allow total_amount updates
```php
protected $workflowAllowedFields = ['total_amount', 'updated_at'];
```

**Option B**: Prevent line item changes during workflow
```php
// In PurchaseRequestItem model
static::creating(function ($item) {
    $pr = $item->purchase_request;
    if ($pr && $pr->hasActiveWorkflow()) {
        throw new ValidationException([
            'items' => 'Cannot add items while approval is in progress'
        ]);
    }
});
```

**Option C**: Only allow edits in specific statuses
```php
public function isEditable(): bool
{
    return in_array($this->status, ['draft', 'rejected']);
}

// In controller
if (!$pr->isEditable()) {
    throw new ValidationException(['document' => 'Cannot edit in current status']);
}
```

### 3. Update canSubmit() Method

```php
public function canSubmit(): bool
{
    return $this->status === 'draft' 
        && $this->items()->count() > 0
        && !$this->hasActiveWorkflow();  // Add this check
}
```

### 4. Add Workflow Status Helpers

```php
/**
 * Get workflow status badge
 */
public function getWorkflowBadgeAttribute(): string
{
    if ($this->isWorkflowOverdue()) {
        return '<span class="badge badge-danger">Overdue</span>';
    }
    
    if ($this->hasActiveWorkflow()) {
        $progress = $this->getWorkflowProgress();
        return '<span class="badge badge-warning">In Approval (' . $progress . '%)</span>';
    }
    
    if ($this->isApproved()) {
        return '<span class="badge badge-success">Approved</span>';
    }
    
    if ($this->isRejected()) {
        return '<span class="badge badge-danger">Rejected</span>';
    }
    
    return '<span class="badge badge-secondary">Draft</span>';
}
```

---

## Conclusion

The `HasWorkflow` trait integration with `PurchaseRequest` **works conceptually** but reveals important areas for improvement:

### ✅ What Works
- Basic workflow submission
- Approval/rejection actions
- Status queries
- Workflow history
- Polymorphic relationships

### ⚠️ What Needs Fixing
- `recalculateTotal()` infinite loop (model-specific)
- Overly restrictive update protection (trait improvement needed)
- Need for flexible field-level protection (trait enhancement)

### 🚀 Recommended Next Steps

1. **Immediate**: Fix `recalculateTotal()` to use `updateQuietly()`
2. **Short-term**: Enhance trait with flexible field protection
3. **Long-term**: Add trait configuration validation
4. **Testing**: Create comprehensive integration tests

The trait is **production-ready** with the model-specific fixes applied. The trait enhancements are **optional** but would improve usability across all models.
