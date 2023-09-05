<?php

namespace Firesphere\HealthcheckJobs\Services;

use GuzzleHttp\Exception\GuzzleException;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 *
 */
class HealthcheckQueuedJobService extends QueuedJobService
{
    protected $healthService;

    /**
     * @param int $jobId
     * @return void
     * @throws GuzzleException
     */
    public function runJob($jobId)
    {
        $this->healthService = HealthcheckService::init($jobId);
        $this->healthService->start(date('Y-m-d H:i:s'));
        $result = false;
        try {
            $result = parent::runJob($jobId);
        } catch (\Exception $e) {
            $this->healthService->fail($e->getMessage());
        }
        if (!$result) {
            $this->healthService->fail();
        } else {
            $this->healthService->success(date('Y-m-d H:i:s'));
        }
    }
}
