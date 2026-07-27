<?php

namespace Drupal\mcc_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Configures the public calendar and the monthly print sheet.
 *
 * Everything the office is likely to want to change about the calendar without
 * a developer lives here: how tight the cells are, whether the legend shows,
 * the standing footnote, and how the printed sheet handles a busy Sunday. The
 * colours and marker shapes are *not* here — those belong to the individual
 * Missions Category terms, so each category is editable in one obvious place.
 */
class CalendarSettingsForm extends ConfigFormBase {

  const SETTINGS = 'mcc_core.calendar.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mcc_core_calendar_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::SETTINGS);

    $form['screen'] = [
      '#type' => 'details',
      '#title' => $this->t('On the website'),
      '#open' => TRUE,
    ];
    $form['screen']['eyebrow'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Kicker'),
      '#description' => $this->t('Small line above the month name.'),
      '#default_value' => $config->get('eyebrow'),
      '#maxlength' => 64,
    ];
    $form['screen']['density'] = [
      '#type' => 'radios',
      '#title' => $this->t('Cell height'),
      '#options' => [
        'comfortable' => $this->t('Comfortable — taller cells, easier to scan'),
        'compact' => $this->t('Compact — shorter cells, more of the month on screen'),
      ],
      '#default_value' => $config->get('density'),
      '#description' => $this->t('Cells always grow to fit every event either way; this sets their minimum height.'),
    ];
    $form['screen']['show_legend'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the category legend'),
      '#default_value' => $config->get('show_legend'),
    ];
    $form['screen']['footnote'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Note below the calendar'),
      '#default_value' => $config->get('footnote'),
      '#rows' => 3,
    ];

    $form['print'] = [
      '#type' => 'details',
      '#title' => $this->t('On the printed sheet'),
      '#open' => TRUE,
      '#description' => $this->t('The printed calendar is one Letter page per month. <a href=":url" target="_blank">Preview this month’s sheet</a>.', [
        ':url' => Url::fromRoute('mcc_core.print_monthly')->toString(),
      ]),
    ];
    $form['print']['busy_day_style'] = [
      '#type' => 'radios',
      '#title' => $this->t('Days with a lot on'),
      '#options' => [
        'grouped' => $this->t('Grouped by time — print the time once per time slot, one line per event'),
        'runs' => $this->t('Condensed — one line per time slot, events separated by dots'),
        'digest' => $this->t('Weekly digest — lift the standing weekly schedule into a band under the header'),
      ],
      '#default_value' => $config->get('print.busy_day_style'),
      '#description' => $this->t('A Sunday morning carries seven standing events in a cell about an inch wide. This decides how they are laid out.'),
    ];
    $form['print']['busy_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Events on one day before that kicks in'),
      '#default_value' => $config->get('print.busy_threshold'),
      '#min' => 2,
      '#max' => 20,
      '#states' => [
        'invisible' => [':input[name="busy_day_style"]' => ['value' => 'digest']],
      ],
    ];
    $form['print']['show_adjacent_days'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show days from the months either side'),
      '#default_value' => $config->get('print.show_adjacent_days'),
    ];
    $form['print']['tagline'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tagline beside the month name'),
      '#default_value' => $config->get('print.tagline'),
      '#maxlength' => 128,
    ];
    $form['print']['footer_left'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Footer, left'),
      '#default_value' => $config->get('print.footer_left'),
      '#maxlength' => 160,
    ];
    $form['print']['footer_right'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Footer, right'),
      '#default_value' => $config->get('print.footer_right'),
      '#maxlength' => 160,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(self::SETTINGS)
      ->set('eyebrow', $form_state->getValue('eyebrow'))
      ->set('density', $form_state->getValue('density'))
      ->set('show_legend', (bool) $form_state->getValue('show_legend'))
      ->set('footnote', $form_state->getValue('footnote'))
      ->set('print.busy_day_style', $form_state->getValue('busy_day_style'))
      ->set('print.busy_threshold', (int) $form_state->getValue('busy_threshold'))
      ->set('print.show_adjacent_days', (bool) $form_state->getValue('show_adjacent_days'))
      ->set('print.tagline', $form_state->getValue('tagline'))
      ->set('print.footer_left', $form_state->getValue('footer_left'))
      ->set('print.footer_right', $form_state->getValue('footer_right'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
