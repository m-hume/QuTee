<?php

namespace Qutee;

use Qutee\Queue;

/**
 * Task
 *
 * @author anorgan
 */
class Task
{
    const EVENT_RECREATE_PROCESSING_TASK = 'qutee.task.recreate_processing_task';

    /**
     * Default name of the method to run the task
     */
    const DEFAULT_METHOD_NAME   = 'run';

    /**
     * Test priority
     */
    const PRIORITY_TEST         = 0;

    /**
     * Low priority
     */
    const PRIORITY_LOW          = 1;

    /**
     * Normal priority
     */
    const PRIORITY_NORMAL       = 2;

    /**
     * High priority
     */
    const PRIORITY_HIGH         = 3;

    /**
     *
     * @var string
     */
    protected $_name;

    /**
     *
     * @var string
     */
    protected $_methodName;

    /**
     *
     * @var array
     */
    protected $_data;

    /**
     *
     * @var int
     */
    protected $_priority = self::PRIORITY_NORMAL;

    /**
     *
     * @var string
     */
    protected $_uniqueId;

    /**
     *
     * @var string
     */
    protected $_retryDelta;

    /**
     *
     * @var string
     */
    protected $_delayTill;

    /**
     *
     * @var int
     */
    protected $_retries = null;

    /**
     *
     * @var int
     */
    protected $_origRetries = null;

    /**
     *
     * @var string
     */
    protected $_lastError = '';

    /**
     *
     * @param string $name
     * @param array $data
     * @param int $priority
     * @param string $unique_id
     * @param string $methodName
     *
     * @param array $data
     */
    public function __construct($name = null, $data = array(), $priority = self::PRIORITY_NORMAL, $unique_id = null, $methodName = null)
    {
        if (null !== $name) {
            $this->setName($name);
        }

        if (null !== $data) {
            $this->setData($data);
        }

        if (null !== $methodName) {
            $this->setMethodName($methodName);
        }

        if (null !== $priority) {
            $this->setPriority($priority);
        }

        if (null !== $unique_id) {
            $this->setUniqueId($unique_id);
        }
    }

    /**
     *
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     *
     * @param string $name
     *
     * @return Task
     */
    public function setName($name)
    {
        // Name can hold method name in it
        if (strpos($name, '::')) {
            list($name, $methodName) = explode('::', $name);
        }

        // Validate name
        if (!preg_match('/^[a-zA-Z0-9\/\\\ _-]+$/', $name)) {
            throw new \InvalidArgumentException('Name can be only alphanumerics, spaces, underscores and dashes');
        }

        if (isset($methodName)) {
            $this->setMethodName($methodName);
        }

        $this->_name = $name;

        return $this;
    }

    /**
     *
     * @return string
     */
    public function getMethodName()
    {
        if ($this->_methodName === null) {
            $this->_methodName = self::DEFAULT_METHOD_NAME;
        }

        return $this->_methodName;
    }

    /**
     *
     * @param string $methodName
     * @return \Qutee\Task
     *
     * @throws \InvalidArgumentException
     */
    public function setMethodName($methodName)
    {
        // validate name
        if (!preg_match('/^[a-z][a-zA-Z0-9_]+$/', $methodName)) {
            throw new \InvalidArgumentException('Method name can be only alphanumerics and underscores');
        }

        $this->_methodName = $methodName;

        return $this;
    }

    /**
     *
     * @return string
     */
    public function getLastError()
    {
        return $this->_lastError;
    }

    /**
     *
     * @param string $error
     * @return \Qutee\Task
     */
    public function setLastError($error)
    {
        $this->_lastError = $error;

        return $this;
    }

    /**
     *
     * @return string
     * @throws Exception
     */
    public function getClassName()
    {
        if ($this->_name === null) {
            throw new Exception('Name not set, can not create class name');
        }

        if (strpos($this->_name, '\\') !== false) {
            // FQCN?
            $className = $this->_name;
        } elseif (strpos($this->_name, '/') !== false) {
            // Forward slash FQCN?
            $className = str_replace('/', '\\', $this->_name);
        } else {
            $className = str_replace(array('-','_'), ' ', strtolower($this->_name));
            $className = str_replace(' ', '', ucwords($className));
        }

        return $className;
    }

