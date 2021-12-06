<?php

/**
 * @file
 * theme-settings.php
 *
 * Provides theme settings for the OpenEDU sub-theme.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter().
 */
function glenville_form_system_theme_settings_alter(&$form, FormStateInterface $form_state, $form_id = NULL) {

  // Default settings are defined in theme_get_setting() in includes/theme.inc
  if (isset($form_id)) {
    return;
  }

  $form['logo']['settings']['footer_logo_path'] = [
    '#type' => 'textfield',
    '#title' => t('Path to custom footer logo'),
    '#default_value' => theme_get_setting('footer_logo_path'),
  ];

}
