<?php

/**
 * @file
 * Theme settings form for mcc_theme.
 *
 * Every line of text in the footer has to be changeable without a deploy. Most
 * of it already was — the link labels are menu links, the column headings are
 * the menus' own labels, and the signup field and button come from the
 * newsletter webform. What was left were six strings baked into
 * mcc_theme_preprocess_page(); they live here instead, at
 * /admin/appearance/settings/mcc_theme.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function mcc_theme_form_system_theme_settings_alter(array &$form, FormStateInterface $form_state): void {
  $form['mcc_footer'] = [
    '#type' => 'details',
    '#title' => t('Footer'),
    '#open' => TRUE,
    '#weight' => -10,
  ];

  $form['mcc_footer']['footer_wordmark'] = [
    '#type' => 'textarea',
    '#title' => t('Brand wordmark'),
    '#description' => t('The church name beside the logo mark. One line per line — it is set two lines deep in the design.'),
    '#rows' => 2,
    '#default_value' => theme_get_setting('footer_wordmark', 'mcc_theme') ?? MCC_FOOTER_WORDMARK,
  ];

  $form['mcc_footer']['footer_tagline'] = [
    '#type' => 'textfield',
    '#title' => t('Tagline'),
    '#description' => t('The italic line under the logo chip.'),
    '#default_value' => theme_get_setting('footer_tagline', 'mcc_theme') ?? MCC_FOOTER_TAGLINE,
  ];

  $form['mcc_footer']['footer_newsletter_heading'] = [
    '#type' => 'textfield',
    '#title' => t('Newsletter heading'),
    '#default_value' => theme_get_setting('footer_newsletter_heading', 'mcc_theme') ?? MCC_FOOTER_NEWSLETTER_HEADING,
  ];

  $form['mcc_footer']['footer_newsletter_description'] = [
    '#type' => 'textfield',
    '#title' => t('Newsletter supporting line'),
    '#maxlength' => 255,
    '#default_value' => theme_get_setting('footer_newsletter_description', 'mcc_theme') ?? MCC_FOOTER_NEWSLETTER_DESCRIPTION,
  ];

  $form['mcc_footer']['footer_credit_line'] = [
    '#type' => 'textfield',
    '#title' => t('Legal line (left)'),
    '#maxlength' => 255,
    '#description' => t('Use [year] where the current year should go, so it never goes stale.'),
    '#default_value' => theme_get_setting('footer_credit_line', 'mcc_theme') ?? MCC_FOOTER_CREDIT_LINE,
  ];

  $form['mcc_footer']['footer_contact_line'] = [
    '#type' => 'textfield',
    '#title' => t('Legal line (right)'),
    '#maxlength' => 255,
    '#description' => t('The address and phone repeated at the very bottom. Leave empty to hide it.'),
    '#default_value' => theme_get_setting('footer_contact_line', 'mcc_theme') ?? MCC_FOOTER_CONTACT_LINE,
  ];

  $form['mcc_footer']['mcc_footer_help'] = [
    '#type' => 'item',
    '#markup' => t('The footer link columns are menus: their headings are the <a href=":menus">menu names</a>, and their links are the menu links. The signup field and button labels are on the <a href=":webform">newsletter webform</a>.', [
      ':menus' => '/admin/structure/menu',
      ':webform' => '/admin/structure/webform/manage/newsletter_email_signup',
    ]),
  ];
}