    /**
     *
     * @return array
     */
    public function getData()
    {
        return $this->_data;
    }

    /**
     *
     * @param array $data
     *
     * @return Task
     */
    public function setData(array $data)
    {
        $this->_data = $data;

        return $this;
    }

    /**
     *
     * @return int
     */
    public function getPriority()
    {
        return $this->_priority;
    }

    /**
     *
     * @param int $priority
     *
     * @return Task
     */
    public function setPriority($priority)
    {
        $this->_priority = $priority;

        return $this;
    }

    /**
     *
     * @return string|boolean
     */
    public function getUniqueId()
    {
        if (!$this->isUnique()) {
            return false;
        }

        return md5($this->getName() . $this->_uniqueId);
    }

    /**
     *
     * @param string $uniqueId
     *
     * @return \Qutee\Task
     */
    public function setUniqueId($uniqueId)
    {
        $this->_uniqueId = $uniqueId;

        return $this;
    }

    /**
     * Task is unique if unique identifier is not null
     *
     * @return boolean
     */
    public function isUnique()
    {
        return !is_null($this->_uniqueId);
    }

    /**
     *
     * @return string
     */
    public function getDelayTill()
    {
        return $this->_delayTill;
    }

    /**
     *
     * @param string|integer $delayTill
     *
     * @return \Qutee\Task
     */
    public function setDelayTill($delayTill)
    {
        $time = is_int($delayTill) ? $delayTill : strtotime($delayTill);
        $this->_delayTill = $time === false ? null : date('c' , $time);

        return $this;
    }

    /**
     *
     * @return integer
     */
    public function getRetries()
    {
        return $this->_retries;
    }

    /**
     *
     * @return integer
     */
    public function getOriginalRetries()
    {
        return $this->_origRetries;
    }

    /**
     *
     * @param integer|null $retries
     *
     * @return \Qutee\Task
     */
    public function setRetries($retries)
    {
        $this->_origRetries = $this->_retries = (($retries === null) ? null :(int)$retries);

        return $this;
    }

    /**
     *
     * @return boolean
     */
    protected function _canRetry()
    {
        if($this->_retries === null){
          return false;
        }
        return (--$this->_retries >= 0);

    }

    /**
     *
     * @return string
     */
    public function getRetryDelta()
    {
        return $this->_retryDelta;
    }

    /**
     *
     * @param string $retryDelta
     *
     * @return \Qutee\Task
     */
    public function setRetryDelta($retryDelta)
    {
        $time = strtotime($retryDelta);
        $this->_retryDelta = $time === false ? null : $retryDelta;

        return $this;
    }

    /**
     *
     * @return array
     */
    public function __sleep()
    {
        return array('_name', '_data', '_methodName', '_priority', '_uniqueId', '_delayTill', '_retries', '_origRetries', '_retryDelta');
    }

    /**
     *
     * @param string $name
     * @param array $data
     * @param int $priority
     * @param string $unique_id
     * @param string $methodName
     *
     * @return Task
     */
    public static function create($name, $data = array(), $priority = self::PRIORITY_NORMAL, $unique_id = null, $methodName = null)
    {

        $queue  = Queue::get();
        $task   = new self($name, $data, $priority, $unique_id, $methodName);
        $queue->addTask($task);

        return $task;
    }

    /**
     *
     * @param Task $task is the task you'd like to recreate
     *
     * @return Task
     */
//    public static function reCreate(Task $task)
    public function reCreate($force = false)
    {
//var_dump($this);
        if(!$this->_canRetry()) {
          return false;
        }
        $delay_till = $this->getDelayTill();
        if(!($delay_till && strtotime($delay_till)>=time())){ // no fututre delay_till
          $retry_delta = $this->getRetryDelta();
          if($retry_delta){
            $this->setDelayTill($retry_delta);
          }
        }

        $queue  = Queue::get();
        $queue->addTask($this, $force, true);

        return true;
    }

    /**
     *
     * @param Task $task is the task you'd like to recreate
     *
     * @return Task
     */
    public function reSchedule($force = false)
    {
//var_dump($this);

        $queue  = Queue::get();
        $this->setRetries($this->_origRetries);
        $queue->addTask($this, $force, false);

        return true;
    }
}
