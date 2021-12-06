<?php

namespace Drupal\glenville_core\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class ConfigImportSubscriber.
 *
 * @see: https://www.drupal.org/project/panelizer/issues/2756151#comment-12588474
 *
 * @package Drupal\panelizer\EventSubscriber
 */
class ConfigImportSubscriber implements EventSubscriberInterface {

  /**
   * The Key Value Expirable Factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  protected $keyValueExpirable;

  /**
   * ConfigImportSubscriber constructor.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $key_value_expirable
   */
  public function __construct(KeyValueExpirableFactoryInterface $key_value_expirable) {
    $this->keyValueExpirable = $key_value_expirable;
  }

  /**
   * Returns the config import event to subscribe to.
   *
   * @return array The event names to listen to
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::IMPORT][] = ['onConfigImport'];
    return $events;
  }

  /**
   * Listener for the Config import event.
   *
   * @param \Drupal\Core\Config\ConfigImporterEvent $event
   */
  public function onConfigImport(ConfigImporterEvent $event) {
    // Flush panels key-value storage.
    $key_value = $this->keyValueExpirable->get('user.shared_tempstore.panelizer.wizard');
    $key_value->deleteAll();
  }

}
