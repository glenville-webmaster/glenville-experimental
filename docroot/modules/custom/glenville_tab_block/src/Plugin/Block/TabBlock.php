<?php

namespace Drupal\glenville_tab_block\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a block with a custom nav tabs in programs.
 *
 * @Block(
 *   id = "glenville_tab_block",
 *   admin_label = @Translation("Tab block"),
 * )
 */
class TabBlock extends BlockBase implements BlockPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();
    $form['tabs'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Tab Regions'),
      '#description' => $this->t("key / value pairs of jQuery selector and Titles separated by a pipe on a new line each."),
      '#default_value' => isset($config['tabs']) ? $this->allowedValuesString($config['tabs']) : '',
    ];

    $form['sticky'] = array(
      '#title' => t('Set Sticky?'),
      '#type' => 'checkbox',
      '#default_value' => isset($config['sticky']) ? $config['sticky'] : FALSE,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    $tabs = $form_state->getValue('tabs');

    $values = [];

    $list = explode("\n", $tabs);
    $list = array_map('trim', $list);
    $list = array_filter($list, 'strlen');

    foreach ($list as $position => $text) {
      // Check for an explicit key.
      $matches = [];
      if (preg_match('/(.*)\|(.*)/', $text, $matches)) {
        // Trim key and value to avoid unwanted spaces issues.
        $key = trim($matches[1]);
        $value = trim($matches[2]);
        $values[$key] = $value;
      }
    }

    if (empty($values)) {
      $form_state->setErrorByName('tabs', $this->t("Invalid valus for tabs."));
    }
    else {
      $form_state->setValue('tabs', $values);
    }
  }

  /**
   * Generates a string representation of an array of 'allowed values'.
   *
   * This string format is suitable for edition in a textarea.
   *
   * @param array $values
   *   An array of values, where array keys are values and array values are
   *   labels.
   *
   * @return string
   *   The string representation of the $values array:
   *    - Values are separated by a carriage return.
   *    - Each value is in the format "value|label" or "value".
   */
  protected function allowedValuesString($values) {
    $lines = [];
    foreach ($values as $key => $value) {
      $lines[] = "$key|$value";
    }
    return implode("\n", $lines);
  }


  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->setConfigurationValue('tabs', $form_state->getValue('tabs'));
    $this->setConfigurationValue('sticky', $form_state->getValue('sticky'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    if (empty($config['tabs'])) {
      return [];
    }

    return [
      '#theme' => 'tabs-program',
      '#items' => $config['tabs'],
      '#sticky' => $config['sticky'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }
}
