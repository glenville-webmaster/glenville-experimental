<?php

namespace Drupal\glenville_ldap\EventSubscriber;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class GlenvilleLdapSubscriber implements EventSubscriberInterface {

  /**
   * Returns an array of event names this subscriber wants to listen to.
   *
   * The array keys are event names and the value can be:
   *
   *  * The method name to call (priority defaults to 0)
   *  * An array composed of the method name to call and the priority
   *  * An array of arrays composed of the method names to call and respective
   *    priorities, or 0 if unset
   *
   * For instance:
   *
   *  * array('eventName' => 'methodName')
   *  * array('eventName' => array('methodName', $priority))
   *  * array('eventName' => array(array('methodName1', $priority), array('methodName2')))
   *
   * @return array The event names to listen to
   */
  public static function getSubscribedEvents() {
    $events['kernel.request'] = ['onRequest', 28];
    return $events;
  }

  /**
   * Checks for specific user roles to access protected content.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The Event.
   */
  public function onRequest(Event $event) {
    $request = $event->getRequest();

    // Prevents node sub-tabs such as "edit", "revisions" from being redirected.
    if ($request->attributes->get('_route') !== 'entity.node.canonical') {
      return;
    }

    /** @var \Drupal\node\Entity\Node $node */
    $node = $request->attributes->get('node');
    // Only redirect a Protected Content content type.
    if ($node->getType() !== 'protected_content') {
      return;
    }

    $account = \Drupal::currentUser();
    $roles = $account->getRoles();

    // just allow admins.
    if (in_array('administrator', $roles) || $account->hasPermission('glenville protected content')) {
      return;
    }

    // Check for the proper roles.
    $visiblities = $node->get('field_protected_visibility')->getValue();
    foreach ($roles as $role) {
      foreach ($visiblities as $visiblity) {
        if ('glenville_' . $visiblity['value'] == $role) {
          // All good.
          return;
        }
      }
    }

    // Already Logged in, just throw a 403.
    if (!$account->isAnonymous()) {
      throw new AccessDeniedHttpException();
    }

    // Redirect with query.
    $query = $request->query->all();
    $query['destination'] = Url::fromRoute('<current>')->toString();
    $login_uri = Url::fromRoute('glenville_ldap.login_form', [], ['query' => $query])
      ->toString();
    $returnResponse = new TrustedRedirectResponse($login_uri, 302);
    $event->setResponse($returnResponse);
  }

}
