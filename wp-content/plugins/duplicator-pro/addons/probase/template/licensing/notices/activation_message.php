<?php

/**
 * @package Duplicator
 */

use Duplicator\Libs\Snap\SnapUtil;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

if (empty($tplData['license_message'])) {
    return;
}

$details = "";
if (isset($tplData['license_request_error'])) {
    $details =  'Message: ' . $tplData['license_request_error']['message'] . "\n" .
                'Error code: ' . $tplData['license_request_error']['code'];
    if (strlen($tplData['license_request_error']['requestDetails'])) {
        $details .= "\n\n" . 'Request Details' . "\n" . $tplData['license_request_error']['requestDetails'];
    }
    if (strlen($tplData['license_request_error']['details'])) {
        $details .= "\n" . 'Response Details' . "\n" . $tplData['license_request_error']['details'];
    }
}

?>
<p>
    <?php if (!$tplData['license_success']) { ?>
        <i class="fa fa-exclamation-triangle"></i>&nbsp;
        <?php
    }
    echo wp_kses(
        $tplData['license_message'],
        [
            'a'    => [
                'href'   => [],
                'target' => [],
            ],
            'span' => [
                'class' => [],
            ],
            'br'   => [],
            'b'    => [
                'class' => [],
            ],
        ]
    );
    ?>
</p>
<?php if (isset($tplData['license_request_error'])) { ?>
    <p>
        <?php echo esc_html__('Error:', 'duplicator-pro') ?> <b><?php echo esc_html($tplData['license_request_error']['message']); ?></b>
    </p>
    <b>Details:</b>
    <textarea class="dup-error-message-textarea" disabled ><?php echo esc_textarea($details); ?></textarea>
    <button
        data-dup-copy-value="<?php echo esc_attr($details); ?>"
        data-dup-copy-title="<?php echo esc_attr("Copy Error Message to clipboard"); ?>"
        data-dup-copied-title="<?php echo esc_attr("Error Message copied to clipboard"); ?>"
        class="button dup-btn-copy-error-message">
        <?php esc_html_e('Copy error details', 'duplicator-pro'); ?>
    </button>
    <?php if (!SnapUtil::isCurlEnabled()) {
        $tplMng->render('licensing/notices/curl_message');
    } ?>
    <p>
        <?php
        printf(
            wp_kses(
                __("If the error persists please open a ticket <a href=\"%s\">here</a> and attach the errors details.", 'duplicator-pro'),
                [
                    'a' => [
                        'href' => [],
                    ],
                ]
            ),
            esc_url(DUPLICATOR_BLOG_URL . 'my-account/support/')
        );
        ?>
    </p>
<?php } ?>
