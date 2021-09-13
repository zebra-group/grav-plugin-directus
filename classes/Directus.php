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
            $this->grav,
            $this->config['directus']['email'],
            $this->config['directus']['password'],
            $this->config['directus']['token'],
            isset($this->config['disableCors']) && $this->config['disableCors']
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
                isset($requestConfig['limit']) ? $requestConfig['limit'] : 200,
                isset($requestConfig['sort']) ? $requestConfig['sort'] : ''
            );
            try {
                /** @var ResponseInterface $response */
                $response = $this->directusUtil->get($requestUrl);
                if(count($response->toArray()['data']) <= 0) {
                    $this->deleteFromFileSystem($page->path());
                } else {
                    $this->writeFileToFileSystem($response->toArray(), $page->path());
                }

                return true;
            } catch (\Exception $e) {
                $this->grav['debugger']->addException($e);
            }
        }
        return false;
    }

    /**
     * @param string $path
     * @param string $filename
     * @return bool
     */
    private function deleteFromFileSystem(string $path, string $filename = 'data.json') {
        return unlink ($path . '/' . $filename);
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
            $this->grav['debugger']->addException($e);
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
            $this->grav['debugger']->addException($e);
        }
        return false;
    }


    /**
     * @param Page $page
     * @param string $lang
     * @return mixed|null
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function get(Page $page, string $lang="") {
       // dd($lang);
        $directusFile = $page->path() . '/data.json';
        if(!file_exists($directusFile)) {
            $this->crawlPage($page);
        }

        $result = $this->readFile($page->path());

        if($result) {
            if($lang && isset($result['translations'])) {
                $result = $this->translate($result, $lang);
            }

            return $result;
        }
        $this->grav['debugger']->addMessage('Directus: no data.json');
        return null;
    }

    /**
     * @param array $object
     * @param string $lang
     * @return array
     */
    public function translate(array $object, string $lang) {
        foreach($object['translations'] as $translation) {
            if(is_array($translation['languages_code']) && ($lang === substr($translation['languages_code']['code'], 0, 2))) {
                foreach ($translation as $key => $value) {
                    if($key !== 'id' && $value)
                    {
                        $object[$key] = $value;
                    }
                }
            } elseif (is_string($translation['languages_code']) && ($lang === substr($translation['languages_code'], 0, 2))) {
                foreach ($translation as $key => $value) {
                    if($key !== 'id' && $value)
                    {
                        $object[$key] = $value;
                    }
                }
            }
        }
        return $object;
    }

    protected function log($data) {
        $fp = fopen('logs/directus.log', 'w');
        fwrite($fp, $data);
        fclose($fp);
    }
}