<?php

/**
 * @package Duplicator
 */

use Duplicator\Utils\Support\SupportToolkit;

defined("ABSPATH") or die("");

/**
 * Admin page packages list
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 *
 * @var Duplicator\Core\Controllers\ControllersManager  $ctrlMng
 * @var Duplicator\Core\Views\TplMng                    $tplMng
 * @var array<string, mixed> $tplData
 */

$storageInfos = $tplData['storageInfos'] ?? [];
if (count($storageInfos) < 1) {
    return;
}

$isAllDownload  = $tplData['isAllDownload'] ?? false;
$isAllUpload    = $tplData['isAllUpload'] ?? false;
$failureMessage = $tplData['failureMessage'] ?? __('There was a problem transferring the backup(s).', 'duplicator-pro');
?>

<p> <?php echo wp_kses($failureMessage, [ 'b' => [], 'i' => [] ]); ?> </p>
<?php if (count($storageInfos) > 1) { ?>
    <ul>
        <?php foreach ($storageInfos as $info) { ?>
        <li>
            <b><?php echo esc_html($info['name']); ?></b> (<?php echo esc_html($info['type']); ?>)
            <?php if (!$isAllDownload && !$isAllUpload) { ?>
            -
            <b class="maroon">
                <?php echo $info['isDownload'] ? esc_html__('Download Failed', 'duplicator-pro') : esc_html__('Upload Failed', 'duplicator-pro'); ?>
            </b>
            <?php } ?>
        </li>
        <?php } ?>
    </ul>
<?php } ?>
<p><?php esc_html_e('Please try transferring again.', 'duplicator-pro'); ?></p>
<p>
<?php echo wp_kses(
    sprintf(
        esc_html_x(
            'If the issue persists, %1$scontact support%2$s with the %3$s attached to the ticket.',
            '1: open link tag, 2: close link tag, 3: diagnostic data link with label or link to instructions to download the Backup logs',
            'duplicator-pro'
        ),
        '<a href="' . esc_url(DUPLICATOR_BLOG_URL . 'my-account/support/') . '" target="_blank">',
        '</a>',
        SupportToolkit::getDiagnosticInfoLinks(['package'])
    ),
    [
        'a' => [
            'href'   => [],
            'target' => [],
        ],
    ]
); ?>
</p>
