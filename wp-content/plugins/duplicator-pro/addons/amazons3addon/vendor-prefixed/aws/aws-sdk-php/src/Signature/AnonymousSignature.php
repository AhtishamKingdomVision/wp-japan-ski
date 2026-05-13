<?php

namespace VendorDuplicator\Aws\Signature;

use VendorDuplicator\Aws\Credentials\CredentialsInterface;
use VendorDuplicator\Psr\Http\Message\RequestInterface;
/**
 * Provides anonymous client access (does not sign requests).
 */
class AnonymousSignature implements SignatureInterface
{
    /**
     * /** {@inheritdoc}
     */
    public function signRequest(RequestInterface $request, CredentialsInterface $credentials)
    {
        return $request;
    }
    /**
     * /** {@inheritdoc}
     */
    public function presign(RequestInterface $request, CredentialsInterface $credentials, $expires, array $options = [])
    {
        return $request;
    }
}
