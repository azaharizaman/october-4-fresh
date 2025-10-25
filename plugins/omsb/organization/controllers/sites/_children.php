<?php if ($this->formGetModel()->id): ?>
    <?= $this->relationRender('children') ?>
<?php else: ?>
    <div class="callout callout-info no-icon">
        <div class="header">
            <h3>Not Available</h3>
            <p>Please save this site first before viewing child sites.</p>
        </div>
    </div>
<?php endif ?>