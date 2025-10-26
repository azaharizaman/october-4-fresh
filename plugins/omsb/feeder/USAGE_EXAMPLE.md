# Feed Sidebar Partial - Usage Example

This document provides a complete example of integrating the Feeder plugin's sidebar partial into a backend controller.

## Example: Purchase Request Controller

### Step 1: Controller View File (update.php)

```php
<?php Block::put('breadcrumb') ?>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= Backend::url('omsb/procurement/purchaserequests') ?>">Purchase Requests</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($this->pageTitle) ?></li>
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

                <!-- Sidebar with feed -->
                <div class="layout-cell layout-sidebar" style="width: 350px;">
                    <?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
                        'feedableType' => get_class($formModel),
                        'feedableId' => $formModel->id,
                        'title' => 'Purchase Request Activity',
                    ]) ?>
                </div>
            </div>
        </div>

        <div class="form-buttons">
            <div data-control="loader-container">
                <button
                    type="submit"
                    data-request="onSave"
                    data-request-data="{ redirect: 0 }"
                    data-hotkey="ctrl+s, cmd+s"
                    data-request-message="<?= __("Saving :name...", ['name' => $formRecordName]) ?>"
                    class="btn btn-primary">
                    <?= __("Save") ?>
                </button>
                <button
                    type="button"
                    data-request="onSave"
                    data-request-data="{ close: 1 }"
                    data-browser-redirect-back
                    data-hotkey="ctrl+enter, cmd+enter"
                    data-request-message="<?= __("Saving :name...", ['name' => $formRecordName]) ?>"
                    class="btn btn-default">
                    <?= __("Save & Close") ?>
                </button>
                <span class="btn-text">
                    <span class="button-separator"><?= __("or") ?></span>
                    <a
                        href="<?= Backend::url('omsb/procurement/purchaserequests') ?>"
                        class="btn btn-link p-0">
                        <?= __("Cancel") ?>
                    </a>
                </span>
            </div>
        </div>

    <?= Form::close() ?>

<?php else: ?>

    <p class="flash-message static error">
        <?= e($this->fatalError) ?>
    </p>
    <p>
        <a
            href="<?= Backend::url('omsb/procurement/purchaserequests') ?>"
            class="btn btn-default">
            <?= __("Return to List") ?>
        </a>
    </p>

<?php endif ?>
```

### Step 2: Creating Feed Entries in Service Layer

When performing operations on your model, create feed entries to track the activity:

```php
<?php namespace Omsb\Procurement\Services;

use Omsb\Procurement\Models\PurchaseRequest;
use Omsb\Feeder\Models\Feed;
use BackendAuth;
use Db;

class PurchaseRequestService
{
    /**
     * Create a new purchase request
     */
    public function createPurchaseRequest(array $data): PurchaseRequest
    {
        return Db::transaction(function () use ($data) {
            $pr = PurchaseRequest::create($data);
            
            // Record the creation activity
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
            
            return $pr;
        });
    }
    
    /**
     * Update a purchase request
     */
    public function updatePurchaseRequest(PurchaseRequest $pr, array $data): bool
    {
        return Db::transaction(function () use ($pr, $data) {
            $pr->update($data);
            
            // Record the update activity
            Feed::create([
                'user_id' => BackendAuth::getUser()->id,
                'action_type' => 'update',
                'feedable_type' => PurchaseRequest::class,
                'feedable_id' => $pr->id,
                'title' => 'Purchase Request Updated',
                'body' => 'Purchase request details were modified.',
                'additional_data' => [
                    'document_number' => $pr->document_number,
                ],
            ]);
            
            return true;
        });
    }
    
    /**
     * Approve a purchase request
     */
    public function approvePurchaseRequest(PurchaseRequest $pr, string $comments = null): bool
    {
        return Db::transaction(function () use ($pr, $comments) {
            $oldStatus = $pr->status;
            
            $pr->status = 'approved';
            $pr->approved_by = BackendAuth::getUser()->id;
            $pr->approved_at = now();
            $pr->approval_comments = $comments;
            $pr->save();
            
            // Record the approval activity
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
                    'currency' => 'MYR',
                ],
            ]);
            
            return true;
        });
    }
    
    /**
     * Reject a purchase request
     */
    public function rejectPurchaseRequest(PurchaseRequest $pr, string $reason): bool
    {
        return Db::transaction(function () use ($pr, $reason) {
            $oldStatus = $pr->status;
            
            $pr->status = 'rejected';
            $pr->rejected_by = BackendAuth::getUser()->id;
            $pr->rejected_at = now();
            $pr->rejection_reason = $reason;
            $pr->save();
            
            // Record the rejection activity
            Feed::create([
                'user_id' => BackendAuth::getUser()->id,
                'action_type' => 'reject',
                'feedable_type' => PurchaseRequest::class,
                'feedable_id' => $pr->id,
                'title' => 'Purchase Request Rejected',
                'body' => $reason,
                'additional_data' => [
                    'document_number' => $pr->document_number,
                    'status_from' => $oldStatus,
                    'status_to' => 'rejected',
                ],
            ]);
            
            return true;
        });
    }
    
    /**
     * Add a comment to a purchase request
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
            'additional_data' => [
                'document_number' => $pr->document_number,
            ],
        ]);
    }
}
```

### Step 3: Result

When you view the Purchase Request in the backend, the sidebar will display:

1. **Timeline of activities** with user avatars
2. **Status transitions** with colored badges (e.g., Draft → Submitted → Approved)
3. **Comments and notes** added by users
4. **Timestamps** in relative format (e.g., "1 month ago")
5. **Additional metadata** like amounts, document numbers, etc.

The feed will automatically update whenever new activities are recorded for the purchase request.

## Alternative: Using in Preview Mode Only

If you only want to show the feed in preview mode (read-only), you can add a condition:

```php
<!-- In your update.php or preview.php -->
<?php if ($this->formGetContext() === 'preview'): ?>
    <div class="layout-cell layout-sidebar" style="width: 350px;">
        <?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
            'feedableType' => get_class($formModel),
            'feedableId' => $formModel->id,
        ]) ?>
    </div>
<?php endif; ?>
```

## Customization

### Custom Title

```php
<?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
    'feedableType' => get_class($formModel),
    'feedableId' => $formModel->id,
    'title' => 'Document History',
]) ?>
```

### Limit Number of Items

```php
<?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
    'feedableType' => get_class($formModel),
    'feedableId' => $formModel->id,
    'limit' => 100,
]) ?>
```

### Multiple Sidebars

You can even have multiple feed sidebars for different purposes:

```php
<!-- Recent activity -->
<?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
    'feedableType' => get_class($formModel),
    'feedableId' => $formModel->id,
    'title' => 'Recent Activity',
    'limit' => 10,
]) ?>

<!-- Full history -->
<?= $this->makePartial('$/omsb/feeder/partials/_feed_sidebar.htm', [
    'feedableType' => get_class($formModel),
    'feedableId' => $formModel->id,
    'title' => 'Complete History',
    'limit' => 500,
]) ?>
```
