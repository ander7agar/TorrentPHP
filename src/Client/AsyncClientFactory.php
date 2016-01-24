<?php

namespace TorrentPHP\Client;

use Amp\ReactorFactory,
    Amp\Artax\AsyncClient;

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
        $reactor = new ReactorFactory;
        $reactor = $reactor->select();
        return array($reactor, new AsyncClient($reactor));
    }
} 