<?php

/**
 * Staging page main template
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Views\UI\UiDialog;

$stagingSites    = $tplData['stagingSites'] ?? [];
$blur            = $tplMng->getGlobalValue('blur');
$numStagings     = count($stagingSites);
$isMultisite     = $tplData['isMultisite'] ?? false;
$deleteAction    = $tplData['deleteAction'] ?? false;
$deleteStagingId = $tplData['deleteStagingId'] ?? 0;
$backupsByDate   = $tplData['backupsByDate'] ?? [];
$hasBackups      = !empty($backupsByDate);

// Show incompatibility message for multisite
if ($isMultisite) : ?>
    <div class="dup-settings-wrapper">
        <div class="notice notice-error inline dupli-staging-incompatible-notice">
            <h3>
                <i class="fas fa-exclamation-circle"></i>
                <?php esc_html_e('Staging Not Available', 'duplicator-pro'); ?>
            </h3>
            <p>
                <?php esc_html_e(
                    'The Staging feature is currently available for single-site WordPress installations only.
                    Multisite networks are not supported at this time.',
                    'duplicator-pro'
                ); ?>
            </p>
        </div>
    </div>
    <?php
    return;
endif;
?>

<?php if ($blur) : ?>
    <div class="dup-blur-message">
        <h2><?php esc_html_e('Staging Sites', 'duplicator-pro'); ?></h2>
        <p>
            <?php esc_html_e('Create staging copies of your site for testing changes safely.', 'duplicator-pro'); ?>
        </p>
        <p>
            <a href="<?php echo esc_url(License::getUpsellURL()); ?>" target="_blank" class="button button-primary">
                <?php esc_html_e('Upgrade to Pro', 'duplicator-pro'); ?>
            </a>
        </p>
    </div>
<?php endif; ?>

<!-- Toolbar -->
<div class="dup-toolbar <?php echo ($blur ? 'dup-mock-blur' : ''); ?>">
    <label for="dupli-staging-bulk-actions" class="screen-reader-text"><?php esc_html_e('Select bulk action', 'duplicator-pro'); ?></label>
    <select id="dupli-staging-bulk-actions" class="small">
        <option value="-1" selected="selected">
            <?php esc_html_e("Bulk Actions", 'duplicator-pro') ?>
        </option>
        <?php if (CapMng::can(CapMng::CAP_STAGING, false)) { ?>
            <option value="delete" title="<?php esc_attr_e('Delete selected staging site(s)', 'duplicator-pro'); ?>">
                <?php esc_html_e('Delete', 'duplicator-pro'); ?>
            </option>
        <?php } ?>
    </select>
    <input
        type="button"
        id="dupli-staging-bulk-apply"
        class="button hollow secondary small"
        value="<?php esc_attr_e("Apply", 'duplicator-pro') ?>"
        onclick="DupliJs.Staging.ConfirmDelete()">
</div>

<form
    id="dupli-staging-form"
    method="post"
    class="<?php echo ($blur ? 'dup-mock-blur' : ''); ?>">

    <!-- Staging Sites Table -->
    <table class="widefat dup-table-list dup-packtbl striped" aria-label="<?php esc_attr_e('Staging Sites List', 'duplicator-pro'); ?>">
        <thead>
            <tr>
                <th class="dup-check-column dupli-check-column-narrow">
                    <input
                        type="checkbox"
                        id="dup-chk-all"
                        title="<?php esc_attr_e("Select all staging sites", 'duplicator-pro') ?>"
                        onclick="DupliJs.Staging.SetDeleteAll()">
                </th>
                <th class="dup-name-column"><?php esc_html_e("Name", 'duplicator-pro') ?></th>
                <th><?php esc_html_e("Status", 'duplicator-pro') ?></th>
                <th><?php esc_html_e("Source Backup", 'duplicator-pro') ?></th>
                <th><?php esc_html_e("Details", 'duplicator-pro') ?></th>
                <th><?php esc_html_e("Created", 'duplicator-pro') ?></th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($numStagings === 0) : ?>
                <tr>
                    <td colspan="8" class="dupli-no-elements">
                        <?php if (!$hasBackups) : ?>
                            <p>
                                <i class="fas fa-info-circle"></i>
                                <?php esc_html_e('No complete backups available. Create a backup first to use staging.', 'duplicator-pro'); ?>
                            </p>
                            <p>
                                <a
                                    href="<?php echo esc_url(SnapWP::adminUrl('admin.php', ['page' => ControllersManager::MAIN_MENU_SLUG])); ?>"
                                    class="button secondary small"
                                >
                                    <?php esc_html_e('Go to Backups', 'duplicator-pro'); ?>
                                </a>
                            </p>
                        <?php else : ?>
                            <p>
                                <?php esc_html_e('No staging sites created yet.', 'duplicator-pro'); ?>
                            </p>
                            <p>
                                <button type="button" class="button primary small dupli-staging-open-modal-btn">
                                    <i class="fas fa-plus-circle fa-sm"></i> <?php esc_html_e('Create Your First Staging Site', 'duplicator-pro'); ?>
                                </button>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($stagingSites as $index => $staging) :
                    $tplMng->render('stagingaddon/staging_page_row', [
                        'staging' => $staging,
                        'index'   => $index,
                    ]);
                endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="8" class="dupli-staging-table-footer">
                    <?php echo esc_html__('Total', 'duplicator-pro') . ': ' . (int) $numStagings; ?>
                </th>
            </tr>
        </tfoot>
    </table>
</form>

<?php
// Render create modal
if ($hasBackups) {
    $tplMng->render('stagingaddon/staging_page_modal', ['backupsByDate' => $backupsByDate]);
}

// Bulk action alert dialog
$alertBulk          = new UiDialog();
$alertBulk->title   = __('Bulk Action Required', 'duplicator-pro');
$alertBulk->message = __('Please select an action from the "Bulk Actions" drop down menu!', 'duplicator-pro');
$alertBulk->initAlert();

// Selection required alert dialog
$alertSelect          = new UiDialog();
$alertSelect->title   = __('Selection Required', 'duplicator-pro');
$alertSelect->message = __('Please select at least one staging site to delete!', 'duplicator-pro');
$alertSelect->initAlert();

// Delete confirmation dialog
$dlgDelete               = new UiDialog();
$dlgDelete->title        = __('Delete Staging Site(s)?', 'duplicator-pro');
$dlgDelete->height       = 350;
$dlgDelete->progressText = __('Removing Staging Sites, Please Wait...', 'duplicator-pro');
$dlgDelete->jsCallback   = 'DupliJs.Staging.Delete()';
$dlgDelete->initConfirm();
?>
<script type="text/javascript">
jQuery(document).ready(function($) {
    DupliJs.Staging.init({
        nonce: <?php echo wp_json_encode(wp_create_nonce('duplicator_staging_create')); ?>,
        deleteNonce: <?php echo wp_json_encode(wp_create_nonce('duplicator_staging_delete')); ?>,
        validateNonce: <?php echo wp_json_encode(wp_create_nonce('duplicator_staging_validate')); ?>,
        dlgDeleteMessageId: <?php echo wp_json_encode($dlgDelete->getMessageID()); ?>,
        i18n: {
            confirmDeleteSingle: <?php echo wp_json_encode(__('Are you sure you want to delete this staging site?', 'duplicator-pro')); ?>,
            confirmDeleteMultiple: <?php echo wp_json_encode(__('Are you sure you want to delete these staging sites?', 'duplicator-pro')); ?>,
            warning: <?php echo wp_json_encode(__('Warning:', 'duplicator-pro')); ?>,
            deleteWarningMessage: <?php echo wp_json_encode(
                __(
                    'This will permanently delete all files and database tables for the selected staging site(s).',
                    'duplicator-pro'
                )
            ); ?>,
            errorDeleting: <?php echo wp_json_encode(__('Error deleting staging site(s)', 'duplicator-pro')); ?>,
            modalNotFound: <?php echo wp_json_encode(__('Modal not found. Please try again.', 'duplicator-pro')); ?>,
            selectBackupFirst: <?php echo wp_json_encode(__('Please select a backup first.', 'duplicator-pro')); ?>,
            unknownValidationError: <?php echo wp_json_encode(__('Unknown validation error', 'duplicator-pro')); ?>,
            cannotCreateStaging: <?php echo wp_json_encode(__('Cannot create staging site:', 'duplicator-pro')); ?>,
            validationRequestFailed: <?php echo wp_json_encode(__('Validation request failed. Please try again.', 'duplicator-pro')); ?>,
            failedToCreate: <?php echo wp_json_encode(__('Failed to create staging:', 'duplicator-pro')); ?>,
            unknownError: <?php echo wp_json_encode(__('Unknown error', 'duplicator-pro')); ?>,
            stagingRequestFailed: <?php echo wp_json_encode(__('Staging creation request failed. Please try again.', 'duplicator-pro')); ?>
        },
        autoDeleteStagingId: <?php echo ($deleteAction && $deleteStagingId > 0) ? (int) $deleteStagingId : 'null'; ?>,
        checkNonce: <?php echo wp_json_encode(wp_create_nonce('duplicator_staging_check_complete')); ?>,
        pendingStagingIds: <?php echo wp_json_encode($tplData['pendingStagingIds'] ?? []); ?>
    });

    DupliJs.Staging.ConfirmDelete = function() {
        if ($("#dupli-staging-bulk-actions").val() != "delete") {
            <?php $alertBulk->showAlert(); ?>
            return;
        }

        var list = DupliJs.Staging.GetDeleteList();
        if (list.length == 0) {
            <?php $alertSelect->showAlert(); ?>
            return;
        }

        var $content = $('#<?php echo esc_js($dlgDelete->getMessageID()); ?>');
        var count = list.length;
        var isSingle = (count === 1);

        var html = isSingle
            ? '<i>' + DupliJs.Staging.i18n.confirmDeleteSingle + '</i>'
            : '<i>' + DupliJs.Staging.i18n.confirmDeleteMultiple + '</i>';

        html += '<div class="dupli-staging-delete-items">';
        list.forEach(function(id) {
            var $checkbox = $('#' + id);
            var name = $checkbox.data('staging-title');
            var escapedName = $('<div/>').text(name).html();
            html += '<div class="dupli-staging-delete-item"><i class="fas fa-clone"></i> <strong>' + escapedName + '</strong></div>';
        });
        html += '</div>';

        html += '<p class="dupli-staging-delete-warning"><strong>' + DupliJs.Staging.i18n.warning + '</strong> ';
        html += DupliJs.Staging.i18n.deleteWarningMessage + '</p>';

        $content.html(html);
        <?php $dlgDelete->showConfirm(); ?>
    };
});
</script>
