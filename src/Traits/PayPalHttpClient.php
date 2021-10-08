<?php

namespace Srmklive\PayPal\Traits;

use Exception;
use GuzzleHttp\Client as HttpClient;
use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Exception\ClientException as HttpClientException;
use RuntimeException;
use Throwable;

trait PayPalHttpClient
{
    /**
     * Set curl constants if not defined.
     *
     * @return void
     */
    protected function setCurlConstants()
    {
        if (!defined('CURLOPT_SSLVERSION')) {
            define('CURLOPT_SSLVERSION', 32);
        }

        if (!defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6);
        }

        if (!defined('CURLOPT_SSL_VERIFYPEER')) {
            define('CURLOPT_SSL_VERIFYPEER', 64);
        }

        if (!defined('CURLOPT_SSLCERT')) {
            define('CURLOPT_SSLCERT', 10025);
        }
    }

    /**
     * Function to initialize Http Client.
     *
     * @return void
     */
    protected function setClient()
    {
        $this->client = new HttpClient([
            'curl' => $this->httpClientConfig,
        ]);
    }

    /**
     * Function to set Http Client configuration.
     *
     * @return void
     */
    protected function setHttpClientConfiguration()
    {
        $this->setCurlConstants();

        $this->httpClientConfig = [
            CURLOPT_SSLVERSION     => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_SSL_VERIFYPEER => $this->validateSSL,
        ];

        if (!empty($this->certificate)) {
            $this->httpClientConfig[CURLOPT_SSLCERT] = $this->certificate;
        }

        // Initialize Http Client
        $this->setClient();

        // Set default values.
        $this->setDefaultValues();

        // Set PayPal API Endpoint.
        $this->apiUrl = $this->config['api_url'];

        // Set PayPal IPN Notification URL
        $this->notifyUrl = $this->config['notify_url'];
    }

    /**
     * Perform PayPal API request & return response.
     *
     * @throws Exception
     *
     * @return StreamInterface
     */
    private function makeHttpRequest()
    {
        try {
            $options = [
                $this->httpBodyParam => $this->post->toArray(),
            ];

            if ($this->fraudnetId) {
                $options['headers'] = [
                    'PAYPAL-CLIENT-METADATA-ID' => $this->fraudnetId,
                ];
            }

            return $this->client->post($this->apiUrl, $options)->getBody();
        } catch (HttpClientException $e) {
            if ($e->hasResponse()) {
                throw new RuntimeException($e->getMessage() . ' body ' . $e->getRequest()->getBody() . ' status ' . $e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getBody());
            }
            throw new RuntimeException($e->getMessage() . ' body ' . $e->getRequest()->getBody() . ' #' . $e->getCode());
        }
    }

    /**
     * Function To Perform PayPal API Request.
     *
     * @param string $method
     *
     * @throws Exception
     *
     * @return array|StreamInterface
     */
    private function doPayPalRequest($method)
    {
        // Setup PayPal API Request Payload
        $this->createRequestPayload($method);

        try {
            // Perform PayPal HTTP API request.
            $response = $this->makeHttpRequest();

            return $this->retrieveData($method, $response);
        } catch (Throwable $t) {

            return [
                'type'    => 'error',
                'message' => $t->getMessage(),
                'code' => $t->getCode(),
                'trace' => collect($t->getTrace())->implode('\n'),
            ];
        }
    }
}
