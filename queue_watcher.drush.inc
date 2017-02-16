<?php

/**
 * @file
 * Drush commands for queue_watcher.
 */

use \Drupal\Core\Queue\QueueInterface;
use \Drupal\Core\Queue\QueueWorkerInterface;
use \Drupal\Core\Queue\RequeueException;
use \Drupal\Core\Queue\SuspendQueueException;

/**
 * Implements hook_drush_command().
 */
function queue_watcher_drush_command() {
  $commands = array();

  $commands['queue-watcher-size'] = [
    'description' => dt('Shows size information of the given queue.'),
    'core' => ['8+'],
    'arguments' => [
      'queue' => dt('The (machine) name of the queue. Omit this argument to show all available queues.'),
    ],
  ];

  return $commands;
}

/**
 * Shows size information of the given queue.
 */
function drush_queue_watcher_size($queue_name = NULL) {
  $state_container = new \Drupal\queue_watcher\QueueStateContainer();
  $states = [];
  if (isset($queue_name)) {
    $states[$queue_name] = $state_container->getState($queue_name);
  }
  else {
    $states = $state_container->getAllStates();
  }

  foreach ($states as $state) {
    drush_print('---');
    drush_print(dt('Queue: @queue', ['@queue' => $state->getQueueName()]));
    drush_print(dt('Size (number of items): @num', ['@num' => $state->getNumberOfItems()]));
    drush_print('---');
  }
}
