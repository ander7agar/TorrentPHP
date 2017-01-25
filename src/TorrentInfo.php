<?php

namespace TorrentPHP;

/**
 * Created by PhpStorm.
 * User: ander
 * Date: 25/01/17
 * Time: 4:07
 */
class TorrentInfo {

    /**
     * @var string
     */
    private $torrentHash;

    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $files;

    private function __construct() {
        $this->files = array();
    }

    /**
     * @return string
     */
    public function getTorrentHash()
    {
        return $this->torrentHash;
    }

    /**
     * @param string $torrentHash
     */
    private function setTorrentHash($torrentHash)
    {
        $this->torrentHash = $torrentHash;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    private function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param array $files
     */
    private function setFiles($files)
    {
        $this->files = $files;
    }

    public function addFiles(FileInfo $files) {
        $this->files[]  = $files;
    }

    /**
     * @param array $data
     * @return TorrentInfo
     */
    public static function build($data) {
        $name = $data['name'];
        $torrentHash = $data['info_hash'];

        $torrent = new TorrentInfo();
        $torrent->setName($name);
        $torrent->setTorrentHash($torrentHash);

        $files = FileInfo::build($data['files_tree']);

        $torrent->setFiles($files);

        return $torrent;

    }

}