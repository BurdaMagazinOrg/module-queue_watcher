<?php

namespace Drupal\queue_watcher;

/**
 * Represents the current state of a queue.
 */
class QueueState {
  protected $queueName;
  protected $numItems;
  protected $stateLevel = 'undefined';

  /**
   * QueueState constructor.
   */
  public function __construct($queue_name, $num_items) {
    $this->queueName = $queue_name;
    $this->numItems = $num_items;
  }

  /**
   * Get the machine name of the corresponding queue.
   *
   * @return string
   *   The queue machine name.
   */
  public function getQueueName() {
    return $this->queueName;
  }

  /**
   * Get the currently known number of items (size) of the queue.
   *
   * @return int
   *   The queue size.
   */
  public function getNumberOfItems() {
    return $this->numItems;
  }

  /**
   * Sets the number of items.
   *
   * This task is usually taken care of by the QueueStateContainer.
   *
   * @return QueueState
   *   The state object itself.
   */
  public function setNumberOfItems($num) {
    $this->numItems = $num;
    return $this;
  }

  /**
   * Get the queue state level.
   *
   * @return string
   *   Can be either 'undefined', 'sane', 'warning' or 'critical'.
   *   Queues which are not added to the watch list in the Queue Watcher config
   *   cannot have a defined queue state.
   */
  public function getStateLevel() {
    return $this->stateLevel;
  }

  /**
   * Sets the level of this state.
   *
   * This task is usually taken care of by the QueueWatcher.
   *
   * @param string $level
   *   The level of the state, can be 'sane', 'warning' or 'critical'.
   *
   * @return QueueState
   *   The state object itself.
   */
  public function setStateLevel($level) {
    $this->stateLevel = $level;
    return $this;
  }

  /**
   * Checks whether the current state of the queue exceeds the given limit.
   *
   * @param int $limit
   *   The given limit as integer.
   *
   * @return bool
   *   TRUE if limit is exceeded, FALSE otherwise.
   */
  public function exceeds($limit) {
    return ($this->getNumberOfItems() > $limit);
  }

}
