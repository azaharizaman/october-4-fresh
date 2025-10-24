<?php if ($this->formGetModel()->id): ?>
    <?= $this->relationRender('sites') ?>
<?php else: ?>
    <div class="callout callout-info no-icon">
        <div class="header">
            <h3>Not Available</h3>
            <p>Please save this company first before managing sites.</p>
        </div>
    </div>
<?php endif ?>
