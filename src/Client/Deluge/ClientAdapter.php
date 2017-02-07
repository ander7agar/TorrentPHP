<?php

namespace TorrentPHP\Client\Deluge;

use TorrentPHP\File;
use TorrentPHP\Torrent;
use TorrentPHP\TorrentInfo;

/**
 * Class ClientAdapter
 *
 * @package TorrentPHP\Client\Deluge
 */
class ClientAdapter extends ClientTransport {

    /**
     * @param string $url
     * @return TorrentInfo
     */
    public function downloadTorrentFileUrl($url) {
        $data = $this->downloadTorrentFile($url);
        $torrentInfo = $this->getTorrentFileInfo($data->result);

        return TorrentInfo::build($torrentInfo->result);
    }

    /**
     * @param string $path
     * @return Torrent
     */
    public function addTorrentUrl($path) {
        $data = $this->addTorrent($path);

        $torrentHash = $data->result;

        return $this->getTorrentInfo($torrentHash);
    }


    /**
     * @param $id
     * @return Torrent
     */
    public function getTorrentInfo($id) {
        $response = $this->getTorrent($id);

        if ($response->result == null) {
            return null;
        }

        $array = $response->result;

        $torrent = Torrent::build($array['hash'], $array['name'], $array['total_wanted']);

        $torrent->setDownloadSpeed($array['download_payload_rate']);
        $torrent->setUploadSpeed($array['upload_payload_rate']);
        $torrent->setPath($array['save_path']);

        /** Deluge doesn't have a per-torrent error string **/
        $torrent->setErrorString((is_null($response->error) ? "" : print_r($response->error, true)));

        $torrent->setStatus($array['state']);
        $torrent->setEta($array['eta']);

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
     * @param array $ids
     * @return array
     */
    public function getTorrentsInfo(array $ids = array())
    {
        $response = $this->getTorrents(array($ids));
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
     * @param Torrent|null $torrent
     * @param null $torrentId
     * @return Torrent
     */
    public function startTorrentDownload(Torrent $torrent = null, $torrentId = null)
    {
        $this->startTorrent($torrent, $torrentId);

        $torrentHash = (!is_null($torrent)) ? $torrent->getHashString() : $torrentId;

        return $this->getTorrentInfo($torrentHash);
    }

    /**
     * @param Torrent|null $torrent
     * @param null $torrentId
     * @return Torrent
     */
    public function pauseTorrentDownload(Torrent $torrent = null, $torrentId = null)
    {
        $this->pauseTorrent($torrent, $torrentId);

        $torrentHash = (!is_null($torrent)) ? $torrent->getHashString() : $torrentId;

        return $this->getTorrentInfo($torrentHash);
    }

    /**
     * @param Torrent|null $torrent
     * @param null $torrentId
     * @return mixed
     */
    public function deleteTorrentFiles(Torrent $torrent = null, $torrentId = null)
    {
        $data = $this->deleteTorrent($torrent, $torrentId);

        return $data->result;
    }
}