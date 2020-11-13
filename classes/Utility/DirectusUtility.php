<?php
namespace Grav\Plugin\Directus\Utility;


use Symfony\Component\HttpClient\HttpClient;

/**
 * Class DirectusUtility
 * @package Grav\Plugin\Directus\Utility
 */
class DirectusUtility
{
    /**
     * @var \Symfony\Contracts\HttpClient\HttpClientInterface
     */
    private $httpClient;

    /**
     * @var string
     */
    private $apiServer;

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $token;

    /**
     * DirectusUtility constructor.
     * @param string $apiUrl
     * @param string $projectName
     * @param string $email
     * @param string $password
     * @param string $token
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function __construct(string $apiUrl, string $projectName, string $email = '', string $password = '', string $token = '')
    {
        $this->httpClient = HttpClient::create();
        $this->apiServer = $apiUrl . '/' . $projectName;
        $this->email = $email;
        $this->password = $password;
        $this->token = $token ? $token : $this->requestToken();
    }

    /**
     * @return mixed
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function requestToken () {
        $options = [
            'body' => [
                'email' => $this->email,
                'password' => $this->password
            ],
        ];

        $response = $this->httpClient->request(
            'POST',
            $this->apiServer . '/auth/authenticate',
            $options
        );

        return json_decode($response->getContent())->data->token;

    }

    /**
     * @return string[]
     */
    private function getAuthorizationHeaders() {
        return [
            'Authorization' => 'bearer ' . $this->token
        ];
    }

    /**
     * @param string $path
     * @return \Symfony\Contracts\HttpClient\ResponseInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function get($path = '')
    {
        $options = [
            'headers' => $this->getAuthorizationHeaders()
        ];

        return $this->httpClient->request(
            'GET',
            $this->apiServer . $path,
            $options
        );
    }

    /**
     * @param string $collection
     * @param int $id
     * @param int $depth
     * @param array $filters
     * @param int $limit
     * @return string
     */
    public function generateRequestUrl(string $collection, int $id = 0, int $depth = 2, array $filters = [], int $limit = -1, string $sort = '') {
        $url = '/items/' . $collection . ($id ? '/' : null);

        if($id) {
            $url .= (string)$id;
        }
        $url .= '?';
        if($depth > 0) {
            $url .= 'fields=';
            for($i = 1; $i <= $depth; $i++) {
                $url .= '*';
                $i < $depth ? $url .= '.' : null;
            }
        }
        foreach($filters as $field => $filter) {
            $url .= '&filter[' . $field . ']' . ( isset($filter['operator']) ? '[' . $filter['operator'] . ']' : null ) . '=' . $filter['value'];
        }
        $url .= '&limit=' . (string)$limit;
        if($sort) {
            $url .= '&sort=' . $sort;
        }


        return $url;
    }
}
