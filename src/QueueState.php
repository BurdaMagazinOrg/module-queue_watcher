<?php

namespace Drupal\queue_watcher;

/**
 * Represents the current state of a queue.
 */
class QueueState {
  protected $queue_name;
  protected $num_items;
  protected $state_level = 'undefined';

  public function __construct($queue_name, $num_items) {
    $this->queue_name = $queue_name;
    $this->num_items = $num_items;
  }

  public function getQueueName() {
    return $this->queue_name;
  }

  public function getNumberOfItems() {
    return $this->num_items;
  }

  /**
   * Sets the number of items.
   *
   * This task is usually taken care of by the QueueStateContainer.
   *
   * @return QueueState
   */
  public function setNumberOfItems($num) {
    $this->num_items = $num;
    return $this;
  }

  public function getStateLevel() {
    return $this->state_level;
  }

  /**
   * Sets the level of this state.
   *
   * This task is usually taken care of by the QueueWatcher.
   *
   * @param string $level
   *   The level of the state, can be 'sane', 'warning' or 'critical'.
   */
  public function setStateLevel($level) {
    $this->state_level = $level;
  }

  /**
   * Checks whether the current state of the queue
   * exceeds the given limit.
   *
   * @param int $limit
   *  The given limit as integer.
   *
   * @return boolean
   *  TRUE if limit is exceeded, FALSE otherwise.
   */
  public function exceeds($limit) {
    return ($this->getNumberOfItems() > $limit);
  }
}
