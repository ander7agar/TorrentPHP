<?php

namespace TorrentPHP\Client\Transmission;

use Amp\Artax\Client;
use Amp\Artax\ClientException as HTTPException;
use TorrentPHP\Client\AsyncClientFactory;
use TorrentPHP\ClientException;
use Amp\LibeventReactor;
use Amp\NativeReactor;
use Amp\Artax\Response;
use Amp\Artax\Request;

/**
 * Class AsyncClientTransport
 *
 * @package TorrentPHP\Client\Transmission
 */
class AsyncClientTransport extends ClientTransport
{
    /**
     * @var AsyncClientFactory
     */
    protected $clientFactory;

    /**
     * @constructor
     *
     * @param AsyncClientFactory $clientFactory Amp\Artax Async HTTP Client
     * @param Request            $request       Empty Amp\Artax Request Object
     * @param ConnectionConfig   $config        Configuration object used to connect over rpc
     */
    public function __construct(AsyncClientFactory $clientFactory, Request $request, ConnectionConfig $config)    {
        parent::__construct($clientFactory->build()[1], $request, $config);
        $this->clientFactory  = $clientFactory;
    }

    /**
     * @inheritdoc
     *
     * @param callable $callable A callable that will be given the async response
     */
    public function getTorrents(array $ids = array(), callable $callable = null)
    {
        $method = self::METHOD_GET;
        $arguments = (empty($ids)) ? array() : array('ids' => $ids);
        $callable = ($callable === null) ? function(){} : $callable;

        try
        {
            $this->performRPCRequest($method, $arguments, $callable);
        }
        catch(HTTPException $e)
        {
            throw new ClientException($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param callable $callable Pass in a callable that gets passed one argument which is the response body
     */
    protected function performRPCRequest($method, array $arguments, callable $callable)
    {
        $returnFields = array(
            'hashString', 'name', 'sizeWhenDone', 'status', 'rateDownload', 'rateUpload',
            'uploadedEver', 'files', 'errorString'
        );

        /** @var Client $client */
        /** @var LibEventReactor|NativeReactor $reactor */
        list ($reactor, $client) = $this->clientFactory->build();
        $request = clone $this->request;

        /** Callback for response data from client **/
        $onResponse = function(Response $response) use ($reactor, $method, $callable) {

            $isJson = function() use ($response) {
                json_decode($response->getBody());
                return (json_last_error() === JSON_ERROR_NONE);
            };

            if ($response->getStatus() !== 200 || !$isJson())
            {

                $reactor->stop();

                throw new HTTPException(sprintf(
                    'Did not get back a JSON response body, got "%s" instead',
                    $method, print_r($response->getBody(), true)
                ));
            }

            $reactor->stop();

            $callable($response->getBody());
        };

        /** Callback on error for either auth response or response **/
        $onError = function(\Exception $e) use($reactor) {
            $reactor->stop();

            throw new HTTPException("Something went wrong..." . $e->getMessage());
        };

        /** Callback when auth response is returned **/
        $onAuthResponse = function(Response $response, Request $request) use ($reactor, $client, $onResponse, $onError, $method, $returnFields, $arguments) {

            if (!$response->hasHeader('X-Transmission-Session-Id'))
            {
                $reactor->stop();

                throw new HTTPException("Response from torrent client did not return an X-Transmission-Session-Id header");
            }

            $sessionId = $response->getHeader('X-Transmission-Session-Id');

            $request = clone $request;
            $request->setMethod('POST');
            $request->setHeader('X-Transmission-Session-Id', $sessionId);
            $request->setBody(json_encode(array(
                'method'    => $method,
                'arguments' => array_merge(
                    array('fields' => $returnFields),
                    $arguments
                )
            )));

            try {
                $promise = $client->request($request);
                $response = \Amp\wait($promise);

                $onResponse($response);
            } catch (\Exception $e) {
                $onError($e);
            }
        };

        /** Let's do this **/
        $reactor->immediately(function() use ($reactor, $client, $request, $onAuthResponse, $onError) {

            $request->setUri(sprintf('%s:%s/transmission/rpc', $this->connectionArgs['host'], $this->connectionArgs['port']));
            $request->setMethod('GET');
            $request->setAllHeaders(array(
                'Content-Type'  => 'application/json; charset=utf-8',
                'Authorization' => sprintf('Basic %s', base64_encode(
                    sprintf('%s:%s', $this->connectionArgs['username'], $this->connectionArgs['password'])
                ))
            ));

            try {
                $promise = $client->request($request);
                $response = \Amp\wait($promise);

                $onAuthResponse($response, $request);
            } catch (\Exception $e) {
                $onError($e);
            }
        });

        $reactor->run();
    }
}