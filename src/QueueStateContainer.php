<?php

namespace Drupal\queue_watcher;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManager;

/**
 * A container class which holds several queue states.
 */
class QueueStateContainer {

  /**
   * The QueueFactory instance.
   *
   * @var Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * A list of known worker definitions, keyed by queue name.
   *
   * @var array
   */
  protected $workerDefinitions;

  /**
   * QueueStateContainer constructor.
   *
   * @param Drupal\Core\Queue\QueueFactory $queue_factory
   *   The QueueFactory instance.
   */
  public function __construct(QueueFactory $queue_factory, QueueWorkerManager $worker_manager) {
    $this->queueFactory = $queue_factory;
    $this->workerDefinitions = $worker_manager->getDefinitions();
  }

  /**
   * The queue states being hold by this container.
   *
   * @var QueueState[]
   */
  protected $states = [];

  /**
   * Refreshes the states for the currently known queues.
   *
   * @param QueueState $state
   *   When given, only the state of this queue will be refreshed.
   *
   * @return QueueStateContainer
   *   The state container itself.
   */
  public function refresh(QueueState $state = NULL) {
    $to_refresh = isset($state) ? [$state] : array_keys($this->workerDefinitions);

    $refreshed = [];
    foreach ($to_refresh as $queue_name) {

      $queue = $this->queueFactory->get($queue_name, FALSE);
      if (!$queue) {
        continue;
      }

      $num_items = (int) $queue->numberOfItems();

      if (isset($this->states[$queue_name])) {
        $this->states[$queue_name]->setNumberOfItems($num_items);
      }
      else {
        $this->states[$queue_name] = new QueueState($queue_name, $num_items);
      }
      $refreshed[$queue_name] = $this->states[$queue_name];
    }

    if (!isset($state)) {
      // There might be observed queues, which are empty now.
      // Manually refresh these states to be empty.
      foreach ($this->states as $queue_name => $state) {
        if (empty($refreshed[$queue_name])) {
          $state->setNumberOfItems(0);
          $refreshed[$queue_name] = $this->states[$queue_name];
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
   * This method always runs a full refresh,
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
   * Adds an empty queue state, if it isn't known yet.
   *
   * @param string $queue_name
   *   The name of the queue to track the state.
   */
  public function addEmptyState($queue_name) {
    if (!isset($this->states[$queue_name])) {
      $this->states[$queue_name] = new QueueState($queue_name, 0);
    }
  }

}
