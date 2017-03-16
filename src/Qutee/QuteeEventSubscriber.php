<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of QuteeEventSubscriber
 *
 * @author jon
 */

namespace Qutee;

// The subscriber:
class QuteeEventSubscriber implements \Symfony\Component\EventDispatcher\EventSubscriberInterface {

	protected static $log_location = '';

	public function __construct($log_location = '') {
		self::$log_location = (bool)$log_location ? $log_location : __DIR__ .'/events.log';
		//echo "Inside the constructor ".self::$log_location."\n";
	}

	public static function getSubscribedEvents() {
		return array(
			\Qutee\Queue::EVENT_ADD_TASK => array(
				'addTask',
				0
			),
			\Qutee\Worker::EVENT_START_PROCESSING_TASK => array(
				'processTask',
				0
			),
			\Qutee\Worker::EVENT_END_PROCESSING_TASK => array(
				'processTaskEnd',
				0
			),
		);
	}

	public function addTask(\Qutee\Event $event) {
		if(!$event->getArgument('isRetry')){
			$this->log(sprintf('Added task: %s::%s()', $event->getTask()->getName(), $event->getTask()->getMethodName()));
		}
		else{
			$this->log(sprintf('reAdding task: %s::%s() %d Retries left', $event->getTask()->getName(), $event->getTask()->getMethodName(), $event->getTask()->getRetries()));
		}
	}

	public function processTask(\Qutee\Event $event) {
		$this->log(sprintf('Processing task %s::%s() started', $event->getTask()->getName(), $event->getTask()->getMethodName()));
	}

	public function processTaskEnd(\Qutee\Event $event) {
		$this->log(sprintf('Processing task %s::%s() finished, lasted %f seconds', $event->getTask()->getName(), $event->getTask()->getMethodName(), $event->getArgument('elapsedTime')));
	}

	protected function log($message) {
		file_put_contents(self::$log_location, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
	}

}
