<?php

namespace Duplicator\Addons\DropboxAddon\Utils;

use Duplicator\Models\GlobalEntity;
use Duplicator\Utils\Logging\DupLog;
use VendorDuplicator\GrahamCampbell\GuzzleFactory\GuzzleFactory;
use VendorDuplicator\GuzzleHttp\Client as GuzzleClient;
use VendorDuplicator\GuzzleHttp\Psr7\Response;
use VendorDuplicator\Spatie\Dropbox\Client;

class DropboxClient extends Client
{
    const OAUTH2_URL = 'https://www.dropbox.com/oauth2/';

    /**
     * Class contructor
     *
     * @param string            $accessToken           The access token
     * @param GuzzleClient|null $client                The HTTP client
     * @param int               $maxChunkSize          The maximum size of a chunk
     * @param int               $maxUploadChunkRetries The maximum number of times to retry a chunk upload
     * @param bool              $sslVerify             If true, use SSL
     * @param string            $sslCert               If empty use server cert
     * @param bool              $ipv4Only              If true, use IPv4 only
     */
    public function __construct(
        $accessToken,
        ?GuzzleClient $client = null,
        int $maxChunkSize = self::MAX_CHUNK_SIZE,
        int $maxUploadChunkRetries = 0,
        $sslVerify = true,
        $sslCert = '',
        $ipv4Only = false
    ) {
        if (!$client) {
            $options = [];
            if ($sslVerify === false) {
                $verify = false;
            } elseif (strlen($sslCert) === 0) {
                $verify = true;
            } else {
                $verify = $sslCert;
            }
            $options['verify'] = $verify;
            if ($ipv4Only) {
                $options['force_ip_resolve'] = 'v4';
            }
            $options['handler'] = GuzzleFactory::handler();
            $client             = new GuzzleClient($options);
        }

        parent::__construct($accessToken, $client, $maxChunkSize, $maxUploadChunkRetries);
    }

    /**
     * Use the app config to authenticate and get the access token
     *
     * @param string                                     $auth_code  The authorization code
     * @param array{app_key: string, app_secret: string} $app_config The app config
     *
     * @return bool
     */
    public function authenticate($auth_code, $app_config)
    {
        $url  = self::OAUTH2_URL . 'token';
        $args = $this->injectExtraReqArgs([
            'timeout' => 30,
            'body'    => [
                'client_id'     => $app_config['app_key'],
                'client_secret' => $app_config['app_secret'],
                'code'          => $auth_code,
                'grant_type'    => 'authorization_code',
            ],
        ]);

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            DupLog::traceObject("Something wrong with while trying to get v2_access_token with code", $response);
            return false;
        }

        DupLog::traceObject("Got v2 access_token", $response);
        $ret_obj = json_decode($response['body']);

        return $ret_obj->access_token ?? false;
    }

    /**
     * Get the account's usage quota information.
     *
     * @return array{used: int, allocation: array{allocated: int}}|false
     */
    public function getQuota()
    {
        try {
            return $this->rpcEndpointRequest('users/get_space_usage');
        } catch (\Exception $e) {
            DupLog::trace('[DropboxClient] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Set the timeout for the client
     *
     * @param int $timeout The timeout in seconds
     *
     * @return void
     */
    public function setTimeout($timeout): void
    {
        if ($timeout > 0) {
            $this->client = new GuzzleClient(['handler' => GuzzleFactory::handler(), 'timeout' => $timeout]);
        } else {
            $this->client = new GuzzleClient(['handler' => GuzzleFactory::handler()]);
        }
    }

    /**
     * Download a file from a user's Dropbox.
     *
     * @param string $path   The path to download
     * @param int    $start  The byte to start from
     * @param int    $length The number of bytes to download
     *
     * @return string
     *
     * @link https://www.dropbox.com/developers/documentation/http/documentation#files-download
     */
    public function downloadPartial(string $path, int $start = 0, int $length = 1024 * 1024)
    {
        $arguments = ['path' => $this->normalizePath($path)];
        $headers   = ['Range' => "bytes=$start-" . ($start + $length - 1)];
        /** @var Response $response */
        $response = $this->contentEndpointRequestWithHeaders('files/download', $arguments, '', $headers);
        return $response->getBody()->getContents();
    }

    /**
     * Inject extra request arguments
     *
     * @param array<string, mixed> $opts The request options
     *
     * @return array<string, mixed>
     */
    private function injectExtraReqArgs(array $opts): array
    {
        $global            = GlobalEntity::getInstance();
        $opts['sslverify'] = !$global->ssl_disableverify;
        if (!$global->ssl_useservercerts) {
            $opts['sslcertificates'] = DUPLICATOR_CERT_PATH;
        }
        return $opts;
    }

    /**
     * Content endpoint request with custom headers
     *
     * @param string                $endpoint  The endpoint to send the request to
     * @param array<string, mixed>  $arguments The params to send.
     * @param string                $body      The body of the request
     * @param array<string, string> $headers   Custom headers to add.
     *
     * @return \VendorDuplicator\Psr\Http\Message\ResponseInterface
     *
     * @throws \Exception
     */
    public function contentEndpointRequestWithHeaders(string $endpoint, array $arguments, string $body = '', array $headers = [])
    {
        $headers['Dropbox-API-Arg'] = \json_encode($arguments);
        if ($body !== '') {
            $headers['Content-Type'] = 'application/octet-stream';
        }
        return $this->client->post($this->getEndpointUrl('content', $endpoint), ['headers' => $this->getHeaders($headers), 'body' => $body]);
    }
}
