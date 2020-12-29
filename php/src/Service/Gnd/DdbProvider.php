<?php

namespace Service\Gnd;

class DdbProvider implements GndProviderInterface
{
    const URL_TEMPLATE = 'https://api.deutsche-digitale-bibliothek.de/entities?query=id:%22https://d-nb.info/gnd/{gnd}%22&oauth_consumer_key={oauth_consumer_key}';

    /**
     * @var \Guzzle\ClientInterface $http_client
     */
    protected $http_client;

    /**
     * @var array $options
     */
    protected $options;

    /**
     * Constructor
     *
     * @param \Guzzle\ClientInterface $http_client
     * @param array|null $options
     */
    public function __construct($http_client, $options = [])
    {
        $this->http_client = $http_client;
        $this->options = $options;
    }

    public function lookup($gnd) {
        if (empty($gnd)) {
            return;
        }

        $data = [
            'gnd' => $gnd,
            'oauth_consumer_key' => $this->options['oauth_consumer_key']
        ];

        $request = $this->http_client->createRequest('GET', [ self::URL_TEMPLATE, $data ]);

        $response = $this->http_client->send($request);
        if (200 != $response->getStatusCode()) {
            return;
        }

        $result = json_decode((string)$response->getBody(), true);

        if (false !== $result && $result['numberOfResults'] > 0) {
            // there should only by one result for a single GND, so return the first one
            $result = $result['results'][0];
            if (array_key_exists('name', $result) && 'single' == $result['name']) {
                return $result['docs'][0];
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'ddb';
    }

}
