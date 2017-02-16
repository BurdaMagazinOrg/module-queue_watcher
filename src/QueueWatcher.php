<?php

namespace Drupal\queue_watcher;

/**
 * The QueueWatcher class.
 */
class QueueWatcher {

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * @var QueueStateContainer
   */
  protected $state_container;

  /**
   * @var array
   */
  protected $queues_to_watch;

  /**
   * @var array
   */
  protected $recipients_to_report;

  public function __construct() {
    $this->config = \Drupal::config('queue_watcher.config');
    $this->state_container = new QueueStateContainer();
    $this->initQueuesToWatch();
    $this->initRecipientsToReport();
  }

  /**
   * Get the Queue Watcher configuration.
   * 
   * @return \Drupal\Core\Config\ImmutableConfig
   */
  public function getConfig() {
    return $this->config;
  }

  /**
   * Performs a lookup on the queues,
   * which are added in the Queue Watcher configuration.
   */
  public function lookup() {
    $states = $this->state_container->getAllStates();
    foreach ($this->queues_to_watch as $queue_name => $defined) {
      if (empty($states[$queue_name])) {
        continue;
      }
      $state = $states[$queue_name];
      if ($state->exceeds($defined['size_limit_warning'])) {
        // Add the exceedance to warning list.
      }
    }
  }

  public function foundProblems() {
    return TRUE;
  }

  public function reportProblems() {
    // Use logger...
    foreach ($this->recipients_to_report as $recipient) {
      // Send reports...
    }
  }

  protected function initQueuesToWatch() {
    $to_watch = [];
    foreach ($this->getConfig()->get('watch_queues') as $watch_item) {
      if (!empty($watch_item['queue_name'])) {
        $name = $watch_item['queue_name'];
        $to_watch[$name] = $watch_item;
      }
    }
    $this->queues_to_watch = $to_watch;
  }

  protected function initRecipientsToReport() {
    $recipients = [];
    foreach (explode(', ', $this->getConfig()->get('mail_recipients')) as $address) {
      $recipients[$address] = $address;
    }
    if ($this->getConfig()->get('use_site_mail')) {
      $site = \Drupal::config('system.site');
      if ($address = $site->get('mail')) {
        $recipients[$address] = $address;
      }
    }
    if ($this->getConfig()->get('use_admin_mail')) {
      $account = \Drupal::entityTypeManager()->getStorage('user')->load(1);
      if ($account && ($address = $account->getEmail())) {
        $recipients[$address] = $address;
      }
    }
    $this->recipients_to_report = $recipients;
  }
}
