<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Core\Models;

use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;
use Duplicator\Libs\Snap\SnapWP;
use Exception;
use ReflectionClass;
use ReflectionObject;
use wpdb;

/**
 * Abstract Entity
 */
abstract class AbstractEntity extends AbstractGenericModel
{
    /** @var string generic indexed value */
    protected string $value1 = '';
    /** @var string generic indexed value */
    protected string $value2 = '';
    /** @var string generic indexed value */
    protected string $value3 = '';
    /** @var string generic indexed value */
    protected string $value4 = '';
    /** @var string generic indexed value */
    protected string $value5 = '';

    /**
     * Return entity type identifier
     *
     * @return string
     */
    abstract public static function getType(): string;

    /**
     * Set props by array key inpust data
     *
     * @param mixed[]   $data             input data
     * @param ?callable $sanitizeCallback sanitize values callback
     *
     * @return void
     */
    protected function setFromArrayKey($data, $sanitizeCallback = null): void
    {
        $reflect = new ReflectionClass($this);
        $props   = $reflect->getProperties();

        foreach ($props as $prop) {
            if (!isset($data[$prop->getName()])) {
                continue;
            }

            if (is_callable($sanitizeCallback)) {
                $value = call_user_func($sanitizeCallback, $prop->getName(), $data[$prop->getName()]);
            } else {
                $value = $data[$prop->getName()];
            }
            $prop->setValue($this, $value);
        }
    }

    /**
     * Initizalize entity from JSON
     *
     * @param string               $json           JSON string
     * @param array<string,scalar> $rowData        DB row data
     * @param ?string              $overwriteClass Overwrite class object, class must extend AbstractEntity
     *
     * @return static
     */
    protected static function getEntityFromJson(string $json, array $rowData, ?string $overwriteClass = null)
    {
        if ($overwriteClass === null) {
            $class = static::class;
        } else {
            if (is_subclass_of($overwriteClass, self::class) === false) {
                throw new Exception('Class ' . $overwriteClass . ' must extend ' . static::class);
            }
            $class = $overwriteClass;
        }

        /** @var static $obj */
        $obj     = JsonSerialize::unserializeToObj($json, $class);
        $reflect = new ReflectionObject($obj);

        $dbValuesToProps = [
            'id'         => 'id',
            'value_1'    => 'value1',
            'value_2'    => 'value2',
            'value_3'    => 'value3',
            'value_4'    => 'value4',
            'value_5'    => 'value5',
            'version'    => 'version',
            'created_at' => 'created',
            'updated_at' => 'updated',
        ];

        if (isset($rowData['id'])) {
            $rowData['id'] = (int) $rowData['id'];
        }

        foreach ($dbValuesToProps as $dbKey => $propName) {
            if (
                !isset($rowData[$dbKey]) ||
                !property_exists($obj, $propName)
            ) {
                continue;
            }

            $prop = $reflect->getProperty($propName);
            if (PHP_VERSION_ID < 80100) {
                $prop->setAccessible(true);
            }
            $prop->setValue($obj, $rowData[$dbKey]);
        }

        return $obj;
    }

    /**
     * Get insert data and formats, can be overridden by child classes
     *
     * @return array{data:array<string,mixed>,formats:string[]}
     */
    protected function getInsertData(): array
    {
        $data                 = parent::getInsertData();
        $data['data']['type'] = static::getType();
        $data['data']['data'] = ''; // First I create a row without an object to generate the id, and then I update the row create
        $data['formats'][]    = '%s';
        $data['formats'][]    = '%s';
        return $data;
    }

    /**
     * Get update data and formats, can be overridden by child classes
     *
     * @return array{data:array<string,mixed>,formats:string[]}
     */
    protected function getUpdateData(): array
    {
        $data                 = parent::getUpdateData();
        $data['data']['type'] = static::getType();
        $data['data']['data'] = JsonSerialize::serialize($this, JsonSerialize::JSON_SKIP_CLASS_NAME | JSON_PRETTY_PRINT);
        $data['value_1']      = $this->value1;
        $data['value_2']      = $this->value2;
        $data['value_3']      = $this->value3;
        $data['value_4']      = $this->value4;
        $data['value_5']      = $this->value5;
        $data['formats'][]    = '%s';
        $data['formats'][]    = '%s';
        $data['formats'][]    = '%s';
        $data['formats'][]    = '%s';
        $data['formats'][]    = '%s';
        $data['formats'][]    = '%s';
        $data['formats'][]    = '%s';
        return $data;
    }

    /**
     * Entity table name
     *
     * @param bool $escape If true apply esc_sql to table name
     *
     * @return string
     */
    final public static function getTableName(bool $escape = false): string
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $res = $wpdb->base_prefix . 'duplicator_entities';
        return ($escape ? esc_sql($res) : $res);
    }

    /**
     * Get where clause for get from database, can be overridden by child classes
     *
     * @return string
     */
    protected static function getWhereClause(): string
    {
        /** @var wpdb $wpdb */
        global $wpdb;

        return $wpdb->prepare('type = %s', static::getType());
    }

    /**
     * Get Model from database row
     *
     * @param array<string,mixed> $row Database row
     *
     * @return static
     */
    protected static function getModelFromRow(array $row)
    {
        return static::getEntityFromJson($row['data'], $row);
    }

    /**
     * Init entity table
     *
     * @return string[] Strings containing the results of the various update queries.
     */
    final public static function initTable(): array
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name      = static::getTableName(true);

        // PRIMARY KEY must have 2 spaces before for dbDelta to work
        // Mysql 5.5 can't have more than 1 DEFAULT CURRENT_TIMESTAMP
        $sql = <<<SQL
CREATE TABLE `{$table_name}` (
    `id` bigint(20) unsigned NOT null AUTO_INCREMENT,
    `type` varchar(100) NOT NULL,
    `value_1` varchar(255) NOT NULL DEFAULT '',
    `value_2` varchar(255) NOT NULL DEFAULT '',
    `value_3` varchar(255) NOT NULL DEFAULT '',
    `value_4` varchar(255) NOT NULL DEFAULT '',
    `value_5` varchar(255) NOT NULL DEFAULT '',
    `data` longtext NOT null,
    `version` varchar(30) NOT NULL DEFAULT '',
    `created_at` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    KEY `type_idx` (`type`),
    KEY `created_at` (`created_at`),
    KEY `updated_at` (`updated_at`),
    KEY `version` (`version`),
    KEY `value_1` (`value_1`(191)),
    KEY `value_2` (`value_2`(191)),
    KEY `value_3` (`value_3`(191)),
    KEY `value_4` (`value_4`(191)),
    KEY `value_5` (`value_5`(191))
) {$charset_collate};
SQL;

        return SnapWP::dbDelta($sql);
    }
}
