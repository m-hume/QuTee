<?php

namespace Qutee;

/**
 * Task Interface
 *
 * @author anorgan
 */
class TaskInterface
{
  protected $_data;
  protected $_task;
  protected $_queue;
    /**
     * Set data needed for the task to run
     *
     * @param array $data
     */
    public function setData(array $data) {
      $this->_data = $data;
      if($this->_task){
        $this->_task->setData($data);
      }
    }

    /**
     * Get data associated with the task
     *
     * @param array $data
     */
    public function getData() {
      if($this->_task && $this->_task->getData()){
        return $this->_task->getData();
      }
      return $this->_data;
    }

    /**
     * Run the task
     */
    public function run($methodName = '', Task $task=null) {
      if (!is_null($task)){
        $this->_task = $task;
      }
      //print_r($this->_task);
      $methodName = ltrim($methodName, ' _');
      if (method_exists($this, $methodName)) {
        return call_user_func_array(array($this, $methodName), $this->_data);
      }

      return false;
    }

    public function reSchedule($schedule='', Task $task=null, array $data=[]){
      if (!is_null($task)){
        $this->_task = $task;
      }
      if($this->_task){
        $schedule = (bool)$schedule ? $schedule : static::schedule();
        $schedule = is_integer($schedule) ? $schedule : self::nextTime($schedule);
        $this->_task
          ->setDelayTill($schedule)
          ->setData($data)
          ->reSchedule()
        ;
        return $this->_task->getDelayTill();
      }
    }
    static public function schedule($methodName=''){
      return '';
    }

    protected function reCreateTask(){
      if($this->_task){
        $recreated = $this->_task->reCreate();
        if(!$recreated){
          // call the callee's failedReCreate method. Bad programming!!!
//          $cls = str_replace('/', '\\', $this->_task->getName());
//          $cls::failedReCreate($this->_task);
          // call the callee's failedReCreate method.
          static::failedReCreate($this->_task);
        }
        return $recreated;
      }
    }

    static protected function failedReCreate(\Qutee\Task $task){}

    protected function log($message, $level = Queue::EVENT_LOG){
      if($this->_task){
        $this->_queue = $this->_queue ?: Queue::get();
        $event = new Event($this);
        $event->setTask($this->_task);
        $event->setArgument('toLog', $message);
        $this->_queue->getEventDispatcher()->dispatch($level, $event);
      }
      else{
        echo $message . PHP_EOL;
      }
    }

    protected function warn($message){
      self::log($message, Queue::EVENT_WARN);
    }

    protected function error($message){
      if(is_a($message, 'Exception')){
        $message = sprintf("%s\n%s", $message->getMessage(), $message->getTraceAsString());
      }
      if($this->_task){
        $this->_task->setLastError($message);
      }
      self::log($message, Queue::EVENT_ERROR);
    }

//    public static function nextTime($time, $iterator='day', $base=null){
//      $iterators = ['day', 'weekday', 'month', 'year', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
//      if(!in_array(strtolower($iterator), $iterators)){
//        throw new \Exception('Bad iterator passed to '.__METHOD__.' accepts '.implode(',', $iterators));
//      }
//      $base = is_integer($base) ? $base : time();
//      $datetime = strtotime($time, $base);
//      // datetime in past or datetime not an iterator (not a weekend when $iterator='weekday')
//      while($datetime < $base || date('Ymd', $datetime) != date('Ymd', strtotime('+0 '.$iterator, $datetime))){
//        // concat the time back on as $iterator='weekday' looses the time
//        $datetime = strtotime('+1 '.$iterator.' '.date('H:i:s', $datetime), $datetime);
//      }
//      return $datetime;
//    }

    public static function nextTime($time, $iterator='day', $base=null){
      $iterators = ['day', 'week', 'weekday', 'workday', 'month', 'year'];
      if(!in_array(strtolower($iterator), $iterators)){
        throw new \Exception('Bad iterator passed to '.__METHOD__.' accepts '.implode(',', $iterators));
      }
      $base = is_integer($base) ? $base : time();
      $datetime = strtotime($time, $base);

      $workday = $iterator == 'workday';
      $iterator = $workday ? 'day' : $iterator;

      while($datetime < $base // datetime in past
        || date('Ymd', $datetime) != date('Ymd', strtotime('+0 '.$iterator, $datetime)) // datetime not an iterator (not a weekend when $iterator='weekday')
        || ($workday && date('l', $datetime) == 'Sunday') // workday is specified and its a sunday
        ){
        // concat the time back on as $iterator='weekday' looses the time
        $datetime = strtotime('+1 '.$iterator.' '.date('H:i:s', $datetime), $datetime);
      }
      return $datetime;
    }
}
