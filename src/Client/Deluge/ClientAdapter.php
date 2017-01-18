<?php

namespace TorrentPHP\Client\Deluge;

use TorrentPHP\ClientAdapter as BaseClientAdapter;
use TorrentPHP\Torrent;

/**
 * Class ClientAdapter
 *
 * @package TorrentPHP\Client\Deluge
 */
class ClientAdapter extends BaseClientAdapter
{
    /**
     * @see ClientTransport::addTorrent()
     */
    public function addTorrent($path)
    {
        $data = json_decode($this->transport->addTorrent($path));

        $torrentHash = $data->result;

        $torrents = $this->getTorrents($torrentHash);

        return $torrents[0];
    }

    /**
     * @see ClientTransport::getTorrents()
     */
    public function getTorrents(array $ids = array())
    {
        $response = $this->transport->getTorrents($ids);
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
            $torrent = $this->torrentFactory->build($array['hash'], $array['name'], $array['total_wanted']);

            $torrent->setDownloadSpeed($array['download_payload_rate']);
            $torrent->setUploadSpeed($array['upload_payload_rate']);

            /** Deluge doesn't have a per-torrent error string **/
            $torrent->setErrorString((is_null($data['error']) ? "" : print_r($data['error'], true)));

            $torrent->setStatus($array['state']);

            foreach ($array['files'] as $fileData)
            {
                $file = $this->fileFactory->build($fileData['path'], $fileData['size']);

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

        $torrents = $this->getTorrents($torrentHash);

        return $torrents[0];
    }

    /**
     * @see ClientTransport::pauseTorrent()
     */
    public function pauseTorrent(Torrent $torrent = null, $torrentId = null)
    {
        $this->transport->pauseTorrent($torrent, $torrentId);

        $torrentHash = (!is_null($torrent)) ? $torrent->getHashString() : $torrentId;

        $torrents = $this->getTorrents($torrentHash);

        return $torrents[0];
    }

    /**
     * @see ClientTransport::deleteTorrent()
     */
    public function deleteTorrent(Torrent $torrent = null, $torrentId = null)
    {
        $data = json_decode($this->transport->deleteTorrent($torrent, $torrentId));

        return $data->result;
    }
}