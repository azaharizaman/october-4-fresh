<?php if ($this->formGetModel()->id): ?>
    <?= $this->relationRender('addresses') ?>
<?php else: ?>
    <div class="callout callout-info no-icon">
        <div class="header">
            <h3>Not Available</h3>
            <p>Please save this company first before managing addresses.</p>
        </div>
    </div>
<?php endif ?>
