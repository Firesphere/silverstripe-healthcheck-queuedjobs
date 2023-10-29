<?php

namespace Firesphere\HealthcheckJobs\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\CronTask\Interfaces\CronTask;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

/**
 * Class \Firesphere\HealthcheckJobs\Services\HealthcheckService
 *
 * @package Firesphere\HealthcheckJobs
 */
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
     * @var string[]
     */
    protected static $versions = [
        1 => 'api/v1/checks/',
        3 => 'api/v3/checks/'
    ];
    /**
     * @var CronTask|QueuedJobDescriptor
     */
    protected $task;
    /**
     * @var string Ping endpoint on the healthcheck service
     */
    protected $pingUrl;
    /**
     * @var Client
     */
    protected $client;

    /**
     * @param null|QueuedJobDescriptor $job
     * @param string $endpoint
     * @param string $key
     * @throws GuzzleException
     */
    public function __construct(?QueuedJobDescriptor $job, string $endpoint, string $key)
    {
        $this->client = new Client([
            'base_uri' => $endpoint,
            'headers'  => [
                'X-Api-Key' => $key
            ]
        ]);
        if ($job) {
            $this->task = $job;
        }
        $this->getCheck();
    }

    /**
     * @return void
     * @throws GuzzleException
     */
    private function getCheck(): void
    {
        if (!$this->task) {
            return;
        }
        $v = self::config()->get('api_version');
        $check = self::$versions[$v];
        $name = get_class($this->task);
        if (method_exists($this->task, 'getTitle')) {
            $name = $this->task->getTitle();
        }

        $json = [
            'name'   => $name,
            "unique" => ['name']
        ];
        if (method_exists($this->task, 'getTimeout')) {
            $json['timeout'] = $this->task->getTimeout();
            if (method_exists($this->task, 'getGrace')) {
                $json['grace'] = $this->task->getGrace();
            }
        } elseif (method_exists($this->task, 'getSchedule')) {
            $json['schedule'] = $this->task->getSchedule();
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
        self::$endpoint = self::config()->get('endpoint') ?? '';

        self::$api_key = self::config()->get('api_key') ?? '';
        $job = null;
        if ($jobId > 0) {
            $job = QueuedJobDescriptor::get_by_id($jobId);
        }

        return new self($job, self::$endpoint, self::$api_key);
    }

    /**
     * @param string|null $kw
     * @return string
     * @throws GuzzleException
     */
    public function start(?string $kw = ''): string
    {
        if (!$this->task || !self::$endpoint || !self::$api_key) {
            return '';
        }

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
        if (!$this->task || !self::$endpoint || !self::$api_key) {
            return '';
        }

        $message = $kw . $this->task->getLastMessage();
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
        if (!$this->task || !self::$endpoint || !self::$api_key) {
            return '';
        }

        if (!$payload) {
            $payload = $this->task->getLastMessage();
        }
        $target = sprintf('%s/%s', rtrim($this->pingUrl, '/'), 'fail');

        $result = $this->client->post($target, ['body' => $payload]);

        return $result->getBody()->getContents();
    }

    /**
     * @return CronTask|QueuedJobDescriptor
     */
    public function getTask(): CronTask|QueuedJobDescriptor
    {
        return $this->task;
    }

    /**
     * @param CronTask|QueuedJobDescriptor $task
     * @return void
     * @throws GuzzleException
     */
    public function setTask(CronTask|QueuedJobDescriptor $task): void
    {
        $this->task = $task;
        $this->getCheck();
    }
}
