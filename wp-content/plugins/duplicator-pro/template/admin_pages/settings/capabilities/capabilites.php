<?php

/**
 * @package Duplicator
 */

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Views\TplMng;
use Duplicator\Views\UI\UiDialog;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

?>
<form id="dup-capabilites-form" action="<?php echo esc_url(ControllersManager::getCurrentLink()); ?>" method="post" data-parsley-validate>
    <?php $tplData['actions'][SettingsPageController::ACTION_CAPABILITIES_SAVE]->getActionNonceFileds(); ?>
    <div class="dup-capabilities-selector-wrapper">
        <h3 class="title">
            <?php esc_html_e('Roles and Permissions', 'duplicator-pro') ?>
        </h3>
        <?php $tplMng->render('admin_pages/settings/capabilities/no_license_message'); ?>
        <p>
            <?php
            esc_html_e(
                'Select the user roles and/or users that are allowed to manage different aspects of Duplicator.',
                'duplicator-pro'
            ); ?><br>
            <?php if (!is_multisite()) {
                esc_html_e(
                    'By default, all permissions are provided only to administrator users.',
                    'duplicator-pro'
                ); ?> <br>
            <?php }
            esc_html_e(
                'Some capabilities depend on others so if you select for example storage capability automatically the Backup read and Backup edit capabilities are assigned.', // phpcs:ignore Generic.Files.LineLength
                'duplicator-pro'
            );
            ?><br>
            <b>
                <?php esc_html_e('It is not possible to self remove the manage settings capabilities.', 'duplicator-pro'); ?>
            </b>
        </p>
        <?php if (is_multisite()) { ?>
            <p>
                <i class="fa-solid fa-info-circle fa-lg dark-gray-color"></i>&nbsp;
                <b>
                    <?php esc_html_e(
                        'In multisite installations, only Super Admin users can be granted access of any type. By default, all 
                    Super Admin users are granted all permissions.',
                        'duplicator-pro'
                    ); ?>
                </b>
            </p>
        <?php } ?>
        <hr size="1" />
        <div class="dup-settings-wrapper margin-bottom-1">
            <?php
            $capList = CapMng::getCapsInfo();
            foreach ($capList as $cap => $capInfo) {
                if ($cap === CapMng::CAP_LICENSE && !CapMng::can(CapMng::CAP_LICENSE, false)) {
                    continue;
                }
                $inputName = TplMng::getInputName('cap', $cap) . '[]';
                $inputId   = TplMng::getInputId('cap', $cap);
                $tCont     = $tplMng->render(
                    'admin_pages/settings/capabilities/capabilites_info_tooltip',
                    [
                        'info'   => $capInfo,
                        'pLabel' => (strlen($capInfo['parent']) > 0 ? $capList[$capInfo['parent']]['label'] : ''),
                    ],
                    false
                );

                $nParents = 0;
                $pCeck    = $capInfo;
                while (strlen($pCeck['parent']) > 0) {
                    $nParents++;
                    $pCeck = $capList[$pCeck['parent']];
                }
                ?>
                <label class="lbl-larger" for="<?php echo esc_attr($inputId); ?>">
                    <?php echo esc_html(str_repeat('-&nbsp;&nbsp;', $nParents)); ?>
                    <?php echo esc_html($capInfo['label']); ?>&nbsp;
                    <i
                        class="fa-solid fa-question-circle fa-sm dark-gray-color"
                        data-tooltip-title="<?php echo esc_attr($capInfo['label']); ?>"
                        data-tooltip="<?php echo esc_attr($tCont); ?>"
                        data-tooltip-width="600">
                    </i>
                </label>
                <div class="margin-bottom-1">
                    <select
                        id="<?php echo esc_attr($inputId); ?>"
                        name="<?php echo esc_attr($inputName); ?>"
                        multiple
                        <?php disabled(License::can(License::CAPABILITY_CAPABILITIES_MNG), false); ?>>
                        <?php
                        foreach (CapMng::getSelectableRoles() as $role => $roleName) {
                            if (!in_array($role, CapMng::getInstance()->getCapRoles($cap))) {
                                continue;
                            }
                            ?>
                            <option
                                value="<?php echo esc_attr($role); ?>"
                                <?php selected(true); ?>>
                                <?php echo esc_html($roleName); ?>
                            </option>
                            <?php
                        }
                        foreach (CapMng::getInstance()->getCapUsers($cap) as $userId) {
                            if (($user = get_user_by('id', $userId)) == false) {
                                continue;
                            }
                            ?>
                            <option
                                value="<?php echo (int) $user->ID; ?>"
                                <?php selected(true); ?>>
                                <?php echo esc_html($user->user_email); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            <?php } ?>
        </div>
    </div>
    <hr>
    <p>
        <input
            type="submit" name="submit" id="submit"
            class="button primary small"
            value="<?php esc_attr_e('Update Capabilities', 'duplicator-pro') ?>"
            <?php disabled(License::can(License::CAPABILITY_CAPABILITIES_MNG), false); ?>>
        &nbsp;
        <button
            id="dup-capabilities-reset"
            class="button secondary hollow small">
            <?php esc_html_e('Reset to Default', 'duplicator-pro'); ?>
        </button>
    </p>
</form>

<?php
//Delete Dialog
$dlgDelete             = new UiDialog();
$dlgDelete->title      = __('Are you sure do you want to reset the capabilities to default?', 'duplicator-pro');
$dlgDelete->message    = '<p>' . __('This action will reassign all the capabilities of the Administrator role.', 'duplicator-pro') . '<p>';
$dlgDelete->jsCallback = 'DupliJs.Settings.CapabilitesReset()';
$dlgDelete->initConfirm();
?>

<script>
    jQuery(document).ready(function($) {
        DupliJs.Settings.CapabilitesReset = function() {
            window.location.href = <?php echo json_encode($tplData['actions'][SettingsPageController::ACTION_CAPABILITIES_RESET]->getUrl()); ?>;
        };

        $('.dup-capabilities-selector-wrapper select').select2({
            width: 'resolve',
            ajax: {
                type: "POST",
                url: ajaxurl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    let query = {
                        search: params.term,
                        page: params.page || 1,
                        action: 'duplicator_settings_cap_users_list',
                        nonce: '<?php echo esc_js(wp_create_nonce('duplicator_settings_cap_users_list')); ?>'
                    }
                    return query;
                },
                processResults: function(data) {
                    return data.data.funcData;
                },
                cache: true
            },
            placeholder: <?php echo json_encode(__('Search roles or users', 'duplicator-pro')); ?>,
            minimumInputLength: <?php echo (License::can(License::CAPABILITY_CAPABILITIES_MNG_PLUS) ? 2 : 0); ?>
        });

        $('#dup-capabilities-reset').on('click', function(e) {
            e.preventDefault();
            <?php $dlgDelete->showConfirm(); ?>
        });
    });
</script>