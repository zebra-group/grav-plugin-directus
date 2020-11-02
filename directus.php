<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Plugin\Directus\Utility\DirectusUtility;

/**
 * Class DirectusPlugin
 * @package Grav\Plugin
 */
class DirectusPlugin extends Plugin
{

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
            'onPageInitialized' => ['onPageInitialized', 0]
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
        $page = $this->grav['page'];

        if(isset($page->header()->directusUrl))
        {
            $config = $this->config();
            $directusUtil = new DirectusUtility(
                $config['directus']['directusAPIUrl'],
                $config['directus']['projectName'],
                $config['directus']['email'],
                $config['directus']['password']
            );

            $result = $directusUtil->get($page->header()->directusUrl);

            if($result->getStatusCode() === 200) {
                $this->grav['twig']->twig_vars['directus_content'] = $result->toArray();
            } else {
                $this->grav['debugger']->addMessage('something went from with directus request: ' . $result->getStatusCode());
            }
        }
    }
}
