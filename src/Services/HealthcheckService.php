<?php

namespace Firesphere\HealthcheckJobs\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SilverStripe\Core\Config\Configurable;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

class HealthcheckService
{

    use Configurable;

    /**
     * @config
     * @var string Endpoint, e.g. 'https://health.example.com'
     */
    protected static $endpoint;
    /**
     * @config
     * @var string Management API Key to communicate with the endpoint
     */
    protected static $api_key;
    /**
     * @config
     * @var int
     */
    protected static $api_version;
    /**
     * @var QueuedJobDescriptor
     */
    protected $job;
    /**
     * @var string Ping endpoint on the healthcheck service
     */
    protected $pingUrl;
    /**
     * @var Client
     */
    protected $client;

    /**
     * @param QueuedJobDescriptor $job
     * @param string $endpoint
     * @param string $key
     * @throws GuzzleException
     */
    public function __construct(QueuedJobDescriptor $job, string $endpoint, string $key)
    {
        $this->client = new Client([
            'base_uri' => $endpoint,
            'headers'  => [
                'X-Api-Key' => $key
            ]
        ]);
        $this->job = $job;
        $this->getCheck();
    }

    /**
     * @return void
     * @throws GuzzleException
     */
    private function getCheck(): void
    {
        $versions = [
            1 => 'api/v1/checks/',
            3 => 'api/v3/checks/'
        ];
        $v = $this->config()->get('api_version');
        $check = $versions[$v];
        $name = $this->job->getTitle();

        $json = [
            'name'   => $name,
            "unique" => ['name']
        ];
        if (method_exists($this->job, 'getTimeout')) {
            $json['timeout'] = $this->job->getTimeout();
        }
        if (method_exists($this->job, 'getGrace')) {
            $json['grace'] = $this->job->getGrace();
        }
        if (method_exists($this->job, 'getCron')) {
            $json['schedule'] = $this->job->getCron();
        }
        $result = $this->client->post($check, [
            'json' => $json
        ]);

        $decoded = json_decode($result->getBody()->getContents(), true);

        $this->pingUrl = str_replace(self::$endpoint, '', $decoded['ping_url']);
    }

    /**
     * @param int $jobId The ID of the job to ping
     * @return self
     * @throws GuzzleException
     */
    public static function init(int $jobId): self
    {
        self::$endpoint = self::config()->get('endpoint');

        self::$api_key = self::config()->get('api_key');
        $job = QueuedJobDescriptor::get_by_id($jobId);

        return new self($job, self::$endpoint, self::$api_key);
    }

    /**
     * @param string|null $kw
     * @return string
     * @throws GuzzleException
     */
    public function start(?string $kw = ''): string
    {
        $message = $kw ?? date('Y-m-d H:i:s');
        $target = sprintf('%s/%s', rtrim($this->pingUrl, '/'), 'start');
        $result = $this->client->post($target, ['body' => $message]);

        return $result->getBody()->getContents();
    }

    /**
     * Stub for success
     * @param null|string $kw
     * @return string
     * @throws GuzzleException
     */
    public function success(?string $kw = null): string
    {
        return $this->ping($kw);
    }

    /**
     * @param null|string $kw Fallback message to use when there's no last message
     * @return string
     * @throws GuzzleException
     */
    public function ping(?string $kw = null): string
    {
        $message = $this->job->getLastMessage() ?? $kw;
        $result = $this->client->post($this->pingUrl, ['body' => $message]);

        return $result->getBody()->getContents();
    }

    /**
     * @param string|null $payload
     * @return string
     * @throws GuzzleException
     */
    public function fail(?string $payload = null): string
    {
        if (!$payload) {
            $payload = $this->job->getLastMessage();
        }
        $target = sprintf('%s/%s', rtrim($this->pingUrl, '/'), 'fail');

        $result = $this->client->post($target, ['body' => $payload]);

        return $result->getBody()->getContents();
    }

    /**
     * @return QueuedJobDescriptor
     */
    public function getJob(): QueuedJobDescriptor
    {
        return $this->job;
    }

    /**
     * @param QueuedJobDescriptor $job
     * @return void
     */
    public function setJob(QueuedJobDescriptor $job): void
    {
        $this->job = $job;
    }
}
