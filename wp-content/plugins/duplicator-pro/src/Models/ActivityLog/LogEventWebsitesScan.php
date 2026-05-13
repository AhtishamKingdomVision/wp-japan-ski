<?php

namespace Duplicator\Models\ActivityLog;

use Duplicator\Core\CapMng;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\Create\BuildComponents;
use Exception;

/**
 * Log event for backup scanning
 */
class LogEventWebsitesScan extends AbstractLogEvent
{
    const SUB_TYPE_ERROR = 'scan_error';
    const SUB_TYPE_START = 'scan_start';
    const SUB_TYPE_END   = 'scan_end';

    /**
     * Class constructor
     *
     * @param AbstractPackage $package  Package
     * @param string          $status   Status ENUM self::SUB_TYPE_*
     * @param int             $parentId Parent ID, if 0 the event have no event parent
     */
    public function __construct(AbstractPackage $package, string $status, int $parentId = 0)
    {
        $this->data['packageId']   = $package->getId();
        $this->data['packageName'] = $package->getName();
        $this->data['components']  = $package->components;
        $this->data['filterOn']    = $package->Archive->FilterOn;
        $this->data['filterDirs']  = strlen($package->Archive->FilterDirs) > 0 ? explode(';', $package->Archive->FilterDirs) : [];
        $this->data['filterExts']  = strlen($package->Archive->FilterExts) > 0 ? explode(';', $package->Archive->FilterExts) : [];
        $this->data['filterFiles'] = strlen($package->Archive->FilterFiles) > 0 ? explode(';', $package->Archive->FilterFiles) : [];
        $this->data['fileCount']   = $package->Archive->FileCount;
        $this->data['dirCount']    = $package->Archive->DirCount;
        $this->data['size']        = $package->Archive->Size;

        // Database table count
        $this->data['dbExcluded']   = BuildComponents::isDBExcluded($package->components);
        $this->data['dbTableCount'] = 0;
        $this->data['dbSize']       = 0;
        $this->data['dbRowCount']   = 0;

        if (!$this->data['dbExcluded'] && isset($package->Database->info)) {
            $this->data['dbTableCount'] = $package->Database->info->tablesFinalCount;
            $this->data['dbSize']       = $package->Database->info->tablesSizeOnDisk;
            $this->data['dbRowCount']   = $package->Database->info->tablesRowCount;
        }

        // Store scan timing data atomically
        $scanTimingData              = $package->getExecutionTimingData();
        $this->data['scanTimeStart'] = $scanTimingData['scanTimeStart'] ?? 0;
        $this->data['scanTimeEnd']   = $scanTimingData['scanTimeEnd'] ?? 0;

        switch ($status) {
            case self::SUB_TYPE_ERROR:
                $this->title = __('Scan Error', 'duplicator-pro');
                break;
            case self::SUB_TYPE_START:
                $this->title = __('Scan', 'duplicator-pro');
                break;
            case self::SUB_TYPE_END:
                $this->title = __('Scan Completed', 'duplicator-pro');
                break;
            default:
                throw new Exception('Invalid status: ' . $status);
        }
        $this->subType  = $status;
        $this->severity = $this->subType === self::SUB_TYPE_ERROR ? self::SEVERITY_ERROR : self::SEVERITY_INFO;
        $this->parentId = $parentId;
    }

    /**
     * Return entity type identifier
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'websites_scan';
    }

    /**
     * Return entity type label
     *
     * @return string
     */
    public static function getTypeLabel(): string
    {
        return __('Websites Scan', 'duplicator-pro');
    }

    /**
     * Return required capability for this log event
     *
     * @return string
     */
    public static function getCapability(): string
    {
        return CapMng::CAP_BASIC;
    }

