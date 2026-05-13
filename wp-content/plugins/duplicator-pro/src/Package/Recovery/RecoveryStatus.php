<?php

namespace Duplicator\Package\Recovery;

use Duplicator\Package\DupPackage;
use Exception;
use Duplicator\Models\TemplateEntity;
use Duplicator\Models\ScheduleEntity;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Package\Import\PackageImporter;
use Duplicator\Libs\WpUtils\PathUtil;

/**
 * Class RecoveryStatus
 *
 * This class is designed to help control the various stages and associates
 * that are used to keep track of the RecoveryPoint statuses
 *
 * @phpstan-type IneligibilityReasons array{
 *     missing_components?: string[],
 *     db_only?: bool,
 *     filtered_wp_dirs?: string[],
 *     filtered_db_tables?: string[],
 *     filtered_subsites?: int[]
 * }
 */
class RecoveryStatus
{
    const TYPE_PACKAGE  = 'PACKAGE';
    const TYPE_SCHEDULE = 'SCHEDULE';
    const TYPE_TEMPLATE = 'TEMPLATE';

    const COMPONENTS_REQUIRED = [
        BuildComponents::COMP_DB,
        BuildComponents::COMP_CORE,
        BuildComponents::COMP_PLUGINS,
        BuildComponents::COMP_THEMES,
        BuildComponents::COMP_UPLOADS,
    ];

    /** @var AbstractPackage|TemplateEntity|ScheduleEntity */
    protected $object;
    protected string $objectType = '';
    /** @var ?array{dbonly:bool,filterDirs:string[],filterTables:string[],filterSubsites:int[],components:string[]} */
    protected $filteredData;
    /** @var false|TemplateEntity */
    private $activeTemplate = false;

    /**
     * Class constructor
     *
     * @param AbstractPackage|TemplateEntity|ScheduleEntity $object entity object
     */
    public function __construct($object)
    {
        if (!is_object($object)) {
            throw new Exception("Input must be of type object");
        }

        if (is_a($object, AbstractPackage::class)) {
            $this->objectType = self::TYPE_PACKAGE;
        } elseif ($object instanceof ScheduleEntity) {
            $this->objectType     = self::TYPE_SCHEDULE;
            $this->activeTemplate = TemplateEntity::getById($object->template_id);
        } elseif ($object instanceof TemplateEntity) {
            $this->objectType     = self::TYPE_TEMPLATE;
            $this->activeTemplate = $object;
        } else {
            throw new Exception('Object must be of a valid object');
        }
        $this->object = $object;

        // Init filtered data
        $this->getFilteredData();
    }

    /**
     * Get the literal type name based on the recovery status object being evaluated
     *
     * @return string Returns the recovery status object type literal
     */
    public function getType(): string
    {
        return $this->objectType;
    }

    /**
     * Return recovery status object
     *
     * @return DupPackage|TemplateEntity|ScheduleEntity
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * Get the type name based on the recovery status object being evaluated
     *
     * @return string     Returns the recovery status object type by name PACKAGE | SCHEDULE | TEMPLATE
     */
    public function getTypeLabel(): string
    {
        switch ($this->objectType) {
            case self::TYPE_PACKAGE:
                return self::TYPE_PACKAGE;
            case self::TYPE_SCHEDULE:
                return self::TYPE_SCHEDULE;
            case self::TYPE_TEMPLATE:
                return self::TYPE_TEMPLATE;
        }

        return '';
    }

    /**
     * Return true if current object is recoveable
     *
     * @return bool
     */
    public function isRecoverable()
    {
        if (
            ($this->object instanceof DupPackage) &&
            version_compare($this->object->getVersion(), PackageImporter::IMPORT_ENABLE_MIN_VERSION, '<')
        ) {
            return false;
        }

        return $this->isLocalStorageEnabled() && $this->meetsRecoveryRequirements();
    }


    /**
     * Return true if current object meets recovery requirements.
     *
     * When $reasons is passed, it is populated with the structured ineligibility data
     * for each failing condition (keys present only when the condition fails).
     *
     * @param IneligibilityReasons $reasons Populated by reference with ineligibility data
     *
     * @return bool
     */
    public function meetsRecoveryRequirements(array &$reasons = []): bool
    {
        $meets = true;

        if (!$this->hasRequiredComponents()) {
            $meets                         = false;
            $reasons['missing_components'] = array_values(
                array_diff(self::COMPONENTS_REQUIRED, $this->filteredData['components'])
            );
        }

        if (!$this->isWordPressCoreComplete()) {
            $meets                       = false;
            $reasons['db_only']          = $this->filteredData['dbonly'];
            $reasons['filtered_wp_dirs'] = $this->filteredData['dbonly']
                ? PathUtil::getWPCoreDirs()
                : $this->filteredData['filterDirs'];
        }

        if (!$this->isDatabaseComplete()) {
            $meets                         = false;
            $reasons['filtered_db_tables'] = $this->filteredData['filterTables'];
        }

        if (!$this->isMultisiteComplete()) {
            $meets                        = false;
            $reasons['filtered_subsites'] = $this->filteredData['filterSubsites'];
        }

        return $meets;
    }

    /**
     * Convert ineligibility reasons array into human-readable messages.
     *
     * @param IneligibilityReasons $reasons Reasons from meetsRecoveryRequirements()
     *
     * @return string[]
     */
    public static function reasonsToMessages(array $reasons): array
    {
        $messages = [];

        if (!empty($reasons['db_only'])) {
            $messages[] = __('Database-only backups cannot be used.', 'duplicator-pro');
        }

        if (!empty($reasons['missing_components'])) {
            $messages[] = sprintf(
                __('Backup is missing required components: %s', 'duplicator-pro'),
                implode(', ', $reasons['missing_components'])
            );
        }

        if (!empty($reasons['filtered_wp_dirs'])) {
            $messages[] = __('Backup has filtered WordPress core directories.', 'duplicator-pro');
        }

        if (!empty($reasons['filtered_db_tables'])) {
            $messages[] = __('Backup has filtered database tables.', 'duplicator-pro');
        }

        if (!empty($reasons['filtered_subsites'])) {
            $messages[] = __('Backup has filtered multisite subsites.', 'duplicator-pro');
        }

        return $messages;
    }

