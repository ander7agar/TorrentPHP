<?php

namespace TorrentPHP\Client;

use Amp\Reactor;

/**
 * Class AsyncClientFactory
 *
 * Responsible for building the Async Client and Reactor for use within the AsyncClientTransport
 *
 * @package TorrentPHP\Client
 */
class AsyncClientFactory
{
    /**
     * @return array An array in the form of [Amp\Reactor, Amp\Artax\AsyncClient]
     */
    public function build()
    {
        $reactor = reactor();
        return array($reactor, new Client($reactor));
    }
} 