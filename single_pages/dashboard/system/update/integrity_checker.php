<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>

<div class="ccm-dashboard-header-buttons">
    <?php if (!$errors->has()) { ?>
        <div class="btn-group">
            <button id="check_core_integrity" class="btn btn-primary">
                <?= t('Check core integrity'); ?>
            </button>
        </div>
    <?php } else { ?>
        <a href="<?= $c->getCollectionLink(); ?>" class="btn btn-primary">
            <?= t('Check again'); ?>
        </a>
    <?php } ?>
</div>

<div class="ccm-system-errors alert alert-danger" <?php if (!$errors->has()) { ?>style="display: none;"<?php } ?>>
    <?php
        if ($errors->has()) {
            foreach ($errors->getList() as $error) {
                echo '<div>' . $error->getMessage() . '</div>';
            }
        }
    ?>
</div>

<?php if (!$errors->has()) { ?>
    <h3 id="diff-title" style="display: none;"><?= t('Changed files'); ?></h3>
    <div class="panel-group" id="diff" role="tablist" aria-multiselectable="true"></div>

    <script type="text/template" data-template="diff-template">
        <div class="panel panel-default" data-path="<%= path %>" data-open="false">
            <div class="panel-heading" role="tab" id="heading-<%= index %>">
                <h4 class="panel-title">
                    <a role="button" data-toggle="collapse" data-parent="#diff" href="#collapse-<%= index %>" aria-expanded="false" aria-controls="collapse-<%= index %>"><%= filePath %></a>
                    <i class="fa fa-plus"></i>
                </h4>
            </div>
            <div id="collapse-<%= index %>" class="panel-collapse collapse" role="tabpanel" aria-labelledby="heading-<%= index %>">
                <div class="panel-body">
                    <i class="fa fa-spinner fa-spin"></i>
                </div>
            </div>
        </div>
    </script>

    <script>
        $(document).ready(function() {
            var _templateDiff = _.template($('[data-template="diff-template"]').html());

            $('#check_core_integrity').on('click', function() {
                $('#check_core_integrity').html('<?= t('Checking...'); ?> <i class="fa fa-spinner fa-spin"></i>').prop('disabled', 'disabled');
                $('.ccm-system-errors').fadeOut('fast', function() {
                    $('.ccm-system-errors').html('');
                });
                $('#diff-title').fadeOut();
                $('#diff').fadeOut(function() {
                    $('#diff').html('');
                });
                $.ajax({
                    url: '<?= $c->getCollectionLink(); ?>/check_core_integrity',
                }).done(function(response) {
                    $('#check_core_integrity').html(<?= json_encode(t('Check core integrity')); ?>).removeAttr('disabled');
                    if (response.errors && response.errors.errors.length > 0) {
                        response.errors.errors.forEach(function(error) {
                            $('.ccm-system-errors').append('<div>' + error + '</div>');
                        });
                        $('.ccm-system-errors').fadeIn();
                    }
                    if (response.modified_files && response.modified_files.length > 0) {
                        $('#diff-title').fadeIn();
                        $('#diff').fadeIn(function() {
                            response.modified_files.forEach((element, index) => {
                                $('#diff').append(_templateDiff({
                                    index: index,
                                    filePath: element.corePath,
                                    path: element.path,
                                }));
                            });
                        });
                    }
                });
            });

            $('#diff').on('click', '.panel-title', function(e) {
                var panel = $(this).parents('.panel');
                var panelBody = panel.find('.panel-body');
                if (panel.data('open')) {
                    panel.data('open', false);
                    panel.find('.panel-title .fa').removeClass('fa-minus');
                } else {
                    panel.data('open', true);
                    panel.find('.panel-title .fa').addClass('fa-minus');
                }
                if (panelBody.find('.diff-element').length <= 0) {
                    var path = panel.data('path');
                    if (path) {
                        $.ajax({
                            url: '<?= $c->getCollectionLink(); ?>/get_file_diff',
                            method: 'post',
                            data: {
                                file: path
                            },
                        }).done(function(response) {
                            if (response.diff) {
                                panelBody.html(response.diff);
                            }
                        });
                    }
                }
            });
        });
    </script>
<?php } ?>

<style type="text/css">

    .ccm-ui .panel-body {
        padding: 0;
        text-align: center;
    }

    .ccm-ui .panel-heading .panel-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .ccm-ui .panel-heading .panel-title i {
        font-size: 1rem;
    }

    .panel-body .diff-element {
        display: flex;
        align-items: center;
        min-height: 1.5rem;
        padding: 0 1rem;
        white-space: pre-wrap;
        line-height: 1.25rem;
        font-family: SFMono-Regular,Consolas,Liberation Mono,Menlo,Courier,monospace;
        font-size: .8125rem;
        color: #24292e;
        word-wrap: normal;
        text-decoration: none;
        text-align: left;
    }

    .panel-body .line-number {
        color: rgba(27, 31, 35, .3);
    }

    .panel-body ins.diff-element {
        background-color: #e6ffed;
    }

    .panel-body del.diff-element {
        background-color: #ffeef0;
    }

    .panel-body del.diff-element {
        background-color: #ffeef0;
    }

    .panel-body span.diff-element.no-changes {
        min-height: 2rem;
        background-color: #f1f8ff;
    }

    .panel-body span.diff-element.no-changes + span.diff-element.no-changes {
        display: none;
    }

</style>
