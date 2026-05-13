<?php

/**
 * Template for displaying backup log context in activity log error events
 *
 * @package   Duplicator
 * @copyright (c) 2025, Snap Creek LLC
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var string[] $logLines Array of log lines to display
 * @var string   $logUrl   URL to the full log file
 */

$logLines = $tplMng->getDataValueArray('logLines', []);
$logUrl   = $tplMng->getDataValueString('logUrl', '');
?>
<div class="dup-error-logs-section">
    <div class="dup-error-logs-content">
        <div class="dup-log-content">
            <pre><?php echo esc_html(implode("\n", $logLines)); ?></pre>
        </div>

        <div class="dup-log-meta">
            <?php printf(esc_html__('Showing %d lines from when error occurred', 'duplicator-pro'), count($logLines)); ?>
            <?php if (!empty($logUrl)) : ?>
                | <a href="<?php echo esc_url($logUrl); ?>" target="_blank"><?php esc_html_e('View complete log file', 'duplicator-pro'); ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>