    /**
     * Return short description
     *
     * @return string
     */
    public function getShortDescription(): string
    {
        switch ($this->subType) {
            case self::SUB_TYPE_ERROR:
                return __('Scan Error', 'duplicator-pro');
            case self::SUB_TYPE_START:
                // Show scan initiation details with what will be scanned
                $description = sprintf(
                    __('Scan started: %1$s components', 'duplicator-pro'),
                    BuildComponents::displayComponentsList($this->data['components'], ", ")
                );

                // Add filter info if filters are applied
                if ($this->data['filterOn']) {
                    $filterCount  = count($this->data['filterDirs']) + count($this->data['filterFiles']) + count($this->data['filterExts']);
                    $description .= sprintf(
                        __('; %d filters applied', 'duplicator-pro'),
                        $filterCount
                    );
                }

                return $description;
            case self::SUB_TYPE_END:
                $description = sprintf(
                    __('Scan completed: %1$d files, %2$d directories; size: %3$s', 'duplicator-pro'),
                    $this->data['fileCount'],
                    $this->data['dirCount'],
                    SnapString::byteSize((int)$this->data['size'])
                );

                // Add database information if available
                if (!$this->data['dbExcluded'] && $this->data['dbTableCount'] > 0) {
                    $description .= sprintf(
                        __('; DB: %1$d tables, %2$d rows, %3$s', 'duplicator-pro'),
                        $this->data['dbTableCount'],
                        $this->data['dbRowCount'] ?? 0,
                        SnapString::byteSize($this->data['dbSize'] ?? 0)
                    );
                }

                return $description;
            default:
                return __('Scan', 'duplicator-pro');
        }
    }

    /**
     * Get execution time for scan phase using atomic timing data
     *
     * @param string $subType The sub-event type (unused for scan, kept for interface consistency)
     *
     * @return string Formatted execution time string
     */
    public function getExecutionTimeForPhase(string $subType): string
    {
        // Use atomic timing data stored in $this->data - no cross-queries
        $start = $this->data['scanTimeStart'] ?? 0;
        $end   = $this->data['scanTimeEnd'] ?? 0;

        // If we don't have both start and end times, return N/A
        if ($start <= 0 || $end <= 0) {
            return __('N/A', 'duplicator-pro');
        }

        $duration = max(0, $end - $start);

        // For durations less than 1 second, show decimal seconds
        if ($duration < 1) {
            return sprintf(__('%.1f sec', 'duplicator-pro'), $duration);
        }

        return SnapString::formatHumanReadableDuration($duration, 0);
    }

    /**
     * Update timing data for this log
     *
     * @param array<string,mixed> $timingData Timing data to merge into this log's data
     *
     * @return void
     */
    public function updateTimingData(array $timingData): void
    {
        if (isset($timingData['scanTimeStart'])) {
            $this->data['scanTimeStart'] = $timingData['scanTimeStart'];
        }
        if (isset($timingData['scanTimeEnd'])) {
            $this->data['scanTimeEnd'] = $timingData['scanTimeEnd'];
        }
    }

