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
    'description' => dt('Performs a lookup and shows information about given and configured queues. Example: $ drush queue-watcher-lookup my_queue another_queue --report-problems'),
    'core' => ['8+'],
    'arguments' => [
      'queue' => dt('(Optional) The machine name of the queue(s) you want to be watched, even when it\'s empty or not configured.'),
    ],
    'options' => [
      'report-problems' => dt('Set this option to send reports about found problems to the configured recipient addresses. By default, the lookup command won\'t send reports.'),
      'recipient' => dt('Specifies an extra recipient address to send reports about problems (if enabled with --report-problems). You can also persistently setup recipient addresses in the Queue Watcher configuration.'),
      'quiet' => dt('Set this option to perform a quiet lookup, i.e. no console output.'),
    ],
  ];

  return $commands;
}

/**
 * Performs a lookup and shows information of the given queue.
 */
function drush_queue_watcher_lookup() {
  // Get the queues, which are defined by the user as arguments.
  $user_defined_queues = func_get_args();
  // Get the options.
  $quiet = drush_get_option('quiet', FALSE);
  $report_problems = drush_get_option('report-problems', FALSE);
  $recipient = drush_get_option('recipient', NULL);

  $watcher = new \Drupal\queue_watcher\QueueWatcher();

  // Explicitly add the queues, which are defined by the user.
  $config = $watcher->getConfig();
  if (!empty($user_defined_queues)) {
    foreach ($user_defined_queues as $queue_name) {
      $watcher->getStateContainer()->addEmptyState($queue_name);
    }
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

  // Send reports when problems have been found.
  if ($report_problems && $watcher->foundProblems()) {
    if (!empty($recipient)) {
      $watcher->addRecipient($recipient);
    }
    $watcher->report();
    if (!$quiet) {
      drush_print(dt('Problem reports just have been sent to the configured recipients.'));
    }
  }
}
