<?php

namespace Drupal\queue_watcher;

use \Psr\Log\LoggerInterface;
use \Drupal\Core\Mail\MailManagerInterface;
use \Drupal\Core\StringTranslation\TranslationInterface;

/**
 * The QueueWatcher class.
 */
class QueueWatcher {

  /**
   * The corresponding Queue Watcher configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * A QueueStateContainer instance.
   *
   * @var QueueStateContainer
   */
  protected $stateContainer;

  /**
   * The list of configured queues to watch.
   *
   * @var array
   */
  protected $queuesToWatch;

  /**
   * The list of configured recipients to report.
   *
   * @var array
   */
  protected $recipientsToReport;

  /**
   * The gathered result from ::lookup().
   *
   * @var array
   */
  protected $lookupResult;

  /**
   * The Drupal logger instance using the queue_watcher channel.
   *
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * The mail manager being used for sending mails.
   *
   * @var MailManagerInterface
   */
  protected $mailManager;

  /**
   * The currently used language code.
   *
   * @var string
   */
  protected $currentLangcode;

  /**
   * The translation manager for translating.
   *
   * @var TranslationInterface
   */
  protected $translationManager;

  /**
   * QueueWatcher constructor method.
   *
   * @param QueueStateContainer $state_container
   *   The QueueStateContainer instance.
   * @param MailManagerInterface $mail_manager
   *   The mail manager.
   */
  public function __construct(QueueStateContainer $state_container, MailManagerInterface $mail_manager, TranslationInterface $translation_manager) {
    $this->config = \Drupal::config('queue_watcher.config');
    $this->logger = \Drupal::logger('queue_watcher');
    $this->stateContainer = $state_container;
    $this->mailManager = $mail_manager;
    $this->translationManager = $translation_manager;
    $this->currentLangcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $this->initQueuesToWatch();
    $this->initRecipientsToReport();
    $this->initLookupResult();
  }

  /**
   * Get the Queue Watcher configuration.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The configuration object.
   */
  public function getConfig() {
    return $this->config;
  }

  /**
   * Get the QueueStateContainer.
   *
   * @return QueueStateContainer
   *   The state container.
   */
  public function getStateContainer() {
    return $this->stateContainer;
  }

  /**
   * Performs a lookup on the current queue states.
   */
  public function lookup() {
    $states = $this->getStateContainer()->getAllStates();
    foreach ($this->queuesToWatch as $queue_name => $settings) {
      if (empty($states[$queue_name])) {
        $this->getStateContainer()->addEmptyState($queue_name);
        $states[$queue_name] = $this->getStateContainer()->getState($queue_name);
      }
      $state = $states[$queue_name];
      $this->classifyStateLevel($state);
      unset($states[$queue_name]);
    }
    // Add the states of queues,
    // which are not added (yet) in the Queue Watcher configuration.
    foreach ($states as $queue_name => $not_configured) {
      $this->classifyStateLevel($not_configured);
    }
  }

  /**
   * Returns TRUE if the watcher found problems after a ::lookup().
   *
   * @return bool
   *   TRUE if the watcher found problems, FALSE otherwise.
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
   *   An array of QueueStates,
   *   which are keyed by 'sane', 'warning' and 'critical'.
   */
  public function getLookupResult() {
    return $this->lookupResult;
  }