    /**
     * Display detailed information in html format
     *
     * @return void
     */
    public function detailHtml(): void
    {
        ?>
        <div class="dup-log-detail-meta">
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Components:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html(BuildComponents::displayComponentsList($this->data['components'], ", ")); ?>
                </span>
            </div>
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Filter On:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html($this->data['filterOn'] ? __('Yes', 'duplicator-pro') : __('No', 'duplicator-pro')); ?>
                </span>
            </div>


            <?php if (!$this->data['dbExcluded'] && $this->data['dbTableCount'] > 0) : ?>
                <div class="dup-log-type-wrapper">
                    <strong><?php esc_html_e('Database Tables:', 'duplicator-pro'); ?></strong>
                    <span class="dup-log-type">
                        <?php echo esc_html(number_format($this->data['dbTableCount'])); ?>
                    </span>
                </div>
                <div class="dup-log-type-wrapper">
                    <strong><?php esc_html_e('Database Size:', 'duplicator-pro'); ?></strong>
                    <span class="dup-log-type">
                        <?php echo esc_html(SnapString::byteSize($this->data['dbSize'])); ?>
                    </span>
                </div>
                <?php if ($this->data['dbRowCount'] > 0) : ?>
                    <div class="dup-log-type-wrapper">
                        <strong><?php esc_html_e('Database Rows:', 'duplicator-pro'); ?></strong>
                        <span class="dup-log-type">
                            <?php echo esc_html(number_format($this->data['dbRowCount'])); ?>
                        </span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($this->data['filterOn']) : ?>
                <?php if (count($this->data['filterDirs']) > 0) : ?>
                    <div class="dup-log-type-wrapper">
                        <strong><?php esc_html_e('Filter Dirs:', 'duplicator-pro'); ?></strong><br>
                        <?php foreach ($this->data['filterDirs'] as $dir) : ?>
                            - <?php echo esc_html($dir); ?><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (count($this->data['filterFiles']) > 0) : ?>
                    <div class="dup-log-type-wrapper">
                        <strong><?php esc_html_e('Filter Files:', 'duplicator-pro'); ?></strong><br>
                        <?php foreach ($this->data['filterFiles'] as $file) : ?>
                            - <?php echo esc_html($file); ?><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (count($this->data['filterExts']) > 0) : ?>
                    <div class="dup-log-type-wrapper">
                        <strong><?php esc_html_e('Filter Exts:', 'duplicator-pro'); ?></strong>
                        <span class="dup-log-type">
                            <?php echo esc_html(implode(', ', $this->data['filterExts'])); ?>
                        </span>
                    </div>
                <?php endif; ?>
                <?php
            endif;
            switch ($this->subType) {
                case self::SUB_TYPE_START:
                    $subEvents = array_merge(
                        [$this],
                        self::getList(
                            [
                                'parent_id' => $this->getId(),
                                'order'     => 'ASC',
                                'orderby'   => 'created_at',
                            ]
                        )
                    );

                    if (count($subEvents) > 0) {
                        ?>
                        <div class="margin-top-1">
                            <?php TplMng::getInstance()->render(
                                'admin_pages/activity_log/parts/sub_table_mini',
                                [
                                    'logs'      => $subEvents,
                                    'parentLog' => $this,
                                ]
                            ); ?>
                        </div>
                        <?php
                    }
                    break;
                case self::SUB_TYPE_END:
                    ?>
                    <div class="dup-activity-log-scan-end">
                        <div class="dup-log-type-wrapper">
                            <strong><?php esc_html_e('File Count:', 'duplicator-pro'); ?></strong>
                            <span class="dup-log-type">
                                <?php echo esc_html($this->data['fileCount']); ?>
                            </span>
                        </div>
                        <div class="dup-log-type-wrapper">
                            <strong><?php esc_html_e('Directory Count:', 'duplicator-pro'); ?></strong>
                            <span class="dup-log-type">
                                <?php echo esc_html($this->data['dirCount']); ?>
                            </span>
                        </div>
                        <div class="dup-log-type-wrapper">
                            <strong><?php esc_html_e('Size:', 'duplicator-pro'); ?></strong>
                            <span class="dup-log-type">
                                <?php echo esc_html(SnapString::byteSize((int)$this->data['size'])); ?>
                            </span>
                        </div>
                    </div>
                    <?php
                    break;
            }
            ?>
        </div>
        <?php
    }

    /**
     * Return object type label, can be overridden by child classes
     * by default it returns the same as static::getTypeLabel() but can change in base of object properties
     *
     * @return string
     */
    public function getObjectTypeLabel(): string
    {
        switch ($this->subType) {
            case self::SUB_TYPE_ERROR:
                return __('Scan Error', 'duplicator-pro');
            case self::SUB_TYPE_START:
                return __('Scan Start', 'duplicator-pro');
            case self::SUB_TYPE_END:
                return __('Scan End', 'duplicator-pro');
            default:
                return __('Scan', 'duplicator-pro');
        }
    }
}
