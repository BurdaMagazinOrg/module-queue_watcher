<?php

namespace Drupal\queue_watcher;

/**
 * A container class which holds several queue states.
 */
class QueueStateContainer {
  /**
   * The queue states being hold by this container.
   *
   * @var QueueState[]
   */
  protected $states;

  /**
   * Re-fetches the states for the currently known queues.
   *
   * @param QueueState $state
   *   When given, only the state of this queue will be refreshed.
   *
   * @return QueueStateContainer
   *   The state container itself.
   */
  public function refresh(QueueState $state = NULL) {
    $query = $this->query();
    if (isset($state)) {
      $name = $state->getQueueName();
      $query->where('q.name = :name', [':name' => $name]);
    }

    $rows = $query->execute()->fetchAllAssoc('name');
    $fetched_states = [];
    foreach ($rows as $queue_name => $row) {

      if (isset($this->states[$queue_name])) {
        $this->states[$queue_name]->setNumberOfItems($row->num_items);
      }
      else {
        $this->states[$queue_name] = new QueueState($queue_name, $row->num_items);
      }
      $fetched_states[$queue_name] = $this->states[$queue_name];
    }

    if (!isset($state) || empty($rows)) {
      // There might be observed queues, which are empty now.
      foreach ($this->states as $queue_name => $state) {
        if (empty($fetched_states[$queue_name])) {
          $state->setNumberOfItems(0);
        }
      }
    }

    return $this;
  }

  /**
   * Get the currently known state of a given queue.
   *
   * If you always want the newest state fetched from the database,
   * you might want to run ::refresh() before.
   *
   * @return QueueState
   *   The known state of the given queue.
   */
  public function getState($queue_name) {
    if (!isset($this->states[$queue_name])) {
      $this->refresh(new QueueState($queue_name, 0));
    }

    return $this->states[$queue_name];
  }

  /**
   * Get all known queue states.
   *
   * This method always runs a query on the database,
   * while ::getState() can use in-memory caching once a state has been fetched.
   *
   * @return QueueState[]
   *   An array of queue states, keyed by queue names.
   */
  public function getAllStates() {
    // No in-memory caching here,
    // because we don't know the currently active queues yet.
    $this->refresh();

    return $this->states;
  }

  /**
   * Adds an empty queue state, if isn't known yet.
   *
   * @param string $queue_name
   *   The name of the queue to track the state.
   */
  public function addEmptyState($queue_name) {
    if (!isset($this->states[$queue_name])) {
      $this->states[$queue_name] = new QueueState($queue_name, 0);
    }
  }

  /**
   * Helper function to return a base query on the queue table.
   */
  protected function query() {
    $query = \Drupal::database()
      ->select('queue', 'q')
      ->fields('q', ['name'])
      ->groupBy('name');
    $query->addExpression('COUNT(q.item_id)', 'num_items');
    return $query;
  }

}
