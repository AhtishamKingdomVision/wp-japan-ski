<?php

/**
 * Singlethon class that manages rest endpoints
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Core\REST;

final class RESTManager
{
    /**
     *
     * @var ?self
     */
    private static $instance;

    /**
     *
     * @return self
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Class constructor
     */
    protected function __construct()
    {
        add_action('rest_api_init', [$this, 'register']);
    }

    /**
     * get rest points list
     *
     * @return AbstractRESTPoint[]
     */
    private function getRestPoints(): array
    {
        $basicRestPoints   = [];
        $basicRestPoints[] = new \Duplicator\RESTPoints\Versions();
        $basicRestPoints[] = new \Duplicator\RESTPoints\SubsiteActions();

        return array_filter(
            apply_filters(
                'duplicator_endpoints',
                $basicRestPoints
            ),
            fn($restPoint): bool => is_subclass_of($restPoint, AbstractRESTPoint::class)
        );
    }

    /**
     * Register rest points
     *
     * @return void
     */
    public function register(): void
    {
        foreach ($this->getRestPoints() as $obj) {
            $obj->register();
        }
    }
}
