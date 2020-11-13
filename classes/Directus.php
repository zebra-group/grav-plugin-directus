<?php

namespace Grav\Plugin\Directus;

use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Plugin\Directus\Utility\DirectusUtility;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Class Directus
 * @package Grav\Plugin\Directus
 */
class Directus {

    /**
     * @var Grav
     */
    protected $grav;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var DirectusUtility
     */
    protected $directusUtil;

    /**
     * Directus constructor.
     * @param Grav $grav
     * @param array $config
     */
    public function __construct(Grav $grav, array $config) {
        $this->grav = $grav;
        $this->config = $config;
        $this->directusUtil = new DirectusUtility(
            $this->config['directus']['directusAPIUrl'],
            $this->config['directus']['projectName'],
            $this->config['directus']['email'],
            $this->config['directus']['password'],
            $this->config['directus']['token']
        );
    }

    /**
     * @param Page $page
     * @return bool
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function crawlPage(Page $page) {
        if(isset($page->header()->directus)) {
            $requestConfig = $page->header()->directus;
            $requestUrl = $this->directusUtil->generateRequestUrl(
                isset($requestConfig['collection']) ? $requestConfig['collection'] : '',
                isset($requestConfig['id']) ? $requestConfig['id'] : 0,
                isset($requestConfig['depth']) ? $requestConfig['depth'] : 2,
                isset($requestConfig['filter']) ? $requestConfig['filter'] : [],
                isset($requestConfig['limit']) ? $requestConfig['limit'] : -1,
                isset($requestConfig['sort']) ? $requestConfig['sort'] : ''
            );
            try {
                /** @var ResponseInterface $response */
                $response = $this->directusUtil->get($requestUrl);
                $this->writeFileToFileSystem($response->toArray(), $page->path());
                return true;
            } catch (\Exception $e) {
                $this->grav['debugger']->addMessage('something went from with directus request: ' . $e);
            }
        }
        return false;
    }

    /**
     * @param array $data
     * @param string $path
     * @param string $filename
     */
    private function writeFileToFileSystem(array $data, string $path, string $filename = 'data.json') {
        try {
            $fp = fopen($path . '/' . $filename, 'w');
            fwrite($fp, json_encode($data));
            fclose($fp);
        } catch (\Exception $e) {
            $this->grav['debugger']->addMessage('cant write to filesystem: ' . $e);
        }
    }

    /**
     * @param string $path
     * @param string $filename
     * @return mixed
     */
    private function readFile(string $path = '', string $filename = 'data.json') {
        try {
            if ( ! $path ) {
                $path = Grav::instance()['page']->path();
            }
            $file = $path . '/' . $filename;
            if ( file_exists( $file ) )
            {
                $contents = file_get_contents( $path . '/data.json' );
                $data = json_decode( $contents, true );
                if ( count($data['data']) > 1 )
                {
                    return $data['data'];
                }
                else
                {
                    return $data['data'][0];
                }
            }
        } catch (\Exception $e) {
            $this->grav['debugger']->addMessage('cant read from filesystem: ' . $e);
        }
        return false;
    }


    /**
     * @param Page $page
     * @return mixed|null
     */
    public function get(Page $page) {

        $directusFile = $page->path() . '/data.json';
        if(!file_exists($directusFile)) {
            $this->crawlPage($page);
        }

        $result = $this->readFile($page->path());

        if($result) {
            return $result;
        }
        else {
            return null;
        }
    }
}