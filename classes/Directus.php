<?php

namespace Grav\Plugin\Directus;

use Grav\Common\Grav;

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
     * Directus constructor.
     * @param Grav $grav
     * @param array $config
     */
    public function __construct(Grav $grav, array $config) {
        $this->grav = $grav;
        $this->config = $config;

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
    }

    /**
     * @return mixed|null
     */
    public function get() {
        $result = $this->readFile();
        if($result) {
            return $result;
        }
        else {
            return null;
        }
    }
}