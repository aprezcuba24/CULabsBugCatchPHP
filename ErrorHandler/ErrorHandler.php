<?php

/**
 * @author: Renier Ricardo Figueredo
 * @mail: aprezcuba24@gmail.com
 */
namespace CULabs\BugCatch\ErrorHandler;

use CULabs\BugCatch\Client\Client;

class ErrorHandler
{
    protected $client;
    protected $post;
    protected $get;
    protected $cookie;
    protected $filesPost;
    protected $server;
    protected $userData = array();
    protected $activate;
    protected $objectsProcessed = array();

    public function __construct(Client $client, $activate = true)
    {
        $this->client   = $client;
        $this->activate = $activate;
    }

    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public function notifyException(\Exception $exception)
    {
        $this->createFromGlobals();
        $exception = FlattenException::create($exception);
        $this->sendRequest(array(
            'method'    => isset($this->server['REQUEST_METHOD'])? $this->server['REQUEST_METHOD']: '',
            'host'      => isset($this->server['HTTP_HOST'])? $this->server['HTTP_HOST']: '',
            'uri'       => isset($this->server['REQUEST_URI'])? $this->server['REQUEST_URI']: '',
            'scheme'    => isset($this->server['REQUEST_SCHEME'])? $this->server['REQUEST_SCHEME']: '',
            'userData'  => json_encode($this->userData),
            'post'      => json_encode($this->post),
            'get'       => json_encode($this->get),
            'cookie'    => json_encode($this->cookie),
            'filesPost' => json_encode($this->filesPost),
            'server'    => json_encode($this->server),
            'errors'    => $exception->toArray(),
        ));
    }

    public function notifyCommandException(\Exception $exception)
    {
        $this->createFromGlobals();
        $exception = FlattenException::create($exception);
        $uri = '';
        foreach ($this->server['argv'] as $key => $item) {
            if ($key == 0) {
                continue;
            }
            $uri .= ' '.$item;
        }
        $this->sendRequest(array(
            'host'   => isset($this->server['argv'][0])? $this->server['argv'][0]: '',
            'uri'    => $uri,
            'errors' => $exception->toArray(),
        ));
    }

    protected function sendRequest($data)
    {
        if (!$this->activate) {
            return;
        }
        try {
            $this->client->send($data);
        } catch (\Exception $e) {
        }
    }

    protected function createFromGlobals()
    {
        if (!$this->post) {
            $this->post = $_POST;
        }
        if (!$this->get) {
            $this->get = $_GET;
        }
        if (!$this->cookie) {
            $this->cookie = $_COOKIE;
        }
        if (!$this->filesPost) {
            $this->filesPost = $_FILES;
        }
        if (!$this->server) {
            $this->server = $_SERVER;
        }
    }

    /**
     * @return mixed
     */
    public function getUserData()
    {
        return $this->userData;
    }

    /**
     * @param mixed $userData
     */
    public function setUserData($userData)
    {
        $this->userData = $userData;
    }

    /**
     * @return mixed
     */
    public function getPost()
    {
        return $this->post;
    }

    /**
     * @param mixed $post
     */
    public function setPost($post)
    {
        $this->post = $post;
    }

    /**
     * @return mixed
     */
    public function getGet()
    {
        return $this->get;
    }

    /**
     * @param mixed $get
     */
    public function setGet($get)
    {
        $this->get = $get;
    }

    /**
     * @return mixed
     */
    public function getCookie()
    {
        return $this->cookie;
    }

    /**
     * @param mixed $cookie
     */
    public function setCookie($cookie)
    {
        $this->cookie = $cookie;
    }

    /**
     * @return mixed
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param mixed $files
     */
    public function setFiles($files)
    {
        $this->filesPost = $files;
    }

    /**
     * @return mixed
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * @param mixed $server
     */
    public function setServer($server)
    {
        $this->server = $server;
    }

    public function processObject($object, $deep = 3)
    {
        if (!is_object($object)) {
            return $object;
        }
        $this->objectsProcessed = array(spl_object_hash($object));

        return $this->doProcessObject($object, $deep);
    }

    private function doProcessObject($object, $deep)
    {
        if (!is_object($object)) {
            return $object;
        }
        $result = array();
        $reflection = new \ReflectionObject($object);
        foreach ($reflection->getMethods() as $method) {
            if ($method->getNumberOfRequiredParameters()) {
                continue;
            }
            try {
                $value = $method->invoke($object);
                if ($value instanceof \DateTime) {
                    $value = $value->format('Y-m-d H:i:s');
                } elseif (is_object($value) && $deep - 1 > 0 ) {
                    if (in_array(spl_object_hash($value), $this->objectsProcessed)) {
                        continue;
                    }
                    $this->objectsProcessed[] = spl_object_hash($value);
                    $value = $this->doProcessObject($value, $deep - 1);
                }
                $result[$method->getName()] = $value;
            } catch (\Exception $e) {}
        }

        return $result;
    }
}