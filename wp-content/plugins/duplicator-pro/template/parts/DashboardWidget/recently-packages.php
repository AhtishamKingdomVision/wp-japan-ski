<?php

/**
 * Duplicator Backup row in table Backups list
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\PackagesPageController;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var \Duplicator\Package\DupPackage[] $packages
 */
$packages = $tplData['packages'];

?>
<hr class="separator">
<div class="dup-section-last-packages">
    <p>
        <b><?php esc_html_e('Recently Backups', 'duplicator-pro'); ?></b>
    </p>
    <?php if (count($packages) > 0) { ?>
        <ul>
            <?php foreach ($packages as $package) {
                $createdTime  = strtotime($package->getCreated());
                $createdDate  = date_i18n(get_option('date_format'), $createdTime);
                $createdHours = date_i18n(get_option('time_format'), $createdTime);

                ?>
                <li>
                    <a href="<?php echo esc_url(PackagesPageController::getInstance()->getPackageDetailsURL($package->getId())); ?>">
                        <?php echo esc_html($package->getName()); ?>
                    </a> - <i class="gary"><?php echo esc_html($createdDate . ' ' .  $createdHours); ?></i>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
    <p class="dup-packages-counts">
        <?php printf(esc_html__('Backups: %1$d, Failures: %2$d', 'duplicator-pro'), (int) $tplData['totalPackages'], (int) $tplData['totalFailures']); ?>
    </p>
</div>