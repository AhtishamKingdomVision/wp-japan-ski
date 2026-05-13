<?php

namespace VendorDuplicator\Spatie\Dropbox;

use VendorDuplicator\GuzzleHttp\Exception\ClientException;
interface RefreshableTokenProvider extends TokenProvider
{
    /**
     * @return bool Whether the token was refreshed.
     */
    public function refresh(ClientException $exception): bool;
}
