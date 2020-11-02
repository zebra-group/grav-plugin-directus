<?php
namespace Grav\Plugin\Directus\Utility;


use Symfony\Component\HttpClient\HttpClient;

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
     */
    public function __construct(string $apiUrl, string $projectName, string $email = '', string $password = '')
    {
        $this->httpClient = HttpClient::create();
        $this->apiServer = $apiUrl . '/' . $projectName;
        $this->email = $email;
        $this->password = $password;
        $this->token = $this->requestToken();
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

        $response = $this->httpClient->request(
            'GET',
            $this->apiServer . $path,
            $options
        );
        return $response;
    }
}
