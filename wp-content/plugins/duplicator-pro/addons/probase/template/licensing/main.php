<?php

/**
 * @package Duplicator
 */

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Addons\ProBase\LicensingController;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

?>
<div class="dup-settings-wrapper" >
    <?php $tplMng->render('licensing/activation'); ?>
</div>
<script>
    jQuery(document).ready(function($) {
        DupliJs.Licensing = new Object();
        DupliJs.Licensing.VISIBILITY_ALL = <?php echo (int) License::VISIBILITY_ALL;?>;
        DupliJs.Licensing.VISIBILITY_INFO = <?php echo (int) License::VISIBILITY_INFO;?>;
        DupliJs.Licensing.VISIBILITY_NONE = <?php echo (int) License::VISIBILITY_NONE;?>;

        $("#_key_password, #_key_password_confirmation").keyup(function(event) {

            if (event.keyCode == 13) {
                $("#show_hide").click();
            }
        });

        DupliJs.Licensing.ChangeActivationStatus = function(activate) {
            if (activate) {
                let licenseKey = $('.dup-license-key-input').val();
                window.location.href = 
                    <?php echo json_encode($tplData['actions'][LicensingController::ACTION_ACTIVATE_LICENSE]->getUrl()); ?> + 
                    '&_license_key=' + encodeURIComponent(licenseKey);
            } else {
                window.location.href = <?php echo json_encode($tplData['actions'][LicensingController::ACTION_DEACTIVATE_LICENSE]->getUrl()); ?>;
            }
            return false;
        }

        DupliJs.Licensing.ClearActivationStatus = function() {
            window.location.href = <?php echo json_encode($tplData['actions'][LicensingController::ACTION_CLEAR_KEY]->getUrl()); ?>;
        }

        DupliJs.Licensing.ChangeKeyVisibility = function(show) {
            $('#dup-license-visibility-form').submit();
        }

        DupliJs.Licensing.VisibilityTemporary = function(visibility) {
            switch (visibility) {
                case DupliJs.Licensing.VISIBILITY_ALL:
                    $("#dup-tr-license-dashboard").show();
                    $("#dup-tr-license-type").show();
                    $("#dup-tr-license-key-and-description").show();
                    break;
                case DupliJs.Licensing.VISIBILITY_INFO:
                    $("#dup-tr-license-dashboard").show();
                    $("#dup-tr-license-type").show();
                    $("#dup-tr-license-key-and-description").hide();
                    break;
                case DupliJs.Licensing.VISIBILITY_NONE:
                    $("#dup-tr-license-dashboard").hide();
                    $("#dup-tr-license-type").hide();
                    $("#dup-tr-license-key-and-description").hide();
                    break;
                default:
                    alert("Unexpected visibility value!");
            }
        }
    });
</script>
