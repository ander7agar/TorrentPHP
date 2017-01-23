<?php

namespace TorrentPHP\Client\Deluge;

use Curl\Curl;
use Exception;
use TorrentPHP\Client\CurlClient;
use TorrentPHP\ClientTransport as ClientTransportInterface;
use TorrentPHP\ClientException;
use TorrentPHP\Torrent;

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
     * @var array Connection arguments
     */
    protected $connectionArgs;

    protected $sessionCookie;

    /**
     * @constructor
     *
     * @param ConnectionConfig             $config  Configuration object used to connect over rpc
     */
    public function __construct(ConnectionConfig $config)
    {
        $this->connectionArgs = $config->getArgs();
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
            return new ResponseBody($this->performRPCRequest($method, $params, $callable));
        }
        catch(Exception $e)
        {
            throw new ClientException($e->getMessage(), null, $e);
        }
    }

    /**
     * @param $method
     * @param array $arguments
     * @param callable|null $callable
     */
    protected function performRPCRequest($method, array $arguments, callable $callable = null)
    {

        /**
         * @param $response
         * @return array
         */
        $onResponse = function($response) use ($callable) {

            $response = json_decode($response);

            if ($callable != null) {
                $callable($response);
            }

            return $response;
        };

        /** Callback on error for either auth response or response **/
        $onError = function(\Exception $e) {

            throw new ClientException($e->getMessage(), null, $e);
        };

        /**
         * @param $cookie
         * @return array
         */
        $onAuthResponse = function($cookie) use ($onResponse, $onError, $method, $arguments) {

            $client = $this->getClient();
            $client->setCookie('Cookie:', $cookie);

            try {
                $url = sprintf('%s:%s/json', $this->connectionArgs['host'], $this->connectionArgs['port']);
                $client->post($url, array(
                    'method' => $method,
                    'params' => $arguments,
                    'id' => rand()
                ));

                $response = $client->response;

                return $onResponse($response);
            } catch (\Exception $e) {
                $onError($e);
            }
        };

        $client = $this->getClient();

        $client->setCookie('Cookie', 'remember_select_exclude=[]; remember_select_notify=[]');

        try {
            $url = sprintf('%s:%s/json', $this->connectionArgs['host'], $this->connectionArgs['port']);
            $client->post($url, array(
                'method' => 'auth.login',
                'params' => array($this->connectionArgs['password']),
                'id' => rand()
            ));

            $headers = $client->response_headers;


            if (!array_key_exists('Set-Cookie', $headers)) {
                throw new ClientException('Set-Cookie Header no present');
            }

            return $onAuthResponse($headers['Set-Cookie']);
        } catch (\Exception $e) {
            $onError($e);
        }
    }

    /**
     * @return Curl
     */
    private function getClient() {
        $curl = new Curl();
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setOpt(CURLOPT_RETURNTRANSFER, true);
        return $curl;
    }
}