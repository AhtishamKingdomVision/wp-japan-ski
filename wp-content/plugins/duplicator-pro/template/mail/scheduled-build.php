<?php

/**
 * Duplicator schedule success mail
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */
?>
<p><?php echo esc_html($tplData['messageTitle']) ?></p>
<p>
    <strong><?php esc_html_e('Backup Name', 'duplicator-pro') ?>: </strong><?php echo esc_html($tplData['packageName']); ?><br/>
    <strong><?php esc_html_e('Backup ID', 'duplicator-pro') ?>: </strong><?php echo esc_html($tplData['packageID']); ?><br/>
    <strong><?php esc_html_e('Date', 'duplicator-pro') ?>: </strong><?php echo esc_html(date_i18n('Y-m-d H:i:s')); ?><br/>
    <strong><?php esc_html_e('Schedule', 'duplicator-pro') ?>: </strong><?php echo esc_html($tplData['scheduleName']); ?>
</p>

<?php if ($tplData['success']) : ?>
<p>
    <strong><?php esc_html_e('Number of Files', 'duplicator-pro') ?>: </strong><?php echo esc_html($tplData['fileCount']); ?><br/>
    <strong><?php esc_html_e('Backup size', 'duplicator-pro') ?>: </strong><?php echo esc_html($tplData['packageSize']); ?>
</p>
<p>
    <strong><?php esc_html_e('Number of tables', 'duplicator-pro') ?>: </strong><?php echo esc_html($tplData['tableCount']); ?><br/>
    <strong><?php esc_html_e('DB dump size', 'duplicator-pro') ?>: </strong><?php echo esc_html($tplData['sqlSize']); ?>
</p>
<?php endif; ?>

<p>
    <strong><?php esc_html_e('Storages', 'duplicator-pro') ?>: </strong>
    <?php foreach ($tplData['storageNames'] as $storageName) : ?>
        <br/> - <?php echo esc_html($storageName); ?>
    <?php endforeach; ?>
</p>
<p>
    <?php
        printf(
            esc_html_x(
                'To go to the "Backups" screen %1$sclick here%2$s.',
                '%1$s and %2$s represent the opening and closing anchor tags (<a> and </a>)',
                'duplicator-pro'
            ),
            '<a href="' . esc_url($tplData['packagesLink']) . '" target="_blank">',
            '</a>'
        );
        ?>
</p>
<?php if ($tplData['logExists']) : ?>
<p>
    <?php esc_html_e('Log is attached.', 'duplicator-pro'); ?>
</p>
<?php endif; ?>
