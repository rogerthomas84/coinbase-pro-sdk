<?php

/**
 * @author Marc MOREAU <moreau.marc.web@gmail.com>
 * @license https://github.com/MockingMagician/coinbase-pro-sdk/blob/master/LICENSE.md MIT
 * @link https://github.com/MockingMagician/coinbase-pro-sdk/blob/master/README.md
 */

namespace MockingMagician\CoinbaseProSdk\Functional;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use MockingMagician\CoinbaseProSdk\Contracts\ApiParamsInterface;
use MockingMagician\CoinbaseProSdk\Contracts\Build\PaginationInterface;
use MockingMagician\CoinbaseProSdk\Contracts\Connectivity\TimeInterface;
use MockingMagician\CoinbaseProSdk\Contracts\RequestInterface;
use MockingMagician\CoinbaseProSdk\Functional\Build\Pagination;
use MockingMagician\CoinbaseProSdk\Functional\Error\ApiError;
use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Throwable;

class Request implements RequestInterface
{
    /**
     * @var ClientInterface
     */
    private $client;
    /**
     * @var string
     */
    private $method;
    /**
     * @var string
     */
    private $routePath;
    /**
     * @var string
     */
    private $body;
    /**
     * @var ApiParamsInterface
     */
    private $apiParams;
    /**
     * @var null|TimeInterface
     */
    private $time;
    /**
     * @var null|Pagination
     */
    private $pagination;
    /**
     * @var array
     */
    private $queryArgs;

    public function __construct(
        ClientInterface $client,
        ApiParamsInterface $apiParams,
        string $method,
        string $routePath,
        array $queryArgs = [],
        ?string $body = null,
        ?PaginationInterface $pagination = null,
        ?TimeInterface $time = null
    ) {
        $this->client = $client;
        $this->method = $method;
        $this->routePath = $routePath;
        $this->body = $body;
        $this->apiParams = $apiParams;
        $this->time = $time;
        $this->pagination = $pagination;
        $this->queryArgs = $queryArgs;
    }

    public function signAndSend()
    {
        $time = $this->getTime();
        $what = $time.$this->method.$this->getFullRoutePath().($this->body ?? '');
        $key = base64_decode($this->apiParams->getSecret());
        $hmac = hash_hmac('sha256', $what, $key, true);
        $sign = base64_encode($hmac);

        $request = new GuzzleRequest(
            $this->method,
            $this->getUri(),
            [
                'CB-ACCESS-KEY' => $this->apiParams->getKey(),
                'CB-ACCESS-SIGN' => $sign,
                'CB-ACCESS-TIMESTAMP' => $time,
                'CB-ACCESS-PASSPHRASE' => $this->apiParams->getPassphrase(),
                'Content-Type' => 'application/json',
            ],
            $this->body
        );

        return $this->send($request);
    }

    public function send(?PsrRequestInterface $request = null)
    {
        if (!$request) {
            $request = new GuzzleRequest(
                $this->method,
                $this->getUri(),
                [
                    'Content-Type' => 'application/json',
                ],
                $this->body
            );
        }

        try {
            $response = $this->client->send($request);
        } catch (BadResponseException $exception) {
            $message = $exception->getMessage();
            if ($exception->hasResponse()
                && ($array = json_decode($exception->getResponse()->getBody()->getContents(), true))
                && isset($array['message'])
            ) {
                $message = $array['message'];
            }

            throw new ApiError($message);
        } catch (Throwable $exception) {
            throw new ApiError($exception->getMessage());
        }

        // TODO implement auto pagination based directly on direction and headers
        dump([
            'header after' => $response->getHeader(Pagination::HEADER_AFTER),
            'header before' => $response->getHeader(Pagination::HEADER_BEFORE)
        ]);

        if ($this->pagination) {
            if ($this->pagination->getDirection() === Pagination::BEFORE
                && !empty($response->getHeader(Pagination::HEADER_BEFORE))
            ) {
                $this->pagination->setOffset($response->getHeader(Pagination::HEADER_BEFORE)[0]);
            } else if (
                !empty($response->getHeader(Pagination::HEADER_AFTER))
            ) {
                $this->pagination->setOffset($response->getHeader(Pagination::HEADER_AFTER)[0]);
            } else {
                $this->pagination->setHasNext(false);
            }
        }

        return $response->getBody()->getContents();
    }

    private function getTime()
    {
        if ($this->time) {
            try {
                return $this->time->getTime()->getEpoch();
            } catch (Throwable $exception) {
            }
        }

        return time();
    }

    private function getQueryString(): string
    {
        if ($this->pagination) {
            return http_build_query(array_merge($this->queryArgs, $this->pagination->getQueryArgs()));
        }

        return http_build_query($this->queryArgs);
    }

    private function getFullRoutePath()
    {
        if (!empty($query = $this->getQueryString())) {
            return $this->routePath.'?'.$query;
        }

        return $this->routePath;
    }

    private function getUri()
    {
        return $this->apiParams->getEndPoint().$this->getFullRoutePath();
    }
}
