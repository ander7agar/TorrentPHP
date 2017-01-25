<?php

namespace TorrentPHP\Client\Deluge;

use TorrentPHP\ClientAdapter as BaseClientAdapter;
use TorrentPHP\File;
use TorrentPHP\Torrent;

/**
 * Class ClientAdapter
 *
 * @package TorrentPHP\Client\Deluge
 */
class ClientAdapter extends BaseClientAdapter
{

    /**
     * @param string $path
     * @return Torrent
     */
    public function addTorrent($path)
    {
        $data = $this->transport->addTorrent($path);

        $torrentHash = $data->result;

        return $this->getTorrent($torrentHash);
    }

    /**
     * @param $id
     * @return Torrent
     */
    public function getTorrent($id) {
        $response = $this->transport->getTorrent($id);
        $array = $response->result;

        $torrent = Torrent::build($array['hash'], $array['name'], $array['total_wanted']);

        $torrent->setDownloadSpeed($array['download_payload_rate']);
        $torrent->setUploadSpeed($array['upload_payload_rate']);

        /** Deluge doesn't have a per-torrent error string **/
        $torrent->setErrorString((is_null($response->error) ? "" : print_r($response->error, true)));

        $torrent->setStatus($array['state']);

        foreach ($array['files'] as $fileData)
        {
            $file = File::build($fileData['path'], $fileData['size']);

            $torrent->addFile($file);
        }

        $torrent->setBytesDownloaded($array['total_done']);
        $torrent->setBytesUploaded($array['total_uploaded']);

        return $torrent;

    }

    /**
     * @see ClientTransport::getTorrents()
     */
    public function getTorrents(array $ids = array())
    {
        $response = $this->transport->getTorrents(array($ids));
        if (is_object($response)) {
            $data = $this->object_to_array($response);
        } else {
            $data = json_decode($response, true);
        }


        $torrents = array();

        if (!empty($ids))
        {
            $changed = array('result' => array(), 'error' => $data['error']);
            $changed['result'][$ids[0]] = $data['result'];
            $data = $changed;
        }

        foreach ($data['result'] as $array)
        {
            $torrent = Torrent::build($array['hash'], $array['name'], $array['total_wanted']);

            $torrent->setDownloadSpeed($array['download_payload_rate']);
            $torrent->setUploadSpeed($array['upload_payload_rate']);

            /** Deluge doesn't have a per-torrent error string **/
            $torrent->setErrorString((is_null($data['error']) ? "" : print_r($data['error'], true)));

            $torrent->setStatus($array['state']);

            foreach ($array['files'] as $fileData)
            {
                $file = File::build($fileData['path'], $fileData['size']);

                $torrent->addFile($file);
            }

            $torrent->setBytesDownloaded($array['total_done']);
            $torrent->setBytesUploaded($array['total_uploaded']);

            $torrents[] = $torrent;
        }

        return $torrents;
    }

    /***
     * transform an object into a recursive array
     * @param $obj
     * @return array
     *
     */
    private function object_to_array($obj)
    {
        $arr = is_object($obj) ? get_object_vars($obj) : $obj;
        foreach ($arr as $key => $val) {
            $val = (is_array($val) || is_object($val)) ? $this->object_to_array($val) : $val;
            $arr[$key] = $val;
        }
        return $arr;
    }

    /**
     * @see ClientTransport::startTorrent()
     */
    public function startTorrent(Torrent $torrent = null, $torrentId = null)
    {
        $this->transport->startTorrent($torrent, $torrentId);

        $torrentHash = (!is_null($torrent)) ? $torrent->getHashString() : $torrentId;

        $torrents = $this->getTorrents(array($torrentHash));

        return $torrents[0];
    }

    /**
     * @see ClientTransport::pauseTorrent()
     */
    public function pauseTorrent(Torrent $torrent = null, $torrentId = null)
    {
        $this->transport->pauseTorrent($torrent, $torrentId);

        $torrentHash = (!is_null($torrent)) ? $torrent->getHashString() : $torrentId;

        $torrents = $this->getTorrents(array($torrentHash));

        return $torrents[0];
    }

    /**
     * @see ClientTransport::deleteTorrent()
     */
    public function deleteTorrent(Torrent $torrent = null, $torrentId = null)
    {
        $data = $this->transport->deleteTorrent($torrent, $torrentId);

        return $data->result;
    }
}