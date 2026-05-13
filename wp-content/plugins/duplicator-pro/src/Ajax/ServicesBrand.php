<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Ajax;

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Core\CapMng;
use Duplicator\Libs\Snap\SnapJson;
use Duplicator\Models\BrandEntity;
use Duplicator\Utils\Logging\ErrorHandler;
use Exception;

class ServicesBrand extends AbstractAjaxService
{
    /**
     * Init ajax calls
     *
     * @return void
     */
    public function init(): void
    {
        if (!License::can(License::CAPABILITY_BASE_ADVANCED)) {
            return;
        }

        $this->addAjaxCall('wp_ajax_duplicator_brand_delete', 'brandDelete');
    }

    /**
     * Hook ajax wp_ajax_duplicator_brand_delete
     *
     * @return never
     */
    public function brandDelete(): void
    {
        ErrorHandler::init();
        check_ajax_referer('duplicator_brand_delete', 'nonce');

        $json      = [
            'success' => false,
            'message' => '',
        ];
        $isValid   = true;
        $inputData = filter_input_array(INPUT_POST, [
            'brand_ids' => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => ['default' => false],
            ],
        ]);
        $brandIDs  = $inputData['brand_ids'];
        $delCount  = 0;

        if (empty($brandIDs) || in_array(false, $brandIDs)) {
            $isValid = false;
        }

        try {
            CapMng::can(CapMng::CAP_CREATE);
            if (!$isValid) {
                throw new Exception(__('Invalid Request.', 'duplicator-pro'));
            }

            foreach ($brandIDs as $id) {
                $brand = BrandEntity::deleteById($id);
                if ($brand) {
                    $delCount++;
                }
            }

            $json['success'] = true;
            $json['ids']     = $brandIDs;
            $json['removed'] = $delCount;
        } catch (Exception $e) {
            $json['message'] = $e->getMessage();
        }

        wp_send_json($json);
    }
}
