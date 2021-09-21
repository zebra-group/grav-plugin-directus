<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Cache;
use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Flex\FlexObject;
use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexDirectoryInterface;
use Grav\Plugin\Directus\Directus;
use Grav\Plugin\Directus\Utility\DirectusUtility;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class DirectusPlugin
 * @package Grav\Plugin
 */
class DirectusPlugin extends Plugin
{

    /**
     * @var Flex
     */
    protected $flex;

    /**
     * @var FlexCollectionInterface
     */
    protected $collection;

    /**
     * @var FlexDirectoryInterface
     */
    protected $directory;

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
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onTwigInitialized' => ['onTwigInitialized', 0]
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
        /** @var Flex $flex */
        $this->flex = Grav::instance()->get('flex');

        $this->directusUtil = new DirectusUtility(
            $this->config()['directus']['directusAPIUrl'],
            $this->grav,
            '',
            '',
            $this->config()['directus']['token'],
            $this->config()['disableCors']
        );

        $this->processWebHooks($this->grav['uri']->route());
    }

    /**
     * @param Event $e
     */
    public function onTwigInitialized(Event $e) {
        $this->grav['twig']->twig()->addFunction(
            new \Twig_SimpleFunction('directusFile', [$this, 'returnDirectusFile'])
        );
        $this->grav['twig']->twig()->addFunction(
            new \Twig_SimpleFunction('localize', [$this, 'localizeObject'])
        );
    }

    /**
     * @param array $object
     * @param string $lang
     * @return array
     */
    public function localizeObject(array $object, string $lang) {
        $directus = new Directus($this->grav, $this->config());
        return $directus->translate($object, $lang);

    }


    /**
     * @param array|null $fileReference
     * @param array|null $options
     * @return string
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function returnDirectusFile (?array $fileReference, ?array $options = []) {
        if(is_array($fileReference)) {
            if($this->config()['centralizedFileStorage'] === false) {
                $contentFolder = $this->grav['page']->path() . '/' . $this->config()['assetsFolderName'];
            } else {
                $contentFolder = 'user/data/' . $this->config()['assetsFolderName'];
            }
            $directusUtil = new DirectusUtility(
                ((isset($this->config()['imageServer']) && $this->config()['imageServer']) ? $this->config()['imageServer'] : $this->config()['directus']['directusAPIUrl']),
                $this->grav,
                $this->config()['directus']['email'],
                $this->config()['directus']['password'],
                $this->config()['directus']['token'],
                isset($this->config['disableCors']) && $this->config['disableCors']
            );

            if (!is_dir($contentFolder)) {
                mkdir ($contentFolder);
            }

            $url =  '/assets/' . $fileReference['id'];

            $hash = md5(json_encode($options));
            $path_parts = pathinfo($fileReference['filename_download']);
            if(!isset($path_parts['extension'])) {
                dd($fileReference);
            }
            $physicalPath = $contentFolder . '/';
            $fileName = $path_parts['filename'] . '-' . $hash . '.' . $path_parts['extension'];

            $fullPath = $physicalPath . $fileName;

            $c = 0;

            foreach ($options as $key => $value) {
                if($c === 0) {
                    $url .= '?' . $key . '=' . $value;
                } else {
                    $url .= '&' . $key . '=' . $value;
                }
                $c++;
            }

            if (!file_exists($fullPath)) {
                try {
                    $imageData = $directusUtil->get($url)->getContent();

                    $fp = fopen($fullPath,'x');
                    fwrite($fp, $imageData);
                    fclose($fp);
                } catch (\Exception $e) {
                    $this->grav['debugger']->addException($e);
                }
            }

            if($this->config()['centralizedFileStorage'] === false) {
                return '/' . $this->grav['page']->relativePagePath() . '/' . $this->config()['assetsFolderName'] . '/' . $fileName;
            } else {
                return '/user/data/' . $this->config()['assetsFolderName'] . '/' . $fileName;
            }

        } else {
            return null;
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
    public function refreshGlobalDataFiles() {
        $directus = $this->initializeDirectusLib();

        foreach($this->grav['pages']->instances() as $pageObject) {

            $directus->crawlPage($pageObject);
        }
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Global import completed'
        ]);

        Cache::clearCache();
        exit;
    }

    /**
     * @return Directus
     */
    private function initializeDirectusLib() {
        return new Directus($this->grav, $this->config());
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
                    $this->writeLog($e);
                }

                break;
            case '/' . $this->config()['directus']['hookPrefix'] . '/sync-flexobject':
                $this->processFlexObject();
                break;
            case '/' . $this->config()['directus']['hookPrefix'] . '/sync-flexobjects':
                $this->processFlexObjects();
                break;
        }
        return true;
    }

    /**
     * @throws \JsonException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function processFlexObject() {
        $requestBody = json_decode(file_get_contents('php://input'), true);

        if(isset($_REQUEST['debug'])) {
            $this->writeLog($this->buildLogEntry(json_encode( $requestBody, JSON_THROW_ON_ERROR), 'request from webhook'));
        }

        $statusCode = 0;

        if(isset($requestBody['collection'])) {

            /** @var FlexCollectionInterface $collection */
            $this->collection = $this->flex->getCollection($requestBody['collection']);

            /** @var FlexDirectoryInterface $directory */
            $this->directory = $this->flex->getDirectory($requestBody['collection']);

            $depth = 2;

            foreach($this->config()['directus']['synchronizeTables'] as $collection => $config) {
                if($collection === $requestBody['collection']) {
                    $depth = $config['depth'];
                }
            }
            try {
                switch ($requestBody['action']) {
                    case "create":
                        $statusCode = $this->createFlexObject($requestBody['collection'], $requestBody['item'], $depth);
                        break;
                    case "update":
                        $statusCode = $this->updateFlexObject($requestBody['collection'], $requestBody['item'], $depth);
                        break;
                    case "delete":
                        $statusCode = $this->deleteFlexObject($requestBody['collection'], $requestBody['item']);
                        break;
                }
            } catch(\Exception $e) {
                $this->writeLog($this->buildLogEntry($e, 'something went wrong'));
            }
        }

        if($statusCode === 200) {
            echo json_encode([
                'status' => '200',
                'message' => 'all done'
            ], JSON_THROW_ON_ERROR);
            Cache::clearCache();
            exit(200);
        }

        echo json_encode([
            'status' => $statusCode,
            'message' => 'something went wrong'
        ], JSON_THROW_ON_ERROR);
        if(isset($_REQUEST['debug'])) {
            $this->writeLog($this->buildLogEntry($statusCode, 'something went wrong'));
        }
        exit($statusCode);
    }

    /**
     * @throws \JsonException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function processFlexObjects() {
        $this->delTree('user/data/flex-objects');

        $collectionArray = $this->config()['directus']['synchronizeTables'];

        foreach ($collectionArray as $collection => $config){


            /** @var FlexCollectionInterface $collection */
            $this->collection = $this->flex->getCollection($collection);
            /** @var FlexDirectoryInterface $directory */
            $this->directory = $this->flex->getDirectory($collection);
            $response = $this->requestItem($collection, 0, ($config['depth'] ?? 2), ($config['filter'] ?? []));
            foreach ($response->toArray()['data'] as $item){
                $object = $this->collection->get($item['id']);

                if ($object) {
                    $object->update($item);
                    $object->save();
                } else {
                    $objectInstance = new FlexObject($item, $item['id'], $this->directory);
                    $object = $objectInstance->create($item['id']);
                    $this->collection->add($object);
                }
            }
        }
        echo json_encode([
            'status' => 200,
            'message' => 'all done'
        ], JSON_THROW_ON_ERROR);
        Cache::clearCache();
        exit(200);
    }

    /**
     * @param $collection
     * @param $id
     * @param int $depth
     * @return int
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function createFlexObject($collection, $id, int $depth = 2) {
        $response = $this->requestItem($collection, $id, $depth);

        if(isset($_REQUEST['debug'])) {
            $this->writeLog($this->buildLogEntry(json_encode($response->toArray(), JSON_THROW_ON_ERROR), 'create flex object - request to directus | data = response from directus'));
        }

        if($response->getStatusCode() === 200) {
            $data = $response->toArray()['data'];
            $objectInstance = new FlexObject($data, $data['id'], $this->directory);
            $object = $objectInstance->create($data['id']);
            $this->collection->add($object);
        }
        return $response->getStatusCode();
    }

    /**
     * @param $collection
     * @param $ids
     * @param int $depth
     * @return int
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function updateFlexObject($collection, $ids, int $depth = 2) {
        foreach ($ids as $id) {

            try {
                $response = $this->requestItem($collection, $id, $depth);

                if(isset($_REQUEST['debug'])) {
                    $this->writeLog($this->buildLogEntry(json_encode($response->toArray(), JSON_THROW_ON_ERROR), 'update flex object - request to directus | data = response from directus'));
                }
                if($response->getStatusCode() === 200) {
                    $object = $this->collection->get($id);

                    if ($object) {
                        $object->update($response->toArray()['data']);
                        $object->save();
                    } else {
                        $this->createFlexObject($collection, $id);
                    }
                }
            } catch(\Exception $e) {
                if(isset($_REQUEST['debug'])) {
                    $this->writeLog($this->buildLogEntry(json_encode($e, JSON_THROW_ON_ERROR), 'something went wrong'));
                }
            }

        }
        return 200;
    }

    /**
     * @param $collection
     * @param $ids
     * @return int
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function deleteFlexObject($collection, $ids) {
        foreach ($ids as $id) {
            $object = $this->collection->get($id);
            if($object) {
                $object->delete();
            }
        }
        return 200;
    }

    /**
     * @param $collection
     * @param $id
     * @param int $depth
     * @return \Symfony\Contracts\HttpClient\ResponseInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function requestItem($collection, $id = 0, $depth = 2, $filters = []) {

        $requestUrl = $this->directusUtil->generateRequestUrl($collection, $id, $depth, $filters);
        return $this->directusUtil->get($requestUrl);
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
        if( isset($_REQUEST['action']) && $_REQUEST['action'] === 'update')
        {
            foreach ($this->grav['pages']->instances() as $pageObject) {
                if(isset($pageObject->header()->directus)) {
                    $directusConfig = $pageObject->header()->directus;
                    if(isset($directusConfig['collection']) && $directusConfig['collection'] === $_REQUEST['table']) {
                        if (isset($directusConfig['id']))
                        {
                            if ((int)$directusConfig['id'] === (int)$_REQUEST['id']) {
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

    /**
     * @param $dir
     */
    private function delTree($dir){
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ( $fileinfo->isDir() ? 'rmdir' : 'unlink' );
            $todo( $fileinfo->getRealPath() );
        }
    }

    /**
     * @param $data
     * @param $action
     * @return string
     */
    private function buildLogEntry($data, $action) {
        $logText =  '######################################################################' . "\n" .
            'Date & Time: ' . date("Y/m/d H:i:s", time()) . "\n" .
            'Action: ' . $action . "\n" .
            'Data:' . "\n" .
            '----------------------------------------------------------------------' . "\n" .
            $data . "\n" .
            '----------------------------------------------------------------------' . "\n";
        return $logText;
    }

    /**
     * @param $data
     */
    private function writeLog($data): void
    {
        file_put_contents('logs/webhook_log_'.date("j.n.Y-H").'.log', $data, FILE_APPEND);
    }
}
