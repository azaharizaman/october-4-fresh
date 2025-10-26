<div class="control-scrollpad" data-control="scrollpad">
    <div class="scroll-wrapper">
        <?= $this->formRenderPreview() ?>
    </div>
</div>

<div class="form-buttons">
    <a href="<?= Backend::url('omsb/feeder/feeds') ?>" class="btn btn-default oc-icon-chevron-left">
        <?= e(trans('backend::lang.form.return_to_list')) ?>
    </a>
</div>
