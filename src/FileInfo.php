<?php
/**
 * Created by PhpStorm.
 * User: ander
 * Date: 25/01/17
 * Time: 4:09
 */

namespace TorrentPHP;


class FileInfo
{

    /**
     * @var int
     */
    private $index;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $size;

    /**
     * @var string
     */
    private $type;

    /**
     * @var array
     */
    private $content;

    private function __construct() {
        $this->content = array();
    }

    /**
     * @return int
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @param int $index
     */
    private function setIndex($index)
    {
        $this->index = $index;
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
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param int $size
     */
    private function setSize($size)
    {
        $this->size = $size;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    private function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return array
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param array $content
     */
    private function setContent($content)
    {
        $this->content = $content;
    }

    public function addContent(FileInfo $file) {
        $this->content[] = $file;
    }

    public function isDirectory() {
        return strtolower($this->type) == 'dir';
    }

    /**
     * @param array $data
     * @return array
     */
    public static function build($data) {

        $contents = $data['contents'];

        $keys = array_keys($contents);
        $files = array();

        foreach ($keys as $k) {
            $f = FileInfo::buildByType($k, $contents[$k]);
            $files[] = $f;
        }

        return $files;

    }

    private static function buildByType($name, $data) {
        $type = 'file';

        if (array_key_exists('type', $data)) {
            $type = $data['type'];
        }

        if ($type == 'file') {
            return FileInfo::buildFile($name, $data);
        } else {
            return FileInfo::buildDirectory($name, $data);
        }
    }

    /**
     * @param $name
     * @param $data
     * @return FileInfo
     */
    private static function buildDirectory($name, $data) {
        $file = new FileInfo();

        $size = $data['length'];
        $type = $data['type'];

        $file->setName($name);
        $file->setSize($size);
        $file->setType($type);

        $contents = $data['contents'];

        $keys = array_keys($contents);

        foreach ($keys as $k) {
            $f = FileInfo::buildByType($k, $contents[$k]);
            $file->addContent($f);
        }

        return $file;
    }

    /**
     * @param $name
     * @param $data
     * @return FileInfo
     */
    private static function buildFile($name, $data) {
        $file = new FileInfo();

        $index = $data['index'];
        $size = $data['length'];
        $type = $data['type'];

        $file->setName($name);
        $file->setType($type);
        $file->setIndex($index);
        $file->setSize($size);

        return $file;
    }
}