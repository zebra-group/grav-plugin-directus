<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Plugin\Directus\Directus;

/**
 * Class DirectusPlugin
 * @package Grav\Plugin
 */
class DirectusPlugin extends Plugin
{

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => [
                ['autoload', 100000], // TODO: Remove when plugin requires Grav >=1.7
                ['onPluginsInitialized', 0]
            ]
        ];
    }

    /**
    * Composer autoload.
    *is
    * @return ClassLoader
    */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Enable the main events we are interested in
        $this->enable([
            'onPageInitialized' => ['onPageInitialized', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0]
        ]);
    }

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function onPageInitialized()
    {

        $this->processWebHooks($this->grav['uri']->route());
    }

    /**
     * onTwigSiteVariables
     */
    public function onTwigSiteVariables()
    {
        $this->grav['twig']->twig_vars['directus'] = new Directus($this->grav, $this->config());
    }

    /**
     * @return bool
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function refreshGlobalDataFiles() {
        $directus = new Directus($this->grav, $this->config());

        foreach($this->grav['pages']->instances() as $pageObject) {
            $directus->crawlPage($pageObject);
        }
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Global import completed'
        ]);
        exit;
    }

    /**
     * @param string $route
     * @return bool
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function processWebHooks(string $route) {
        switch ($route) {
            case '/' . $this->config()['directus']['hookPrefix'] . '/refresh-global':
                $this->refreshGlobalDataFiles();
                break;
            case '/' . $this->config()['directus']['hookPrefix'] . '/refresh-single':
                try {
                    $this->refreshSingleDataFiles();
                } catch (\Exception $e) {
                    $this->log($e);
                }

                break;
        }
        return true;
    }

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function refreshSingleDataFiles() {
        $directus = new Directus($this->grav, $this->config());
        if( isset($_REQUEST['update']) || isset($_REQUEST['new']) || isset($_REQUEST['delete']))
        {
            $requestConfig = json_decode($_REQUEST[key($_REQUEST)], true);
            foreach ($this->grav['pages']->instances() as $pageObject) {
                if(isset($pageObject->header()->directus)) {
                    $directusConfig = $pageObject->header()->directus;
                    if($directusConfig['collection'] === $requestConfig['table']) {
                        if (isset($directusConfig['id']))
                        {
                            if ((int)$directusConfig['id'] === (int)$requestConfig['id']) {
                                $directus->crawlPage($pageObject);
                            } else {
                                continue;
                            }
                        } elseif (!isset($directusConfig['id']) && !isset($directusConfig['filter'])) {
                            $directus->crawlPage($pageObject);
                        } elseif (isset($directusConfig['filter'])) {
                            $directus->crawlPage($pageObject);
                        } else {
                            continue;
                        }
                    }

                }
            }
        }
        exit;
    }

    protected function log($data) {
        $fp = fopen('logs/directus.log', 'w');
        fwrite($fp, $data);
        fclose($fp);
    }
}
