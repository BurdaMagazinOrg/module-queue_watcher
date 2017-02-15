<?php

namespace Drupal\queue_watcher;

/**
 * Represents the current state of a queue.
 */
class QueueState {
  protected $queue_name;
  protected $num_items;

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
}
