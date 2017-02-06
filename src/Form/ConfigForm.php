<?php

/**
 * @file
 * Class for the Queue Watcher configuration form. 
 */

namespace Drupal\queue_watcher\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ConfigForm extends FormBase {
  public function getFormId() {
    return 'queue_watcher_config_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
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
      '#title' => $this->t('Write occurences into the system log.'),
      '#value' => $config->get('use_logger'),
      '#weight' => 10,
    ];

    $form['targets']['use_site_mail'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send notification mail to website email.'),
      '#value' => $config->get('use_site_mail'),
      '#weight' => 20,
    ];

    $form['targets']['use_admin_mail'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send notification mail to website administrator (User with id 1).'),
      '#value' => $config->get('use_admin_mail'),
      '#weight' => 30,
    ];

    $form['targets']['mail_recipients'] = [
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('Mail recipients to send notifications about size exceedance.'),
      '#description' => $this->t('Enter multiple mail addresses separated by comma, e.g. <strong>one@two.com, three@four.com</strong>.'),
      '#value' => $config->get('mail_recipients'),
      '#weight' => 40,
    ];

    $form['watch_queues'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Target addresses to notify'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#tree' => TRUE,
      '#weight' => 20,
    ];

    $i = 0;
    foreach ($config->get('watch_queues') as $queue_to_watch) {
      $form['watch_queues'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('#@num Queue to watch', ['@num' => $i]),
        '#collapsible' => FALSE,
        '#collapsed' => FALSE,
        '#tree' => TRUE,
        '#weight' => $i * 10,
      ];
      $form['watch_queues'][$i]['queue_name'] = [
        '#type' => 'textfield',
        '#maxlength' => 255,
        '#title' => $this->t('Queue machine name'),
        '#value' => $queue_to_watch['queue_name'],
      ];
      $form['watch_queues'][$i]['size_limit_warning'] = [
        '#type' => 'textfield',
        '#maxlength' => 255,
        '#title' => $this->t('The size limit as a valid, but undesired number of items'),
        '#value' => $queue_to_watch['size_limit_warning'],
      ];
      $form['watch_queues'][$i]['size_limit_critical'] = [
        '#type' => 'textfield',
        '#maxlength' => 255,
        '#title' => $this->t('The size limit as a critical, maximum allowed number of items'),
        '#value' => $queue_to_watch['size_limit_critical'],
      ];
      $i++;
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    
  }
}