    /**
     * Is the local storage type enabled for the various object types
     *
     * @return bool Returns true if the object type has a local default storage associated with it
     *
     * @notes:
     * Templates do not have local storage associations so the result will always be true for that type
     */
    public function isLocalStorageEnabled()
    {
        $isEnabled = false;

        if ($this->object instanceof DupPackage) {
            $isEnabled = ($this->object->getLocalPackageFilePath(AbstractPackage::FILE_TYPE_ARCHIVE) !== false);
        } elseif ($this->object instanceof ScheduleEntity) {
            if (in_array(StoragesUtil::getDefaultStorageId(), $this->object->storage_ids)) {
                $isEnabled = true;
            } else {
                foreach ($this->object->storage_ids as $id) {
                    if (($storage = AbstractStorageEntity::getById($id)) === false) {
                        continue;
                    }
                    if ($storage->isLocal()) {
                        $isEnabled = true;
                        break;
                    }
                }
            }
        } elseif ($this->object instanceof TemplateEntity) {
            $isEnabled = true;
        }

        return $isEnabled;
    }

    /**
     * Returns true of the Backup components are set to their default value
     *
     * @return bool
     */
    public function hasRequiredComponents(): bool
    {
        return array_intersect(
            self::COMPONENTS_REQUIRED,
            $this->filteredData['components']
        ) === self::COMPONENTS_REQUIRED;
    }

    /**
     * Returns package has component
     *
     * @param string $component component name
     *
     * @return bool
     */
    public function hasComponent($component): bool
    {
        return in_array($component, $this->filteredData['components']);
    }

    /**
     * Is the object type filtering out any of the WordPress core directories
     *
     * @return bool     Returns true if the object type has all the proper WordPress core folders
     *
     * @notes:
     *  - The WP core directories include WP -> admin, content and includes
     */
    public function isWordPressCoreComplete(): bool
    {
        return (
            $this->filteredData['dbonly'] == false &&
            count($this->filteredData['filterDirs']) == 0
        );
    }

    /**
     * Is the object type filtering out any Database tables that have the WordPress prefix
     *
     * @return bool Returns true if the object type filters out any database tables
     */
    public function isDatabaseComplete(): bool
    {
        return (count($this->filteredData['filterTables']) == 0);
    }

    /**
     * Is multisite complete
     *
     * @return bool
     */
    public function isMultisiteComplete(): bool
    {
        return (count($this->filteredData['filterSubsites']) == 0);
    }

    /**
     * Return filtered datat from entity
     *
     * @return array{dbonly:bool,filterDirs:string[],filterTables:string[],filterSubsites:int[],components:string[]}
     */
    public function getFilteredData()
    {
        if ($this->filteredData === null) {
            $dbOnly        = false;
            $components    = [];
            $filterDirs    = [];
            $filterTables  = [];
            $filterSusites = [];


            switch (get_class($this->object)) {
                case DupPackage::class:
                    $dbOnly     = $this->object->isDBOnly();
                    $components = $this->object->components;

                    if (filter_var($this->object->Archive->FilterOn, FILTER_VALIDATE_BOOLEAN) && strlen($this->object->Archive->FilterDirs) > 0) {
                        $filterDirs = explode(';', $this->object->Archive->FilterDirs);
                        $filterDirs = array_intersect($filterDirs, PathUtil::getWPCoreDirs());
                    }

                    if (
                        filter_var($this->object->Database->FilterOn, FILTER_VALIDATE_BOOLEAN) &&
                        strlen($this->object->Database->FilterTables) > 0
                    ) {
                        $filterTables = SnapWP::getTablesWithPrefix(explode(',', $this->object->Database->FilterTables));
                    }

                    $filterSusites = $this->object->Multisite->FilterSites;
                    break;
                case ScheduleEntity::class:
                case TemplateEntity::class:
                    if ($this->activeTemplate === false) {
                        break;
                    }
                    $dbOnly     = BuildComponents::isDBOnly($this->activeTemplate->components);
                    $components = $this->activeTemplate->components;

                    if (
                        filter_var(
                            $this->activeTemplate->archive_filter_on,
                            FILTER_VALIDATE_BOOLEAN
                        ) &&
                        strlen($this->activeTemplate->archive_filter_dirs) > 0
                    ) {
                        $filterDirs = explode(';', $this->activeTemplate->archive_filter_dirs);
                        $filterDirs = array_intersect($filterDirs, PathUtil::getWPCoreDirs());
                    }

                    if (
                        filter_var($this->activeTemplate->database_filter_on, FILTER_VALIDATE_BOOLEAN) &&
                        strlen($this->activeTemplate->database_filter_tables) > 0
                    ) {
                        $filterTables = SnapWP::getTablesWithPrefix(explode(',', $this->activeTemplate->database_filter_tables));
                    }

                    $filterSusites = $this->activeTemplate->filter_sites;
                    break;
            }

            $this->filteredData = [
                'dbonly'         => $dbOnly,
                'filterDirs'     => $filterDirs,
                'filterTables'   => $filterTables,
                'filterSubsites' => $filterSusites,
                'components'     => $components,
            ];
        }

        return $this->filteredData;
    }
}
