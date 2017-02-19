<?php

namespace Drupal\queue_watcher\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\StatusMessages;
use Drupal\Component\Utility\Html;

/**
 * Class for the Queue Watcher configuration form.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * Get the form id.
   */
  public function getFormId() {
    return 'queue_watcher_config_form';
  }

  /**
   * Get the editable config names.
   */
  protected function getEditableConfigNames() {
    return ['queue_watcher.config'];
  }

  /**
   * The Form builder implementation.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('queue_watcher.config');

    $form['targets'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Target addresses to notify'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#tree' => FALSE,
      '#weight' => 10,
    ];

    $form['targets']['use_logger'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Write occurrences into the system log.'),
      '#default_value' => $config->get('use_logger'),
      '#weight' => 10,
    ];

    $form['targets']['use_site_mail'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send notification mail to website email.'),
      '#default_value' => $config->get('use_site_mail'),
      '#weight' => 20,
    ];

    $form['targets']['use_admin_mail'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send notification mail to website administrator (User with id 1).'),
      '#default_value' => $config->get('use_admin_mail'),
      '#weight' => 30,
    ];

    $form['targets']['mail_recipients'] = [
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('Mail recipients to send notifications about size exceedance.'),
      '#description' => $this->t('Enter multiple mail addresses separated by comma, e.g. <strong>one@two.com, three@four.com</strong>.'),
      '#default_value' => $config->get('mail_recipients'),
      '#weight' => 50,
    ];

    $form['watch_queues'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Queues to watch'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#tree' => TRUE,
      '#weight' => 20,
    ];

    $num_queue_items = count($config->get('watch_queues'));
    $form['watch_queues']['placeholder_for_new_queue_item'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'placeholder-for-new-queue-item'],
      '#weight' => $num_queue_items * 100 - 10,
    ];
    $form['watch_queues']['add'] = [
      '#tree' => FALSE,
      '#type' => 'button',
      '#name' => 'add_new_queue_item',
      '#value' => $this->t('Add another item'),
      '#weight' => $num_queue_items * 100,
      '#ajax' => [
        'callback' => [$this, 'addNewQueueItem'],
        'wrapper' => 'placeholder-for-new-queue-item',
        'effect' => 'fade',
        'method' => 'before',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Loading settings...'),
        ],
      ],
    ];

    // Reset the index counter.
    $form_state->setValue('watch_queues_index', 1);

    foreach ($config->get('watch_queues') as $queue_to_watch) {
      $this->addQueueItem($form, $form_state, $queue_to_watch);
    }

    $form['undefined_queue_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default settings for queues missing in the watch list'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#tree' => FALSE,
      '#weight' => 30,
    ];
    $default_queue_settings = $config->get('default_queue_settings');
    $form['undefined_queue_settings']['size_limit_warning'] = [
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('The default size limit as a valid, but undesired number of items'),
      '#default_value' => $default_queue_settings['size_limit_warning'],
      '#description' => $this->t("Leave it empty if you don't have an undesired limit. May be useful if you want to have a buffer for preparing performance optimizations. Writes a warning in the log (if writing into system log is activated above)."),
      '#weight' => 10,
    ];
    $form['undefined_queue_settings']['size_limit_critical'] = [
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('The default size limit as a critical, maximum allowed number of items'),
      '#default_value' => $default_queue_settings['size_limit_critical'],
      '#description' => $this->t("Leave it empty if you don't have a critical limit. Writes an error in the log (if writing into system log is activated above)."),
      '#weight' => 20,
    ];
    $form['undefined_queue_settings']['notify_undefined'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Report queues as problematic, whose states are not yet defined by this configuration.'),
      '#description' => $this->t('When there is no limit specified regarding a warning or a critical state, the given queue will be assigned with an undefined state level. Queues always have a defined state, when the above fields for default size limits are not empty.'),
      '#default_value' => $config->get('notify_undefined'),
      '#weight' => 30,
    ];

    $form['actions']['#weight'] = 100;

    return $form;
  }

  /**
   * Submit handler callback for the Queue Watcher config form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('queue_watcher.config');

    $values = $form_state->getValues();

    $config->set('use_logger', (bool) $values['use_logger']);
    $config->set('use_site_mail', (bool) $values['use_site_mail']);
    $config->set('use_admin_mail', (bool) $values['use_admin_mail']);
    $config->set('notify_undefined', (bool) $values['notify_undefined']);
    $config->set('mail_recipients', $values['mail_recipients']);

    // Default queue settings.
    $default_queue_settings = [
      'size_limit_warning' => $values['size_limit_warning'],
      'size_limit_critical' => $values['size_limit_critical'],
    ];
    $config->set('default_queue_settings', $default_queue_settings);

    foreach ($values['watch_queues'] as $index => $item) {
      // Remove marked items.
      if ($item['to_remove']) {
        unset($values['watch_queues'][$index]);
      }
      else {
        // Filter out unnecessary information.
        unset($values['watch_queues'][$index]['to_remove']);
        unset($values['watch_queues'][$index]['remove']);
      }
    }

    $config->set('watch_queues', $values['watch_queues']);

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Helper function to add a new queue item to the form.
   */
  public function addNewQueueItem(array &$form, FormStateInterface $form_state) {
    $new = [
      'queue_name' => '',
      'size_limit_warning' => '',
      'size_limit_critical' => '',
    ];

    // Synchronize the state of the given form values with the config object.
    $config = $this->config('queue_watcher.config');
    $queues = $form_state->getValue('watch_queues', []);
    $queues[] = $new;
    $config->set('watch_queues', $queues);

    $this->addQueueItem($form, $form_state, $new);
    $form  = $this->rebuild($form_state, $form);
    $i = $form_state->getValue('watch_queues_index');
    return $form['watch_queues'][$i];
  }

  /**
   * Helper function to add another queue item to the form.
   */
  protected function addQueueItem(&$form, $form_state, $queue_to_watch) {
    $i = $form_state->getValue('watch_queues_index', 1);
    // Get an index which isn't being used yet.
    while (TRUE) {
      if (empty($form['watch_queues'][$i])) {
        break;
      }
      $i++;
    }
    $form_state->setValue('watch_queues_index', $i);

    // Is this item marked to be removed?
    $queues = $form_state->getValue('watch_queues', []);
    $to_remove = !empty($queues[$i]['to_remove']) ? $queues[$i]['to_remove'] : 0;

    $id = Html::getUniqueId('edit-queue-item-' . $i);
    $form['watch_queues'][$i] = [
      '#type' => 'fieldset',
      '#title' => $this->t('#@num Queue to watch', ['@num' => $i]),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#tree' => TRUE,
      '#weight' => $i * 10,
      '#attributes' => ['id' => $id],
    ];
    $form['watch_queues'][$i]['queue_name'] = [
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('Queue machine name'),
      '#default_value' => $queue_to_watch['queue_name'],
      '#required' => $to_remove ? FALSE : TRUE,
      '#weight' => 10,
    ];
    $form['watch_queues'][$i]['size_limit_warning'] = [
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('The size limit as a valid, but undesired number of items'),
      '#default_value' => $queue_to_watch['size_limit_warning'],
      '#description' => $this->t("Leave it empty if you don't have an undesired limit. May be useful if you want to have a buffer for preparing performance optimisations. Writes a warning in the log (if writing into system log is activated above)."),
      '#weight' => 20,
    ];
    $form['watch_queues'][$i]['size_limit_critical'] = [
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('The size limit as a critical, maximum allowed number of items'),
      '#default_value' => $queue_to_watch['size_limit_critical'],
      '#description' => $this->t("Leave it empty if you don\'t have a critical limit. Writes an error in the log (if writing into system log is activated above)."),
      '#weight' => 30,
    ];

    // Find out whether this item is marked to be removed.
    $form['watch_queues'][$i]['to_remove'] = [
      '#type' => 'value',
      '#value' => $to_remove,
    ];
    $form['watch_queues'][$i]['remove'] = [
      '#type' => 'button',
      '#name' => 'remove_queue_item_' . $i,
      '#value' => $this->t('Remove this item'),
      '#ajax' => [
        'callback' => [$this, 'removeQueueItem'],
        'wrapper' => $id,
        'effect' => 'fade',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Removing...'),
        ],
      ],
      '#weight' => 100,
    ];

    return $form['watch_queues'][$i];
  }

  /**
   * Marks a queue item to be removed from the form.
   */
  public function removeQueueItem(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $i = $trigger['#parents'][1];

    $queues = $form_state->getValue('watch_queues', []);
    $queues[$i]['to_remove'] = 1;
    $form_state->setValue('watch_queues', $queues);
    $this->rebuild($form_state, $form);

    drupal_set_message($this->t('Item will be removed permanently when configuration is saved.'));
    return StatusMessages::renderMessages(NULL);
  }

  /**
   * Helper function to rebuild the form when necessary.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array &$old_form
   *   The old form build.
   *
   * @return array
   *   The newly built form.
   */
  protected function rebuild(FormStateInterface $form_state, array &$old_form) {
    $form_state->setRebuild();
    $form_builder = \Drupal::getContainer()->get('form_builder');
    $form = $form_builder->rebuildForm($this->getFormId(), $form_state, $old_form);
    return $form;
  }

}
