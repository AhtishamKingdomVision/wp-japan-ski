<?php

/**
 * Class that collects the functions of initial checks on the requirements to run the plugin
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Core\Addons;

abstract class AbstractAddonCore
{
    const ADDON_DATA_CONTEXT = 'duplicator_addon';

    /** @var static[] */
    private static $instances = [];
    /** @var array<string, mixed> */
    protected $addonData = [];

    /**
     *
     * @return static
     */
    public static function getInstance()
    {
        $class = static::class;
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }

        return self::$instances[$class];
    }

    /**
     * Class constructor
     */
    final protected function __construct()
    {
        $reflect         = new \ReflectionClass(static::class);
        $this->addonData = self::getInitAddonData($reflect->getShortName());
        $this->onConstruct();
    }

    /**
     * On Costruct function called on child class constructor
     * Use to exect function before init hookw, at this point wordpress is not fully loaded
     *
     * @return void
     */
    protected function onConstruct(): void
    {
    }

    /**
     * Init called on worpdres hook init if addon is enabled
     *
     * @return void
     */
    abstract public function init();

    /**
     * Return addon file
     *
     * @return string
     */
    abstract public static function getAddonFile();

    /**
     * Return addon folder
     *
     * @return string
     */
    abstract public static function getAddonPath();

    /**
     * Return addon url
     *
     * @return string
     */
    public static function getAddonUrl()
    {
        $path = 'addons/' . basename(static::getAddonPath());
        return plugins_url($path, DUPLICATOR____FILE);
    }

    /**
     *
     * @return string
     */
    public function getAddonInstallerPath()
    {
        return static::getAddonPath() . '/installer/' . strtolower($this->getSlug());
    }

    /**
     * Return addon slug
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->addonData['slug'];
    }

    /**
     * Check if current addon is available
     *
     * @return boolean
     */
    public function canEnable()
    {
        if (version_compare(PHP_VERSION, $this->addonData['requiresPHP'], '<')) {
            return false;
        }

        global $wp_version;
        if (version_compare($wp_version, $this->addonData['requiresWP'], '<')) {
            return false;
        }

        if (version_compare(DUPLICATOR_VERSION, $this->addonData['requiresDuplcator'], '<')) {
            return false;
        }

        return true;
    }

    /**
     * Check if addon has dependencies
     *
     * @return boolean
     */
    public function hasDependencies()
    {
        $avaliableAddons = AddonsManager::getInstance()->getAvailableAddons();
        return !array_diff($this->addonData['requiresAddons'], $avaliableAddons);
    }

    /**
     * Get addon info data
     *
     * @param string $class class short name
     *
     * @return array<string, mixed>
     */
    protected static function getInitAddonData($class)
    {
        $data          = get_file_data(static::getAddonFile(), self::getDefaltHeaders(), self::ADDON_DATA_CONTEXT);
        $getDefaultVal = self::getDefaultHeadersValues();

        foreach ($data as $key => $val) {
            if (strlen($val) === 0) {
                $data[$key] = $getDefaultVal[$key];
            }
        }

        if (!is_array($data['requiresAddons'])) {
            $data['requiresAddons'] = explode(',', $data['requiresAddons']);
        }
        $data['requiresAddons'] = array_map('trim', $data['requiresAddons']);

        $data['slug'] = $class;
        if (strlen($data['name']) === 0) {
            $data['name'] = $data['slug'];
        }
        return $data;
    }

    /**
     * Get defaults addon header value
     *
     * @return array<string, mixed>
     */
    protected static function getDefaultHeadersValues()
    {
        static $defaultHeaders = null;
        if (is_null($defaultHeaders)) {
            $defaultHeaders = [
                'name'              => '',
                'addonURI'          => '',
                'version'           => '0',
                'description'       => '',
                'author'            => '',
                'authorURI'         => '',
                'requiresWP'        => '5.3',
                'requiresPHP'       => '7.4',
                'requiresDuplcator' => '4.5.20',
                'requiresAddons'    => [],
            ];
        }
        return $defaultHeaders;
    }

    /**
     * Get addon headers
     *
     * @return string[]
     */
    protected static function getDefaltHeaders()
    {
        return [
            'name'              => 'Name',
            'addonURI'          => 'Addon URI',
            'version'           => 'Version',
            'description'       => 'Description',
            'author'            => 'Author',
            'authorURI'         => 'Author URI',
            'requiresWP'        => 'Requires WP min version',
            'requiresPHP'       => 'Requires PHP',
            'requiresDuplcator' => 'Requires Duplicator min version',
            'requiresAddons'    => 'Requires addons',
        ];
    }
}
