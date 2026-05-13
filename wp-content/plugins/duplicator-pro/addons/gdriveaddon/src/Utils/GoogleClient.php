<?php

namespace Duplicator\Addons\GDriveAddon\Utils;

use VendorDuplicator\Google\Client;
use VendorDuplicator\GuzzleHttp\Client as GuzzleClient;

class GoogleClient extends Client
{
    /** @var array<string,mixed> */
    protected $customHttpOptions = [];

    /**
     * Set http client options
     *
     * @param array<string,mixed> $options options
     *
     * @return void
     */
    public function setHttpClientOptions(array $options): void
    {
        $this->customHttpOptions = $options;
    }

    /**
     * Create a new http client.
     *
     * @return GuzzleClient
     */
    protected function createDefaultHttpClient()
    {
        $options = [
            'base_uri'    => $this->getConfig('base_path'),
            'http_errors' => false,
        ];
        $options = array_merge($options, $this->customHttpOptions);

        return new GuzzleClient($options);
    }
}
