<?= Form::open(['class' => 'layout']) ?>

    <div class="layout-row">
        <?= $this->formRender() ?>
    </div>

    <div class="form-buttons">
        <div class="loading-indicator-container">
            <button
                type="button"
                data-request="onSave"
                data-request-data="close:true"
                data-hotkey="ctrl+enter, cmd+enter"
                data-load-indicator="<?= e(trans('backend::lang.form.saving')) ?>"
                class="btn btn-default">
                <?= e(trans('backend::lang.form.save_and_close')) ?>
            </button>
            <span class="btn-text">
                <?= e(trans('backend::lang.form.or')) ?> <a href="<?= Backend::url('omsb/feeder/feeds') ?>"><?= e(trans('backend::lang.form.cancel')) ?></a>
            </span>
        </div>
    </div>

<?= Form::close() ?>
