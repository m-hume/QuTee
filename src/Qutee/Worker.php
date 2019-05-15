<?php

namespace Qutee;

use Qutee\Exception;
use Qutee\Queue;
use Qutee\Task;
use Qutee\TaskInterface;

/**
 * Worker
 *
 * @author anorgan
 */
class Worker
{
    /**
     * Run every 5 seconds by default
     */
    const DEFAULT_INTERVAL = 5;

    const EVENT_START_PROCESSING_TASK = 'qutee.worker.start_processing_task';
    const EVENT_END_PROCESSING_TASK = 'qutee.worker.end_processing_task';

    /**
     * Run every X seconds
     *
     * @var int
     */
    protected $_interval = self::DEFAULT_INTERVAL;

    /**
     * Run every X seconds
     *
     * @var int
     */
    protected $_maxRunTime = false;

    /**
     * Do only tasks with this priority or all if priority is null
     *
     * @var int
     */
    protected $_priority;

    /**
     *
     * @var Queue
     */
    protected $_queue;

    /**
     *
     * @var float
     */
    protected $_workerStartTime;

    /**
     *
     * @var float
     */
    protected $_startTime;

    /**
     *
     * @var float
     */
    protected $_passedTime;

    /**
     *
     * @var
     */
    protected $_shmop = null;

    public function __construct() {
      $this->_workerStartTime = microtime(true);
      if(class_exists('\OScom\Shmop')){
        $this->_shmop = new \OScom\Shmop();
      }
    }

    /**
     *
     * @return int
     */
    public function getInterval()
    {
        return $this->_interval;
    }

    /**
     *
     * @param int $interval
     *
     * @return Worker
     */
    public function setInterval($interval)
    {
        $this->_interval = $interval;

        return $this;
    }

    /**
     *
     * @return int|false
     */
    public function getMaxRunTime()
    {
        return $this->_maxRunTime;
    }

    /**
     *
     * @param int $maxRunTime
     *
     * @return Worker
     */
    public function setMaxRunTime($maxRunTime)
    {
        $this->_maxRunTime = $maxRunTime;

        return $this;
    }

    /**
     *
     * @return array
     */
    public function getPriority()
    {
        return $this->_priority;
    }

    /**
     *
     * @param int $priority
     *
     * @return Worker
     *
     * @throws \InvalidArgumentException
     */
    public function setPriority($priority)
    {
        if ($priority !== null && !is_int($priority)) {
            throw new \InvalidArgumentException('Priority must be null or an integer');
        }

        $this->_priority = $priority;

        return $this;
    }

    /**
     *
     * @return Queue
     */
    public function getQueue()
    {
        if (null === $this->_queue) {
            $this->_queue = Queue::get();
        }

        return $this->_queue;
    }

    /**
     *
     * @param Queue $queue
     *
     * @return Worker
     */
    public function setQueue(Queue $queue)
    {
        $this->_queue = $queue;

        return $this;
    }

    /**
     * Run the worker, get tasks of the queue, run them
     *
     * @return Task|null Task which ran, or null if no task found
     * @throws \Exception
     */
    public function run()
    {
        // Start timing
        $this->_startTime();

        // Get next task with set priority (or any task if priority not set)
        if (null === ($task = $this->getQueue()->getTask($this->getPriority()))) {
            $this->_sleep();
            return;
        }

        $event = new Event($this);
        $event->setArgument('startTime', $this->_startTime);
        $event->setTask($task);

        $this->getQueue()->getEventDispatcher()->dispatch(self::EVENT_START_PROCESSING_TASK, $event);

        $this->_runTask($task);

        $event = new Event($this);
        $event->setArgument('elapsedTime', $this->_getPassedTime());
        $event->setTask($task);

        $this->getQueue()->getEventDispatcher()->dispatch(self::EVENT_END_PROCESSING_TASK, $event);

        // After working, sleep
        $this->_sleep();

        return $task;
    }

    /**
     * Start timing
     */
    protected function _startTime()
    {
        $this->_startTime = microtime(true);
    }

    /**
     * Get passed time
     *
     * @return float
     */
    protected function _getPassedTime()
    {
        return abs(microtime(true) - $this->_startTime);
    }

    /**
     * Get passed time
     *
     * @return float
     */
    protected function _getTotalPassedTime()
    {
        return abs(microtime(true) - $this->_workerStartTime);
    }

    /**
     * Sleep
     *
     * @return null
     */
    protected function _sleep()
    {
        // Time ... enough
        if ($this->_getPassedTime() <= $this->_interval) {
            for( $remainder = ($this->_interval - $this->_getPassedTime()); $remainder > 0; $remainder--){
              if($this->getMaxRunTime() && $this->_getTotalPassedTime() > $this->getMaxRunTime()){
                //max run time is up
                return;
              }
              if($this->_shmop){// if we have our shmop class
                $procs = $this->_shmop->getProcs();
                if( ($proc = $procs[getmypid()]) && isset($proc['j']) && $proc['j'] ){
                  //remove the job flag and return early to run job
                  $this->_shmop->setProc(['j'=>false]);
                  return;
                }
              }
              $sleep_for = ($remainder > 1) ? 1 : $remainder;
              usleep($sleep_for * 1000000);
            }
        } // Task took more than the interval, don't sleep
        return;
    }

    /**
     * Get class of the task, run it's default method or method specified in
     * task data [method]
     *
     * @param Task $task
     */
    protected function _runTask(Task $task)
    {
        $taskClassName  = $task->getClassName();
        if (!class_exists($taskClassName)) {
            throw new \InvalidArgumentException(sprintf('Task class "%s" not found', $taskClassName));
        }

        $taskObject     = new $taskClassName;
        $methodName     = $task->getMethodName();

        if ($taskObject instanceof TaskInterface) {

            $taskObject->setData($task->getData());
            $taskObject->run($methodName, $task); // send the method name and instance of task
            return $taskObject;

        } else {

            $taskObject->$methodName($task->getData());

        }
    }

}