  /**
   * Returns a user-readable summary of the current information the watcher has.
   *
   * @param string $langcode
   *   (Optional) The desired language translation code of the summary.
   *
   * @return string
   *   A summary of the watcher's status information.
   */
  public function getReadableStatus($langcode = NULL) {
    if (!isset($langcode)) {
      $langcode = $this->currentLangcode;
    }

    $info = '------------------------------------------------------' . "\n";
    if ($this->foundProblems()) {
      $info .= $this->t(".. The Queue Watcher has detected problematic queue states! ..") . "\n";
    }
    else {
      $info .= $this->t(".. The Queue Watcher hasn't found any problematic queue states. ..") . "\n";
    }
    $info .= '------------------------------------------------------' . "\n";
    foreach ($this->getLookupResult() as $states) {
      foreach ($states as $state) {
        $info .= $this->t('Queue: @queue', ['@queue' => $state->getQueueName()]) . "\n";
        $info .= $this->t('Size (number of items): @num', ['@num' => $state->getNumberOfItems()]) . "\n";
        $info .= $this->t('State level: @level', ['@level' => $state->getStateLevel()]) . "\n";
        $info .= '------------------------------------------------------' . "\n";
      }
    }
    return $info;
  }

  /**
   * Returns a short, user-readable status summary.
   *
   * @param string $langcode
   *   (Optional) The desired language translation code of the summary.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A brief summary of the watcher's status information.
   */
  public function getShortReadableStatus($langcode = NULL) {
    if (!isset($langcode)) {
      $langcode = $this->currentLangcode;
    }

    $state_info = [];
    $options = ['langcode' => $langcode];
    foreach ($this->getLookupResult() as $states) {
      foreach ($states as $state) {
        $args = ['@queue' => $state->getQueueName(), '@level' => $state->getStateLevel()];
        $state_info[] = $this->t('@queue is at @level state', $args, $options);
      }
    }

    if (!empty($state_info)) {
      if ($this->foundProblems()) {
        return $this->t('Problematic queues detected: @states.', ['@states' => implode(', ', $state_info)], $options);
      }
      else {
        return $this->t('Detected queue states: @states.', ['@states' => implode(', ', $state_info)], $options);
      }
    }
    else {
      return $this->t('There are currently no queues to watch.', [], $options);
    }
  }

  /**
   * Returns a list of known sane queue states.
   *
   * These known states are not exceeding any limits.
   *
   * @return QueueState[]
   *   The list of sane queue states.
   */
  public function getSaneQueueStates() {
    return $this->lookupResult['sane'];
  }

  /**
   * Returns a list of warning queue states.
   *
   * This list contains queue states,
   * which have exceeded the warning limit,
   * but currently do not exceed the critical limit.
   *
   * @see ::getCriticalQueueStates()
   *
   * @return QueueState[]
   *   The list of warning queue states.
   */
  public function getWarningQueueStates() {
    return $this->lookupResult['warning'];
  }

  /**
   * Returns a list of critical queue states.
   *
   * This is the list of known queue states,
   * which have exceeded the critical limit.
   *
   * The critical states are not found in the list of warning states,
   * since these ones are critical for now, and not a warning anymore.
   *
   * @see ::getWarningQueueStates()
   *
   * @return QueueState[]
   *   The list of critical queue states.
   */
  public function getCriticalQueueStates() {
    return $this->lookupResult['critical'];
  }

  /**
   * Returns a list of undefined queue states.
   *
   * This is the list of known queue states,
   * whose limits are not defined in the Queue Watcher configuration.
   *
   * @return QueueState[]
   *   The list of undefined queue states.
   */
  public function getUndefinedQueueStates() {
    return $this->lookupResult['undefined'];
  }

  /**
   * Returns the list with queues, which are to be watched.
   *
   * See the queue_watcher.schema.yml section 'watch_queues'
   * for possible queue definition keys.
   *
   * @return array
   *   An array of defined queues including limits, keyed by queue name.
   */
  public function getQueuesToWatch() {
    return $this->queuesToWatch;
  }

  /**
   * Returns a list of all recipient mail addresses.
   *
   * These recipients will be notified by calling ::report().
   *
   * @return array
   *   The list of recipient mail addresses as strings.
   */
  public function getRecipientsToReport() {
    return $this->recipientsToReport;
  }

  /**
   * Adds a further recipient address.
   *
   * The address will be added,
   * when it is not yet defined in the Queue Watcher configuration.
   *
   * @param string $mail
   *   A valid E-Mail address.
   */
  public function addRecipient($mail) {
    $this->recipientsToReport[$mail] = $mail;
  }

