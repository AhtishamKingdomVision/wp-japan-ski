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

if (!isset($tplData['tmpCleanUpSuccess'])) {
    return;
}

$messageClasses = [
    'notice',
    'dupli-admin-notice',
    'is-dismissible',
    'dupli-diagnostic-action-tmp-cache',
    'notice-success',
];

?>
<div id="message" class="<?php echo esc_attr(implode(' ', $messageClasses)); ?>">
    <p>
        <?php esc_html_e('Build cache removed.', 'duplicator-pro'); ?>
    </p>
</div>
