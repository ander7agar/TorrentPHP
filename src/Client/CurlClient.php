<?php
/**
 * Created by PhpStorm.
 * User: ander
 * Date: 2/11/16
 * Time: 18:49
 */

namespace TorrentPHP\Client;


class CurlClient {

    /** @var string */
    private $url;

    /** @var string */
    private $method;

    /** @var array */
    private $headers = array();

    /** @var array */
    private $responseHeaders;

    /** @var array */
    private $params = array();

    /** @var boolean */
    private $return;

    /** @var boolean */
    private $encoding;

    /**
     * Curl constructor.
     * @param $url
     */
    public function __construct($url) {
        $this->url = $url;
    }

    /**
     * @param string $method
     * @return $this
     */
    public function setMethod($method) {
        $this->method = $method;
        return $this;
    }

    /**
     * @param array $headers
     * @return $this
     */
    public function setHeaders($headers) {
        if ($headers != null) {
            $this->headers = $headers;
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getResponseHeaders()
    {
        return $this->responseHeaders;
    }

    /**
     * @param mixed $value
     * @return $this
     */
    public function addHeader($value) {
        $this->headers[] = $value;
        return $this;
    }

    /**
     * @param array $parameters
     * @return $this
     */
    public function setParameters($parameters) {
        if ($parameters != null) {
            $this->params = $parameters;
        }

        return $this;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function addParameter($key, $value) {
        $this->params[$key] = $value;
        return $this;
    }

    /**
     * @param string $key
     * @return $this
     */
    public function removeParameter($key) {
        if (array_key_exists($key, $this->params)) {
            unset($this->params[$key]);
        }
        return $this;
    }

    /**
     * @param boolean $return
     * @return $this
     */
    public function setReturn($return) {
        $this->return = $return;
        return $this;
    }

    /**
     * @param boolean $encoding
     */
    public function setEncoding($encoding)
    {
        $this->encoding = $encoding;
    }

    /**
     * @return string
     */
    private function buildGetParams() {
        $b = http_build_query($this->params);
        return $b;
    }


    /**
     * @return mixed
     */
    public function execute() {
        // create curl resource
        $ch = curl_init();

        //return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, $this->return);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_ENCODING, $this->encoding);
        $method = strtoupper($this->method);

        switch($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                break;
            case 'GET':
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_PUT, true);
                break;
        }

        if ($method == 'GET') {
            $url = $this->url . $this->buildGetParams();
        } else {
            $url = $this->url;
        }

        // set url
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);

        $output = curl_exec($ch);

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->responseHeaders = $this->parseHeaders(substr($output, 0, $header_size));
        $body = substr($output, $header_size);
        curl_close($ch);
        return $body;
    }

    /**
     * @param $headers
     * @return array
     */
    private function parseHeaders($headers) {
        $firstHeaders = preg_split('/\R/', $headers);

        //die(print_r($firstHeaders, true));

        $headers = array();

        for ($x = 1; $x < count($firstHeaders) -2; $x++) {
            $pair = explode(':', $firstHeaders[$x]);
            $headers[$pair[0]] = $pair[1];
        }

        return $headers;
    }

}