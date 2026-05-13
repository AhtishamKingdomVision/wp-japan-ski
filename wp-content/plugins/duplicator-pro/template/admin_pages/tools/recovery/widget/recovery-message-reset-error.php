<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */
?>
<div class="dupli-recovery-message" >
    <p class="recovery-reset-message-error">
        <i class="fa fa-exclamation-triangle"></i> <b><?php _e('Recovery Point reset issue!', 'duplicator-pro'); ?></b>
    <p>
    <p class="recovery-error-message">
        <!-- here is set the message received from the server -->
    </p>
</div>
