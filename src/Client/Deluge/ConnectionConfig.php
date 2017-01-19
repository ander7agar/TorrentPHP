<?php

namespace TorrentPHP\Client\Deluge;

/**
 * Class DelugeConnectionConfig
 *
 * @package TorrentPHP\Client\Deluge
 */
class ConnectionConfig
{
    /**
     * @var string Example 'https://localhost' or 'localhost'
     */
    private $host;

    /**
     * @var int Example 9091 or 20000
     */
    private $port;

    /**
     * @var string Authentication password
     */
    private $password;

    /**
     * @constructor
     *
     * Set the connection arguments - required are host, port and password
     *
     * @param array $arguments The arguments required for the user to make the rpc call to transmission
     *
     * @throws \InvalidArgumentException When the minimum required argument keys were not provided
     */
    public function __construct(array $arguments)
    {
        $required = array('host', 'port', 'password');

        if (count(array_intersect_key(array_flip($required), $arguments)) === count($required))
        {
            $host = strtolower($arguments['host']);

            if (strpos($host, 'http') === false) {
                $host = 'http://' . $host;
            }

            $this->host = $host;
            $this->port = $arguments['port'];
            $this->password = $arguments['password'];
        }
        else
        {
            throw new \InvalidArgumentException(sprintf(
                "Transmission connection args requires an array with the following keys: '%s', but '%s' given.",
                print_r($required, true), print_r(array_keys($arguments), true)
            ));
        }
    }

    /**
     * @return array The configuration args as keys and values
     */
    public function getArgs()
    {
        return array(
            'host' => $this->host,
            'port' => $this->port,
            'password' => $this->password
        );
    }
} 