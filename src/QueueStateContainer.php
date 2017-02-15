<?php

namespace Drupal\queue_watcher;

/**
 * A container class which holds several queue states.
 */
class QueueStateContainer {
  /**
   * @var QueueState[] 
   */
  $states;

  public function __construct() {
    $this->refresh();
  }

  protected function query() {
    $query = \Drupal::database()
      ->select('queue', 'q')
      ->fields('q', ['name'])
      ->groupBy('name');
    $query->addExpression('COUNT(q.item_id)', 'num_items');
    return $query;
  }

  public function refresh() {
    $this->states = [];
  }

  /**
   * @return QueueState
   */
  public function getState($queue_name) {
    
  }

  /**
   * @return QueueState[]
   */
  public function getAllStates() {
    
  }
}
