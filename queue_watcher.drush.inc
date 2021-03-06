<?php

/**
 * @file
 * Drush commands for queue_watcher.
 */

/**
 * Implements hook_drush_command().
 */
function queue_watcher_drush_command() {
  $commands = array();

  $commands['queue-watcher-lookup'] = [
    'description' => dt('Performs a lookup and shows information about given and configured queues. Example: $ drush queue-watcher-lookup my_queue another_queue --report-problems'),
    'core' => ['8+'],
    'arguments' => [
      'queue' => dt('(Optional) The machine name of the queue(s) you want to be watched, even when the queue is empty or not configured.'),
    ],
    'options' => [
      'report-problems' => dt('Set this option to send reports about found problems to the configured recipient addresses. By default, the lookup command won\'t send reports.'),
      'recipient' => dt('Specifies an extra recipient mail address to send reports about problems (if enabled with --report-problems). Multiple mail addresses are to be separated by \', \'. You can also persistently setup recipient addresses in the Queue Watcher configuration.'),
      'quiet' => dt('Set this option to perform a quiet lookup, i.e. no console output.'),
    ],
  ];

  $commands['queue-watcher-get-state'] = [
    'description' => dt('Performs a lookup and shows the worst discovered state level of all available queues, or the current state regarding a single queue when given as argument.'),
    'core' => ['8+'],
    'arguments' => [
      'queue' => dt('(Optional) The machine name of a queue. When given, the output will be the state level exclusively regarding this queue.'),
    ],
  ];

  $commands['queue-watcher-get-size'] = [
    'description' => dt('Performs a lookup and shows the largest size of all available queues, or the current size regarding a single queue when given as argument.'),
    'core' => ['8+'],
    'arguments' => [
      'queue' => dt('(Optional) The machine name of a queue. When given, the output will be the size exclusively regarding this queue.'),
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

  /* @var \Drupal\queue_watcher\QueueWatcher $watcher */
  $watcher = \Drupal::service('queue_watcher');

  // Explicitly add the queues, which are defined by the user.
  if (!empty($user_defined_queues)) {
    foreach ($user_defined_queues as $queue_name) {
      $watcher->getStateContainer()->addEmptyState($queue_name);
    }
  }

  $watcher->lookup();

  // Print out state information, when desired.
  if (!$quiet) {
    drush_print($watcher->getReadableStatus());
  }

  // Send reports when problems have been found.
  if ($report_problems && $watcher->foundProblems()) {
    if (!empty($recipient)) {
      $mail_addresses = explode(', ', $recipient);
      foreach ($mail_addresses as $mail_address) {
        $watcher->addRecipient($mail_address);
      }
    }

    $watcher->report();

    if (!$quiet) {
      drush_print('------------------------------------------------------');
      drush_print(dt('The summary above has just been sent to the configured recipients.'));
      drush_print('------------------------------------------------------');
    }
  }
}

/**
 * Shows the worst discovered state level of all queues, or for a given queue.
 *
 * @param string $queue_name
 *   (Optional) The machine name of the queue.
 */
function drush_queue_watcher_get_state($queue_name = NULL) {
  /* @var \Drupal\queue_watcher\QueueWatcher $watcher */
  $watcher = \Drupal::service('queue_watcher');

  if (!empty($queue_name)) {
    $watcher->getStateContainer()->addEmptyState($queue_name);
  }

  $watcher->lookup();

  if (!empty($queue_name)) {
    $state = $watcher->getStateContainer()->getState($queue_name);
    drush_print($state->getStateLevel());
  }
  else {
    drush_print($watcher->getWorstDiscoveredStateLevel());
  }
}

/**
 * Shows the largest size of all queues, or for a given queue.
 *
 * @param string $queue_name
 *   (Optional) The machine name of the queue.
 */
function drush_queue_watcher_get_size($queue_name = NULL) {
  /* @var \Drupal\queue_watcher\QueueWatcher $watcher */
  $watcher = \Drupal::service('queue_watcher');

  if (!empty($queue_name)) {
    $watcher->getStateContainer()->addEmptyState($queue_name);
  }

  $watcher->lookup();

  if (!empty($queue_name)) {
    $state = $watcher->getStateContainer()->getState($queue_name);
    drush_print($state->getNumberOfItems());
  }
  else {
    drush_print($watcher->getLargestDiscoveredQueueSize());
  }
}
