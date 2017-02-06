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
  $query = \Drupal::database()
    ->select('queue', 'q')
    ->fields('q', ['name'])
    ->addExpression('COUNT(q.item_id)', 'num_items')
    ->groupBy('name');
  if (isset($queue_name)) {
    $query->where('q.name = :name', [':name' => $queue_name]);
  }

  $rows = $query->execute()->fetchAll();

  foreach ($rows as $row) {
    drush_print('---');
    drush_print(dt('Queue: @queue', ['@queue' => $row->name]));
    drush_print(dt('Size (number of items): @num', ['@num' => $row->num_items]));
    drush_print('---');
  }
}
