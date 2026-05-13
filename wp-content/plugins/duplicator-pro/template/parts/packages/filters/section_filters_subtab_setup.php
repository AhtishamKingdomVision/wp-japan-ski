<?php

/**
 * @package Duplicator
 */

use Duplicator\Installer\Package\ArchiveDescriptor;
use Duplicator\Package\SettingsUtils;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

$secureOn   = ($tplData['secureOn'] ?? ArchiveDescriptor::SECURE_MODE_NONE);
$securePass = ($tplData['securePass'] ?? '');

$unavaliableMessage = '';
$encryptAvaliable   = SettingsUtils::isArchiveEncryptionAvailable($unavaliableMessage);

?>
<div class="archive-setup-tab" >
    <div class="dup-package-hdr-1">
        <?php esc_html_e('Security', 'duplicator-pro'); ?>
    </div>

    <div class="dup-form-item margin-bottom-1">
        <label class="lbl-larger" >
            <?php esc_html_e('Mode', 'duplicator-pro') ?>:&nbsp;
            <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
                data-tooltip-title="<?php esc_attr_e('Security', 'duplicator-pro'); ?>"
                data-tooltip="<?php $tplMng->renderEscAttr('admin_pages/packages/setup/security-tooltip-content'); ?>">
            </i>
        </label>
        <div class="input">
            <span class="secure-on-input-wrapper">
                <label class="inline-display margin-right-1" >
                    <input 
                        type="radio" 
                        name="secure-on" 
                        class="margin-0"
                        id="secure-on-none" 
                        onclick="DupliJs.EnableInstallerPassword()" 
                        required
                        value="<?php echo (int) ArchiveDescriptor::SECURE_MODE_NONE; ?>"
                        <?php checked($secureOn, ArchiveDescriptor::SECURE_MODE_NONE); ?>
                        data-parsley-multiple="secure-on-mltiple-error"
                        data-parsley-errors-container="#secure-on-parsely-error"
                    >
                    <?php esc_html_e('None', 'duplicator-pro') ?>
                </label>
                <label class="inline-display margin-right-1" >
                    <input 
                        type="radio" 
                        name="secure-on" 
                        class="margin-0"
                        id="secure-on-inst-pwd" 
                        value="<?php echo (int) ArchiveDescriptor::SECURE_MODE_INST_PWD; ?>"
                        <?php checked($secureOn, ArchiveDescriptor::SECURE_MODE_INST_PWD); ?>
                        onclick="DupliJs.EnableInstallerPassword()" 
                        data-parsley-multiple="secure-on-mltiple-error"
                    >
                    <?php esc_html_e('Installer password', 'duplicator-pro') ?>
                </label>
                <label class="inline-display" >
                    <input 
                        type="radio" 
                        name="secure-on" 
                        class="margin-0"
                        id="secure-on-arc-encrypt" 
                        value="<?php echo (int) ArchiveDescriptor::SECURE_MODE_ARC_ENCRYPT; ?>"
                        <?php echo ($encryptAvaliable ? checked($secureOn, ArchiveDescriptor::SECURE_MODE_ARC_ENCRYPT, false) : ''); ?>
                        onclick="DupliJs.EnableInstallerPassword()" 
                        <?php disabled(!$encryptAvaliable); ?>
                        data-parsley-multiple="secure-on-mltiple-error"
                    >
                    <?php esc_html_e('Archive encryption', 'duplicator-pro') ?>
                </label>
            </span>
            <div id="secure-on-parsely-error"></div>
        </div>
    </div>
    <div class="dup-form-item">
        <label class="lbl-larger" >
            <?php esc_html_e('Password', 'duplicator-pro') ?>:
        </label>
        <div class="input">
            <span class="dup-password-toggle width-xlarge"> 
                <input 
                    id="secure-pass" 
                    type="password" 
                    name="secure-pass" 
                    required="required"
                    size="50"
                    maxlength="150"
                    value="<?php echo esc_attr($securePass); ?>" 
                >
                <button type="button" >
                    <i class="fas fa-eye fa-sm"></i>
                </button>
            </span>
            <div class="input dup-tabs-opts-help-secure-pass">
                <?php
                    esc_html_e(
                        'Caution: Passwords are case-sensitive and if lost cannot be recovered.  Please keep passwords in a safe place!',
                        'duplicator-pro'
                    );
                    echo '<br/>';
                    esc_html_e(
                        'If this password is lost then a new archive file will need to be created.',
                        'duplicator-pro'
                    );
                    ?>
            </div>
        </div>
    </div>

    <?php if (!$encryptAvaliable) { ?>
        <div class="dup-form-item">
            <span class="title">
                &nbsp;
            </span>
            <span class="input dup-tabs-opts-notice">
                <i>
                <i class="fas fa-exclamation-triangle fa-xs"></i>
                <?php
                    echo esc_html__("The security mode 'Archive encryption' option above is currently disabled on this server.", 'duplicator-pro') . '<br>'
                        . esc_html($unavaliableMessage);
                ?>
                </i>
            </span>
        </div>
    <?php } ?>

</div>

<script>
    jQuery(function($) {
        DupliJs.EnableInstallerPassword = function () {
            let $button = $('#secure-btn');
            let secureOnVal = $('.secure-on-input-wrapper input:checked').val();

            if (secureOnVal == <?php echo json_encode(ArchiveDescriptor::SECURE_MODE_NONE); ?>) {
                $('#dupli-install-secure-lock-icon').hide();
                $('#secure-pass').removeAttr('required');
                $('#secure-pass').attr('readonly', true);
                $button.prop('disabled', true);
            } else {
                $('#dupli-install-secure-lock-icon').show();
                $('#secure-pass').attr('readonly', false);
                $('#secure-pass').attr('required', 'true').focus();
                $button.prop('disabled', false); 
            }
        };

        $('#secure-on-none').parsley().on('field:error', function() {
            $('.archive-setup-tab button').trigger('click');
            $('html,body').animate({scrollTop: $(".archive-setup-tab").offset().top - 30},'slow');
        });
    });
</script>
