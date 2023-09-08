<?php

namespace Firesphere\HealthcheckJobs\Controllers;

use Cron\CronExpression;
use Firesphere\HealthcheckJobs\Services\HealthcheckService;
use SilverStripe\CronTask\Controllers\CronTaskController;
use SilverStripe\CronTask\CronTaskStatus;
use SilverStripe\CronTask\Interfaces\CronTask;

/**
 * Class \Firesphere\HealthcheckJobs\Controllers\HealthcheckCronTaskController
 *
 */
class HealthcheckCronTaskController extends CronTaskController
{
    /**
     * @var HealthcheckService
     */
    private $healthService;
    public function __construct()
    {

        parent::__construct();
    }

    /**
     * Checks and runs a single CronTask
     *
     * @param CronTask $task
     * @throws \Exception|\GuzzleHttp\Exception\GuzzleException
     */
    public function runTask(CronTask $task)
    {
        $cron = new CronExpression($task->getSchedule());
        $isDue = $this->isTaskDue($task, $cron);
        // Update status of this task prior to execution in case of interruption
        CronTaskStatus::update_status(get_class($task), $isDue);
        if ($isDue) {
            $this->healthService = HealthcheckService::init(-1);
            $this->healthService->setTask($task);
            $this->healthService->start(date('Y-m-d H:i:s'));
            $this->output(_t(self::class . '.WILL_START_NOW', '{task} will start now.', ['task' => get_class($task)]));
            try {
                $task->process();
                $this->healthService->success(date('Y-m-d H:i:s'));
            } catch (\Exception $e) {
                $this->healthService->fail($e->getMessage());
            }
        } else {
            $this->output(
                _t(
                    self::class . '.WILL_RUN_AT',
                    '{task} will run at {time}.',
                    ['task' => get_class($task), 'time' => $cron->getNextRunDate()->format('Y-m-d H:i:s')]
                ),
                2
            );
        }
    }

}
