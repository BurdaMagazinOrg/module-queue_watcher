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

  $commands['queue-watcher-lookup'] = [
    'description' => dt('Performs a lookup and shows information of the given queue. Example: $ drush queue-watcher-lookup --report-problems'),
    'core' => ['8+'],
    'arguments' => [
      'queue' => dt('The (machine) name of the queue. Omit this argument to show all available queues.'),
    ],
    'options' => [
      'send-reports' => dt('Set this option to send reports about found problems to the configured recipient addresses. By default, the lookup command won\'t send reports.'),
      'recipient' => dt('Specifies an extra recipient address to send reports about problems (if enabled with --report-problems). You can also persistently setup recipient addresses in the Queue Watcher configuration.'),
      'quiet' => dt('Set this option to perform a quiet lookup, i.e. no console output.'),
    ],
  ];

  return $commands;
}

/**
 * Performs a lookup and shows information of the given queue.
 */
function drush_queue_watcher_lookup($queue_name = NULL) {
  // Get the options.
  $quiet = drush_get_option('quiet', FALSE);
  $send_reports = drush_get_option('send-reports', FALSE);
  $recipient = drush_get_option('recipient', NULL);

  $watcher = new \Drupal\queue_watcher\QueueWatcher();
  $config = $watcher->getConfig();
  if (isset($queue_name)) {
    $watcher->getStateContainer()->addEmptyState($queue_name);
  }
  $watcher->lookup();

  // Print out state information, when desired.
  if (!$quiet) {
    drush_print('------------------------------------------------------');
    foreach ($watcher->getLookupResult() as $state_level => $states) {
      foreach ($states as $state) {
        drush_print(dt('Queue: @queue', ['@queue' => $state->getQueueName()]));
        drush_print(dt('Size (number of items): @num', ['@num' => $state->getNumberOfItems()]));
        drush_print(dt('State level: @level', ['@level' => $state_level]));
        drush_print('------------------------------------------------------');
      }
    }
  }

  // Send reports on found problems.
  if ($send_reports && $watcher->foundProblems()) {
    if (!empty($recipient)) {
      $watcher->addRecipient($recipient);
    }
    $watcher->report();
    if (!$quiet) {
      drush_print(dt('Problem reports just have been sent to the configured recipients.'));
    }
  }
}
