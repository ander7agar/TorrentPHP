<?php

namespace TorrentPHP\Client\Deluge;

use Amp\LibeventReactor;
use Amp\NativeReactor;
use TorrentPHP\ClientTransport as ClientTransportInterface;
use Amp\Artax\ClientException as HTTPException;
use TorrentPHP\ClientException;
use TorrentPHP\Torrent;
use Amp\Artax\Response;
use Amp\Artax\Request;
use Amp\Artax\Client;
use Amp;

/**
 * Class ClientTransport
 *
 * @package TorrentPHP\Client\Deluge
 *
 * @see <http://deluge-torrent.org/docs/1.2/modules/core/core.html>
 * @see <http://dev.deluge-torrent.org/ticket/2085#comment:4>
 */
class ClientTransport implements ClientTransportInterface
{
    /**
     * RPC Method to call for authentication
     */
    const METHOD_AUTH = 'auth.login';

    const GET_SESSION_STATE = 'core.get_session_state';

    /**
     * RPC Method to call to get torrent data for all torrents
     */
    const METHOD_GET_ALL = 'core.get_torrents_status';

    /**
     * Get all the data!
     */
    const METHOD_GET_WEB_UI = 'web.update_ui';

    /**
     * RPC Method to call to add a torrent from a url
     */
    const METHOD_ADD_URL = 'core.add_torrent_url';

    /**
     * RPC Method to call to add a torrent from a magnet url
     */
    const METHOD_ADD_MAGNET = 'core.add_torrent_magnet';

    const METHOD_ADD_FILE = 'core.add_torrent_file';

    /**
     * RPC Method to call to start a torrent
     */
    const METHOD_START = 'core.resume_torrent';

    /**
     * RPC Method to call to pause a torrent
     */
    const METHOD_PAUSE = 'core.pause_torrent';

    /**
     * RPC Method to call to delete a torrent and it's associated data
     */
    const METHOD_DELETE = 'core.remove_torrent';

    /**
     * RPC Method to set labels. Requires the Label plugin enabled.
     */
    const METHOD_SET_LABEL = 'label.set_torrent';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array Connection arguments
     */
    protected $connectionArgs;

    protected $sessionCookie;

    /**
     * @constructor
     *
     * @param Client                       $client  Amp\Artax HTTP Client
     * @param Request                      $request Empty Request object
     * @param ConnectionConfig             $config  Configuration object used to connect over rpc
     */
    public function __construct(Client $client, ConnectionConfig $config)
    {
        $this->connectionArgs = $config->getArgs();
        $this->client = $client;
    }

