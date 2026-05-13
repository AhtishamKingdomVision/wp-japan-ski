<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\ProBase;

use Duplicator\Models\ScheduleEntity;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Addons\ProBase\Models\LicenseData;

class DrmHandler
{
    const SCHEDULE_DRM_DELAY_DAYS = 14;

    /**
     * Check if the license has a schedule delay to DRM.
     *
     * This method determines if the current license type allows for a schedule delay
     * to DRM (Digital Rights Management). It returns true if the license type is not
     * one of the restricted types that do not allow for a schedule delay.
     *
     * @return bool True if there is a schedule delay, false otherwise.
     */
    protected static function haveLicenseScheduleDelayToDRM(): bool
    {
        $licenseType = LicenseData::getInstance()->getLicenseType();
        if (is_multisite()) {
            $noScheduleDRMTypes = [
                License::TYPE_UNKNOWN,
                License::TYPE_UNLICENSED,
                License::TYPE_BASIC,
                License::TYPE_PLUS,
            ];
        } else {
            $noScheduleDRMTypes = [
                License::TYPE_UNKNOWN,
                License::TYPE_UNLICENSED,
            ];
        }
        return !in_array($licenseType, $noScheduleDRMTypes);
    }

    /**
     * Get the number of days until the scheduled DRM activation.
     *
     * @return int Returns the number of days left until the scheduled DRM activation, or -1 if there are no days left.
     */
    public static function getDaysTillScheduleDRM()
    {
        if (count(ScheduleEntity::getActive()) == 0) {
            // No active schedules, no need to check DRM
            return -1;
        }
        $status = LicenseData::getInstance()->getStatus();
        if (!in_array($status, [LicenseData::STATUS_VALID, LicenseData::STATUS_EXPIRED])) {
            return -1;
        }
        if (!self::haveLicenseScheduleDelayToDRM()) {
            return -1;
        }
        if (($expiresDays = LicenseData::getInstance()->getExpirationDays()) === false) {
            return -1;
        }
        return self::SCHEDULE_DRM_DELAY_DAYS + $expiresDays;
    }
}
