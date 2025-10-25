<?php if ($this->formGetModel()->id): ?>
    <?= $this->relationRender('warehouses') ?>
<?php else: ?>
    <div class="callout callout-info no-icon">
        <div class="header">
            <h3>Not Available</h3>
            <p>Please save this site first before managing warehouses.</p>
        </div>
    </div>
<?php endif ?>