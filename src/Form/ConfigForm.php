<?php

/**
 * @file
 * Class for the Queue Watcher configuration form. 
 */

namespace Drupal\queue_watcher\Form;

use Drupal\Core\Form\FormBase;

class ConfigForm extends FormBase {
  public function getFormId() {
    return 'queue_watcher_config_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    
  }
}
