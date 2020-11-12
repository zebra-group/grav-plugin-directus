<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Page\Page;
use Grav\Common\Plugin;
use Grav\Plugin\Directus\Directus;
use Grav\Plugin\Directus\Utility\DirectusUtility;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Class DirectusPlugin
 * @package Grav\Plugin
 */
class DirectusPlugin extends Plugin
{

    /**
     * @var DirectusUtility
     */
    protected $directusUtil;

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
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function onPageInitialized()
    {
        $this->directusUtil = new DirectusUtility(
            $this->config()['directus']['directusAPIUrl'],
            $this->config()['directus']['projectName'],
            $this->config()['directus']['email'],
            $this->config()['directus']['password'],
            $this->config()['directus']['token']
        );

        $this->processWebHooks($this->grav['uri']->route());

        $directusFile = $this->grav['page']->path() . '/data.json';
        if(!file_exists($directusFile)) {
            $this->crawlPage($this->grav['page']);
        }
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

        foreach($this->grav['pages']->instances() as $pageObject) {
            $this->crawlPage($pageObject);
        }
        return true;
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
    private function crawlPage(Page $page) {
        if(isset($page->header()->directus)) {
            $requestConfig = $page->header()->directus;
            $requestUrl = $this->directusUtil->generateRequestUrl(
                isset($requestConfig['collection']) ? $requestConfig['collection'] : '',
                isset($requestConfig['id']) ? $requestConfig['id'] : 0,
                isset($requestConfig['depth']) ? $requestConfig['depth'] : 2,
                isset($requestConfig['filter']) ? $requestConfig['filter'] : [],
                isset($requestConfig['limit']) ? $requestConfig['limit'] : -1
            );
            /** @var ResponseInterface $response */
            $response = $this->directusUtil->get($requestUrl);

            if($response->getStatusCode() === 200) {
                $this->writeFileToFileSystem($response->toArray(), $page->path());
            } else {
                $this->grav['debugger']->addMessage('something went from with directus request: ' . $response->getStatusCode());
            }
        }
        return true;
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
        }
        return true;
    }
}