  /**
   * Logs the current status information.
   *
   * @param LoggerInterface $logger
   *   (Optional) A specific logger channel instance.
   *   By default, the Queue Watcher channel will be used.
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
   *   (Optional) The recipient addresses to send the status mail.
   *   By default, the configured and previously
   *   given recipient addresses will be used.
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
      $langcode = $this->currentLangcode;
      $overall = $this->foundProblems() ? $this->t('Problematic') : $this->t('No problems');
      if (!empty($this->getCriticalQueueStates())) {
        $overall = $this->t('Critical');
      }
      $site = \Drupal::config('system.site')->get('name');

      $prepared_subject = $this->t('@overall - Queue Watcher status report from @site',
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
   * Classifies the state level of a given queue state.
   */
  protected function classifyStateLevel(QueueState $state) {
    $queue_name = $state->getQueueName();
    $settings = !empty($this->queuesToWatch[$queue_name]) ?
      $this->queuesToWatch[$queue_name] : $this->getConfig()->get('default_queue_settings');
    $warning_limit = $settings['size_limit_warning'];
    $critical_limit = $settings['size_limit_critical'];

    if (is_numeric($critical_limit) && $state->exceeds($critical_limit)) {
      $state->setStateLevel('critical');
      $this->lookupResult['critical'][$queue_name] = $state;
      unset($this->lookupResult['warning'][$queue_name]);
      unset($this->lookupResult['sane'][$queue_name]);
    }
    elseif (is_numeric($warning_limit) && $state->exceeds($warning_limit)) {
      $state->setStateLevel('warning');
      unset($this->lookupResult['critical'][$queue_name]);
      $this->lookupResult['warning'][$queue_name] = $state;
      unset($this->lookupResult['sane'][$queue_name]);
    }
    elseif (is_numeric($critical_limit) || is_numeric($warning_limit)) {
      $state->setStateLevel('sane');
      unset($this->lookupResult['critical'][$queue_name]);
      unset($this->lookupResult['warning'][$queue_name]);
      $this->lookupResult['sane'][$queue_name] = $state;
    }
    else {
      $this->lookupResult['undefined'][$queue_name] = $state;
    }
  }

  /**
   * Get the corresponding logger instance.
   *
   * @return LoggerInterface
   *   The logger instance.
   */
  protected function logger() {
    return $this->logger;
  }

  /**
   * Get the MailManagerInterface instance.
   *
   * @return MailManagerInterface
   *   The mail manager instance.
   */
  protected function getMailManager() {
    return $this->mailManager;
  }

  /**
   * Translation helper function.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Translatable markup.
   */
  protected function t($string, array $args = [], array $options = []) {
    return $this->translationManager->translate($string, $args, $options);
  }

  /**
   * Initialize the list of queues to watch.
   */
  protected function initQueuesToWatch() {
    $to_watch = [];
    $default = $this->getConfig()->get('default_queue_settings');
    foreach ($this->getConfig()->get('watch_queues') as $defined) {
      if (!empty($defined['queue_name'])) {
        $name = $defined['queue_name'];
        $defined['size_limit_warning'] = !empty($defined['size_limit_warning']) ? $defined['size_limit_warning'] : $default['size_limit_warning'];
        $defined['size_limit_critical'] = !empty($defined['size_limit_critical']) ? $defined['size_limit_critical'] : $default['size_limit_critical'];
        $to_watch[$name] = $defined;
      }
    }
    $this->queuesToWatch = $to_watch;
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
    $this->recipientsToReport = $recipients;
  }

  /**
   * Initialize the lookup result structure.
   */
  protected function initLookupResult() {
    $this->lookupResult = [
      'sane' => [],
      'warning' => [],
      'critical' => [],
      'undefined' => [],
    ];
  }

}
