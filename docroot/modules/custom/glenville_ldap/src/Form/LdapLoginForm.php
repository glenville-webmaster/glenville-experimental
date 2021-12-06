<?php

namespace Drupal\glenville_ldap\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LdapLoginForm extends FormBase {

  const GLENVILLE_LDAP_PRIMARY = '129.71.82.1';

  const GLENVILLE_LDAP_SECONDARY = '129.71.82.5';

  const GLENVILLE_LDAP_BASE = 'DC=glenville,DC=edu';

  const GLENVILLE_LDAP_DOMAIN = '@glenville.edu';

  const GLENVILLE_LDAP_USERS = [
    'Drupal-Faculty' => 26,
    'Drupal-Staff' => 21,
    'Drupal-Student' => 16,
  ];

  /**
   * @var \Drupal\Core\Session\AccountProxyInterface $account
   */
  protected $currentUser;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Class constructor.
   */
  public function __construct(AccountProxyInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'glenville_ldap_login_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Check if already logged in.
    if ($this->currentUser->isAuthenticated()) {
      return [
        '#markup' => $this->t("You are already logged in. Click @front to go back to the site.", [
          '@front' => Link::createFromRoute($this->t("here"), "<front>")->toString()
        ])];
    }

    $form['username'] = [
      '#title' => $this->t('Username'),
      '#type' => 'textfield',
      '#required' => TRUE,
    ];

    $form['password'] = [
      '#title' => $this->t('Password'),
      '#type' => 'password',
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Log in')];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $user = $form_state->getValue('username');
    $pass = $form_state->getValue('password');

    if ($info = $this->getUserInfo($user, $pass)) {
      $form_state->setValue('ldap_info', $info);
    }
    else {
      $form_state->setError($form, $this->t("Unrecognized username or password."));
    }
  }

  /**
   * Test authentication and return the user role.
   *
   * @param $user
   *   The Username.
   * @param $pass
   *   The password.
   *
   * @return bool|array
   *   The info array of the user or FALSE.
   */
  protected function getUserInfo($user, $pass) {

    // TRY Both servers.
    if (!$ldap = ldap_connect(self::GLENVILLE_LDAP_PRIMARY)) {
      if (!$ldap = ldap_connect(self::GLENVILLE_LDAP_SECONDARY)) {
        return FALSE;
      }
    }

    // LDAP3 supports UTF-8 encoding so can support extended character sets.
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    // necessary for AD because AD sends back referrals and not just what you ask for.
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

    // Authenticate.
    if (!$ldapbind = ldap_bind($ldap, $user . self::GLENVILLE_LDAP_DOMAIN, $pass)) {
      return FALSE;
    }

    $attributes = [
      "memberOf",
      "givenname"
    ];

    if ($results = ldap_search($ldap, self::GLENVILLE_LDAP_BASE, "(samaccountname=$user)", $attributes)) {
      if ($entries = ldap_get_entries($ldap, $results)) {
        $info = [];

        // Save for login message.
        if (isset($entries[0]['givenname'][0])) {
          $info['given_name'] = $entries[0]['givenname'][0];
        }

        if (isset($entries[0]['memberof'])) {
          $dn_list = explode(",", $entries[0]['memberof'][0]);
          foreach ($dn_list as $entry) {
            foreach (self::GLENVILLE_LDAP_USERS as $role => $uid) {
              if (strpos($entry, $role) !== FALSE) {
                $info['role'] = $role;
                return $info;
              }
            }
          }
        }
      }
    }

    return FALSE;
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $info = $form_state->getValue('ldap_info');
    $role = $info['role'];

    if (isset(self::GLENVILLE_LDAP_USERS[$role])) {
      if ($account = $this->entityTypeManager->getStorage('user')
        ->load(self::GLENVILLE_LDAP_USERS[$role])) {

        // A destination was set, probably on an exception controller,
        if ($this->getRequest()->request->has('destination')) {
          $this->getRequest()->query->set('destination', $this->getRequest()->request->get('destination'));
        }

        if (isset($info['given_name'])) {
          drupal_set_message($this->t('Login Successful! Welcome back, @name.', ['@name' => $info['given_name']]));
        }
        else {
          drupal_set_message($this->t('Login Successful!'));
        }

        user_login_finalize($account);
        return;
      }
    }

    drupal_set_message($this->t("There was an error logging in, please try again."), 'error');
  }
}
