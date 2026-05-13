<?php

/**
 * Template for Duplicator Cloud Connect Step 1
 *
 * @package   Duplicator\Addons\DupCloudAddon
 * @copyright (c) 2024, Snap Creek LLC
 */

use Duplicator\Addons\DupCloudAddon\Models\DupCloudStorage;
use Duplicator\Core\CapMng;

defined('ABSPATH') || exit;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 */
$storage = DupCloudStorage::getUniqueStorage();

if (
    !CapMng::can(CapMng::CAP_STORAGE, false) ||
    !$storage->isAuthorized()
) {
    return;
}
?>
<span>
    <a href="<?php echo esc_url($storage->getBackupsUrl()); ?>" target="_blank"
        id="dup-dupcloud-manage-website"
        class="button button-primary hollow tiny font-bold margin-bottom-0"
        target="_blank"
    >
        <i class="fa-solid fa-cloud"></i>&nbsp;
        <?php esc_html_e('Cloud Dashboard', 'duplicator-pro'); ?>
    </a>
</span>