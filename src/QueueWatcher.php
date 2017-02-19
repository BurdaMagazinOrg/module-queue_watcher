<?php

namespace Drupal\queue_watcher;

use \Psr\Log\LoggerInterface;
use \Drupal\Core\Mail\MailManagerInterface;

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

  /**
   * @var array
   */
  protected $lookup_result;

  /**
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * @var MailManagerInterface
   */
  protected $mail_manager;

  /**
   * $var string
   */
  protected $current_langcode;

  /**
   * @param QueueStateContainer $state_container
   *   The QueueStateContainer instance.
   * @param MailManagerInterface $mail_manager
   *   The mail manager.
   */
  public function __construct(QueueStateContainer $state_container, MailManagerInterface $mail_manager) {
    $this->config = \Drupal::config('queue_watcher.config');
    $this->logger = \Drupal::logger('queue_watcher');
    $this->state_container = $state_container;
    $this->mail_manager = $mail_manager;
    $this->current_langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $this->initQueuesToWatch();
    $this->initRecipientsToReport();
    $this->initLookupResult();
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
   * Get the QueueStateContainer.
   *
   * @return QueueStateContainer
   */
  public function getStateContainer() {
    return $this->state_container;
  }

  /**
   * Performs a lookup on the queues,
   * which are added in the Queue Watcher configuration.
   */
  public function lookup() {
    $states = $this->getStateContainer()->getAllStates();
    foreach ($this->queues_to_watch as $queue_name => $defined) {
      if (empty($states[$queue_name])) {
        $this->getStateContainer()->addEmptyState($queue_name);
        $states[$queue_name] = $this->getStateContainer()->getState($queue_name);
      }
      $state = $states[$queue_name];
      if ($state->exceeds($defined['size_limit_critical'])) {
        $state->setStateLevel('critical');
        $this->lookup_result['critical'][$queue_name] = $state;
        unset($this->lookup_result['warning'][$queue_name]);
        unset($this->lookup_result['sane'][$queue_name]);
      }
      elseif ($state->exceeds($defined['size_limit_warning'])) {
        $state->setStateLevel('warning');
        unset($this->lookup_result['critical'][$queue_name]);
        $this->lookup_result['warning'][$queue_name] = $state;
        unset($this->lookup_result['sane'][$queue_name]);
      }
      else {
        $state->setStateLevel('sane');
        unset($this->lookup_result['critical'][$queue_name]);
        unset($this->lookup_result['warning'][$queue_name]);
        $this->lookup_result['sane'][$queue_name] = $state;
      }
      unset($states[$queue_name]);
    }
    // Add the states of queues,
    // which are not added (yet) in the Queue Watcher configuration.
    foreach ($states as $queue_name => $not_configured) {
      $this->lookup_result['undefined'][$queue_name] = $not_configured;
    }
  }

  /** 
   * Returns TRUE if the watcher found problems after a ::lookup().
   *
   * @return boolean
   *  TRUE if the watcher found problems, FALSE otherwise.
   */
  public function foundProblems() {
    if (!empty($this->getWarningQueueStates()) || !empty($this->getCriticalQueueStates())) {
      return TRUE;
    }
    if ($this->getConfig()->get('notify_undefined') && !empty($this->getUndefinedQueueStates())) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Reports the current queue states to the configured recipients and logs.
   */
  public function report() {
    if ($this->getConfig()->get('use_logger')) {
      $this->logStatus($this->logger());
    }

    $this->mailStatus($this->getRecipientsToReport());
  }

  /**
   * Returns the lookup result.
   *
   * @return array
   *  An array of QueueStates,
   *  which are keyed by 'sane', 'warning' and 'critical'.
   */
  public function getLookupResult() {
    return $this->lookup_result;
  }

  /**
   * Returns a user-readable summary of the current information the watcher has.
   *
   * @param $langcode
   *  (Optional) The desired language translation code of the summary.
   *
   * @return string
   *  A summary of the watcher's status information.
   */
  public function getReadableStatus($langcode = NULL) {
    if (!isset($langcode)) {
      $langcode = $this->current_langcode;
    }

    $info = '------------------------------------------------------' . "\n";
    if ($this->foundProblems()) {
      $info .= '.. ' .  t('The Queue Watcher has detected problematic queue states!') . ' ..' . "\n";
    }
    else {
      $info .= '.. ' .  t('The Queue Watcher hasn\'t found any problematic queue states.') . ' ..' . "\n";
    }
    $info .= '------------------------------------------------------' . "\n";
    foreach ($this->getLookupResult() as $states) {
      foreach ($states as $state) {
        $info .= t('Queue: @queue', ['@queue' => $state->getQueueName()]) . "\n";
        $info .= t('Size (number of items): @num', ['@num' => $state->getNumberOfItems()]) . "\n";
        $info .= t('State level: @level', ['@level' => $state->getStateLevel()]) . "\n";
        $info .= '------------------------------------------------------' . "\n";
      }
    }
    return $info;
  }

  /**
   * Returns a short, user-readable status summary.
   *
   * @param $langcode
   *  (Optional) The desired language translation code of the summary.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *  A brief summary of the watcher's status information.
   */
  public function getShortReadableStatus($langcode = NULL) {
    if (!isset($langcode)) {
      $langcode = $this->current_langcode;
    }

    $state_info = [];
    $options = ['langcode' => $langcode];
    foreach ($this->getLookupResult() as $states) {
      foreach ($states as $state) {
        $args = ['@queue' => $state->getQueueName(), '@level' => $state->getStateLevel()];
        $state_info[] = t('@queue is at @level state', $args, $options);
      }
    }

    if (!empty($state_info)) {
      if ($this->foundProblems()) {
        return t('Problematic queues detected: @states.', ['@states' => implode(', ', $state_info)], $options);
      }
      else {
        return t('Detected queue states: @states.', ['@states' => implode(', ', $state_info)], $options);
      }
    }
    else {
      return t('There are currently no queues to watch.', [], $options);
    }
  }

  /**
   * Returns the list of known states of queues,
   * which are not exceeding any limits.
   *
   * @return QueueState[]
   */
  public function getSaneQueueStates() {
    return $this->lookup_result['sane'];
  }

  /**
   * Returns the list of known states of queues,
   * which have exceeded the warning limit,
   * but currently do not exceed the critical limit.
   *
   * @see ::getCriticalQueueStates()
   *
   * @return QueueState[]
   */
  public function getWarningQueueStates() {
    return $this->lookup_result['warning'];
  }

  /**
   * Returns the list of known states of queues,
   * which have exceeded the critical limit.
   *
   * The critical states are not found in the list of warning states,
   * since these ones are critical for now, and not a warning anymore.
   *
   * @see ::getWarningQueueStates()
   *
   * @return QueueState[]
   */
  public function getCriticalQueueStates() {
    return $this->lookup_result['critical'];
  }

  /**
   * Returns the list of known states of queues,
   * which are not defined in the Queue Watcher configuration.
   *
   * @return QueueState[]
   */
  public function getUndefinedQueueStates() {
    return $this->lookup_result['undefined'];
  }

  /**
   * Returns the list with queues, which are to be watched.
   *
   * @return array
   *  An array of defined queues including limits, keyed by queue name.
   *
   * @see queue_watcher.schema.yml section 'watch_queues'
   *  for possible queue definition keys.
   */
  public function getQueuesToWatch() {
    return $this->queues_to_watch;
  }

  /**
   * Returns a list of all recipient mail addresses,
   * which will be notified by calling ::report().
   *
   * @return array
   *  The list of recipient mail addresses as strings.
   */
  public function getRecipientsToReport() {
    return $this->recipients_to_report;
  }

  /**
   * Adds a further recipient address,
   * if not yet defined in the Queue Watcher configuration.
   *
   * @param string $mail
   *  A valid E-Mail address.
   */
  public function addRecipient($mail) {
    $this->recipients_to_report[$mail] = $mail;
  }


  /**
   * Logs the current status information.
   *
   * @param LoggerInterface $logger
   *  (Optional) A specific logger channel instance.
   *  By default, the Queue Watcher channel will be used.
   */
  public function logStatus(LoggerInterface $logger = NULL) {
    if (!isset($logger)) {
      $logger = $this->logger();
    }

    $info = $this->getShortReadableStatus();
    if (!empty($this->getCriticalQueueStates())) {
      $logger->critical($info);
    }
    elseif (!empty($this->getWarningQueueStates())) {
      $logger->warning($info);
    }
    else {
      $logger->info($info);
    }
  }

  /**
   * Sends a status mail to the given mail addresses.
   *
   * @param array $mail_addresses
   *  (Optional) The recipient addresses to send the status mail.
   *  By default, the configured and previously
   *  given recipient addresses will be used.
   *
   * @see ::addRecipient()
   * @see ::getRecipientsToReport()
   */
  public function mailStatus(array $mail_addresses = []) {
    if (empty($mail_addresses)) {
      $mail_addresses = $this->getRecipientsToReport();
    }

    if (!empty($mail_addresses)) {
      // TODO Find a way to determine appropriate translations.
      $langcode = $this->current_langcode;
      $overall = $this->foundProblems() ? t('Problematic') : t('No problems');
      if (!empty($this->getCriticalQueueStates())) {
        $overall = t('Critical');
      }
      $site = \Drupal::config('system.site')->get('name');

      $prepared_subject = t('@overall - Queue Watcher status report from @site',
        ['@overall' => $overall, '@site' => $site], ['langcode' => $langcode]);
      $prepared_info = nl2br($this->getReadableStatus($langcode));
      $params = [
        'prepared_subject' => $prepared_subject,
        'prepared_status_info' => $prepared_info,
        'watcher_instance' => $this,
      ];
      foreach ($mail_addresses as $mail_address) {
        $this->getMailManager()->mail('queue_watcher', 'status', $mail_address, $langcode, $params);
      }
    }
  }

  /**
   * Get the corresponding logger instance.
   *
   * @return LoggerInterface
   */
  protected function logger() {
    return $this->logger;
  }

  /**
   * Get the MailManagerInterface instance.
   *
   * @return MailManagerInterface
   */
  protected function getMailManager() {
    return $this->mail_manager;
  }

  /**
   * Initialize the list of queues to watch.
   */
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

  /**
   * Initialize the recipients to report.
   */
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

  /**
   * Initialize the lookup result structure.
   */
  protected function initLookupResult() {
    $this->lookup_result = ['sane' => [], 'warning' => [], 'critical' => [], 'undefined' => [],];
  }
}