    public function getSessionState() {
        $method = self::GET_SESSION_STATE;

        $params = array();

        return $this->tryRPCRequest($method, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getTorrents(array $ids = array(), callable $callable = null)
    {
        $method = self::METHOD_GET_ALL;

        $params = array(
            /** Torrent ID if provided - null returns all torrents **/
            empty($ids) ? null : ['id' => $ids],
            /** Return Keys **/
            array(
                'name', 'state', 'files', 'eta', 'hash', 'download_payload_rate', 'status',
                'upload_payload_rate', 'total_wanted', 'total_uploaded', 'total_done', 'error_code', 'label'
            )
        );

        return $this->tryRPCRequest($method, $params, $callable);
    }

    public function getWebUI()
    {
        $method = self::METHOD_GET_WEB_UI;

        //[["queue","name","total_wanted","state","progress","num_seeds","total_seeds","num_peers","total_peers","download_payload_rate","upload_payload_rate","eta","ratio","distributed_copies","is_auto_managed","time_added","tracker_host","save_path","total_done","total_uploaded","max_download_speed","max_upload_speed","seeds_peers_ratio","label"],{}]

        $params = array(array("queue", "name", "total_wanted", "state", "progress", "num_seeds", "total_seeds", "num_peers",
            "total_peers", "download_payload_rate", "upload_payload_rate", "eta", "ratio", "distributed_copies",
            "is_auto_managed", "time_added", "tracker_host", "save_path", "total_done", "total_uploaded",
            "max_download_speed", "max_upload_speed", "seeds_peers_ratio", "label"), []);

        return $this->tryRPCRequest($method, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function addTorrent($path)
    {
        $method = self::METHOD_ADD_URL;
        $params = array(
            /** Torrent Url **/
            $path,
            /** Required array of optional arguments (required and also optional? wtf was the api designer thinking) **/
            array()
        );

        return $this->tryRPCRequest($method, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function addTorrentMagnet($url)
    {
        $method = self::METHOD_ADD_MAGNET;
        $params = array(
            /** Torrent Url **/
            $url,
            /** Required array of optional arguments (required and also optional? wtf was the api designer thinking) **/
            array("add_paused" => false)
        );

        return $this->tryRPCRequest($method, $params);
    }

    /**
     * core.add_torrent_file(filename, filedump, options)
     * RPC Exported Function (Auth Level: 5)
     *   Adds a torrent file to the session.
     *   Args:  filename (str): The filename of the torrent.
     *          filedump (str): A base64 encoded string of the torrent file contents.
     *          options (dict): The options to apply to the torrent upon adding.
     *   Returns: str: The torrent_id or None.
     */
    public function addTorrentFile($filePath)
    {
        $method = self::METHOD_ADD_MAGNET;
        $params = array(
            /** Torrent Url **/
            $filePath,
            /** Required array of optional arguments (required and also optional? wtf was the api designer thinking) **/
            array("add_paused" => false)
        );

        return $this->tryRPCRequest($method, $params);
    }

    public function setLabel($torrentId, $label) {

        $method = self::METHOD_SET_LABEL;

        $params = array($torrentId, $label);

        return $this->tryRPCRequest($method, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function startTorrent(Torrent $torrent = null, $torrentId = null)
    {
        $method = self::METHOD_START;

        if (!(is_null($torrent && is_null($torrentId))))
        {
            $params = array(
                /** The torrent to start **/
                array(!is_null($torrent) ? $torrent->getHashString() : $torrentId)
            );

            return $this->tryRPCRequest($method, $params);
        }
        else
        {
            throw new \InvalidArgumentException(sprintf(
                'Method: "%s" expected at least a Torrent object or torrent id parameter provided, none given', $method
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function pauseTorrent(Torrent $torrent = null, $torrentId = null)
    {
        $method = self::METHOD_PAUSE;

        if (!(is_null($torrent && is_null($torrentId))))
        {
            $params = array(
                /** The torrent to pause **/
                array(!is_null($torrent) ? $torrent->getHashString() : $torrentId)
            );

            return $this->tryRPCRequest($method, $params);
        }
        else
        {
            throw new \InvalidArgumentException(sprintf(
                'Method: "%s" expected at least a Torrent object or torrent id parameter provided, none given', $method
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTorrent(Torrent $torrent = null, $torrentId = null)
    {
        $method = self::METHOD_DELETE;

        if (!(is_null($torrent && is_null($torrentId))))
        {
            $params = array(
                /** The torrent to delete **/
                !is_null($torrent) ? $torrent->getHashString() : $torrentId,
                /** Boolean to remove all associated data **/
                true
            );

            return $this->tryRPCRequest($method, $params);
        }
        else
        {
            throw new \InvalidArgumentException(sprintf(
                'Method: "%s" expected at least a Torrent object or torrent id parameter provided, none given', $method
            ));
        }
    }

    /**
     * Just a wrapper for performRPCRequest that returns a ResponseBody ready to read.
     *
     * @param string $method The rpc method to call
     * @param array $params Associative array of rpc method arguments to send in the header (not auth arguments)
     * @param callable $callable
     * @return ResponseBody The decoded return data that came back from Deluge
     *
     * @throws ClientException
     */
    private function tryRPCRequest($method, $params, callable $callable = null) {
        try
        {
            return new ResponseBody($this->performRPCRequest($method, $params, $callable)->getBody());
        }
        catch(HTTPException $e)
        {
            throw new ClientException($e->getMessage());
        }
    }

    /**
     * Helper method to facilitate json rpc requests using the Amp\Artax client
     *
     * @param string $method The rpc method to call
     * @param array $arguments Associative array of rpc method arguments to send in the header (not auth arguments)
     *
     * @param callable $callable
     * @return Response When something goes wrong with the HTTP call
     *
     */
    protected function performRPCRequest($method, array $arguments, callable $callable = null)
    {
        /** @var Client $client */
        /** @var LibEventReactor|NativeReactor $reactor */
        $client = $this->client;
        $reactor = Amp\reactor();

        $request = new Request();

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

            if ($callable != null) {
                $callable($response->getBody());
            }

        };

        /** Callback on error for either auth response or response **/
        $onError = function(\Exception $e) use($reactor) {

            $reactor->stop();

            throw new HTTPException("Something went wrong..." . $e->getMessage());
        };

        /** Callback when auth response is returned **/
        $onAuthResponse = function(Response $response, Request $request) use ($reactor, $client, $onResponse, $onError, $method, $arguments) {

            /*            if (!$response->hasHeader('Set-Cookie'))
                        {
                            $reactor->stop();

                            throw new HTTPException("Response from torrent client did not return an Set-Cookie header");
                        }

                        $response = $response->getHeader('Set-Cookie');*/

            //preg_match_all('#_session_id=(.*?);#', $response[0], $matches);
            //$cookie = isset($matches[0][0]) ? $matches[0][0]: '';

            $request = clone $request;
            $request->setMethod('POST');
            //$request->setHeader('Cookie', array($cookie));
            $request->setBody(json_encode(array(
                'method' => $method,
                'params' => $arguments,
                'id'     => rand()
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

            $request->setUri(sprintf('%s:%s/json', $this->connectionArgs['host'], $this->connectionArgs['port']));
            $request->setMethod('POST');
            $request->setAllHeaders(array(
                'Content-Type'  => 'application/json; charset=utf-8'
            ));
            $request->setBody(json_encode(array(
                'method' => self::METHOD_AUTH,
                'params' => array(
                    $this->connectionArgs['password']
                ),
                'id'     => rand()
            )));

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