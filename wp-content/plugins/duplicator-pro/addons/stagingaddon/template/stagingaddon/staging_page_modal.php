<?php

/**
 * Staging page create modal partial
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

use Duplicator\Addons\StagingAddon\StagingSiteHandler;

/** @var array<string, \Duplicator\Package\DupPackage[]> $backupsByDate */
$backupsByDate = $tplData['backupsByDate'];
$colorSchemes  = StagingSiteHandler::getValidColorSchemes();
?>
<div id="dupli-staging-create-modal-content" class="dupli-modal-content-hidden">
    <div class="dup-box dupli-staging-create-modal margin-0">
        <div class="dup-box-title no-toggle">
            <i class="fas fa-clone"></i> <?php esc_html_e('Create New Staging Site', 'duplicator-pro'); ?>
        </div>
        <div class="dup-box-panel">
            <form id="dupli-staging-create-form">
                <label class="lbl-larger">
                    <?php esc_html_e('Source Backup', 'duplicator-pro'); ?>
                </label>
                <div class="margin-bottom-1">
                    <select id="dupli-staging-backup-select" name="backup_id" required>
                        <option value=""> -- <?php esc_html_e('Select a backup', 'duplicator-pro'); ?> -- </option>
                        <?php foreach ($backupsByDate as $dateKey => $dateBackups) : ?>
                            <optgroup label="<?php echo esc_attr($dateKey); ?>">
                                <?php /** @var \Duplicator\Package\DupPackage $backup */ foreach ($dateBackups as $backup) : ?>
                                    <option value="<?php echo esc_attr((string) $backup->getId()); ?>">
                                        [<?php echo esc_html(date_i18n(get_option('time_format'), strtotime($backup->getCreated()))); ?>]
                                        <?php echo esc_html($backup->getName()); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Choose the backup to create a staging site from.', 'duplicator-pro'); ?>
                    </p>
                </div>

                <label class="lbl-larger">
                    <?php esc_html_e('Staging Title', 'duplicator-pro'); ?>
                </label>
                <div class="margin-bottom-1">
                    <input type="text"
                           id="dupli-staging-title"
                           name="title"
                           class="regular-text"
                           value="<?php echo esc_attr($tplData['defaultStagingTitle']); ?>"
                           placeholder="<?php esc_attr_e('My Staging Site', 'duplicator-pro'); ?>">
                    <p class="description">
                        <?php esc_html_e('Optional: A friendly name for this staging site.', 'duplicator-pro'); ?>
                    </p>
                </div>

                <label class="lbl-larger">
                    <?php esc_html_e('Admin Color Scheme', 'duplicator-pro'); ?>
                </label>
                <div class="margin-bottom-1">
                    <select id="dupli-staging-color-scheme" name="color_scheme">
                        <?php foreach ($colorSchemes as $scheme) : ?>
                            <option value="<?php echo esc_attr($scheme); ?>" <?php selected($scheme, 'sunrise'); ?>>
                                <?php echo esc_html(ucfirst($scheme)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Choose a color scheme to visually distinguish this staging site.', 'duplicator-pro'); ?>
                    </p>
                </div>

                <div class="dupli-staging-validation-result" id="dupli-staging-validation-result"></div>

                <hr>
                <div class="dupli-staging-form-actions">
                    <button type="button" id="dupli-staging-cancel-btn" class="button secondary hollow">
                        <?php esc_html_e('Cancel', 'duplicator-pro'); ?>
                    </button>
                    <button type="submit" id="dupli-staging-create-btn" class="button primary">
                        <i class="fas fa-plus-circle fa-sm"></i> <?php esc_html_e('Create Staging Site', 'duplicator-pro'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
