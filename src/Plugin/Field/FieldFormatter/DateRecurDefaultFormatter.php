<?php

namespace Drupal\date_recur\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;
use Drupal\datetime_range\Plugin\Field\FieldFormatter\DateRangeDefaultFormatter;

/**
 * Plugin implementation of the 'date_recur_default_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "date_recur_default_formatter",
 *   label = @Translation("Date recur default formatter"),
 *   field_types = {
 *     "date_recur"
 *   }
 * )
 */
class DateRecurDefaultFormatter extends DateRangeDefaultFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      // Whether the human readable string should be shown.
      'show_rrule' => TRUE,
      // Show number of occurrences.
      'show_next' => 5,
      // Whether number of occurrences should be per item or in total.
      'count_per_item' => TRUE,
      // Date format for occurrences.
      // @todo add dependencies.
      'occurrence_format_type' => 'medium',
      // Date format for end date, if same day as start date.
      'same_end_date_format_type' => 'medium',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['show_rrule'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show repeat rule'),
      '#default_value' => $this->getSetting('show_rrule'),
    ];

    $form['occurrences'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline']],
      '#tree' => FALSE,
    ];

    $form['occurrences']['show_next'] = [
      '#field_prefix' => $this->t('Show'),
      '#field_suffix' => $this->t('occurrences'),
      '#type' => 'number',
      '#min' => 0,
      '#default_value' => $this->getSetting('show_next'),
      '#attributes' => ['size' => 4],
      '#element_validate' => [[static::class, 'validateSettingsShowNext']],
    ];

    $form['occurrences']['count_per_item'] = [
      '#type' => 'select',
      '#options' => [
        'per_item' => $this->t('per field item'),
        'all_items' => $this->t('across all field items'),
      ],
      '#default_value' => $this->getSetting('count_per_item') ? 'per_item' : 'all_items',
      '#element_validate' => [[static::class, 'validateSettingsCountPerItem']],
    ];

    $form['occurrence_format_type'] = $form['format_type'];
    $form['occurrence_format_type']['#title'] = $this->t('Date format for occurrences');
    $form['occurrence_format_type']['#default_value'] = $this->getSetting('occurrence_format_type');
    $form['same_end_date_format_type'] = $form['format_type'];
    $form['same_end_date_format_type']['#title'] = $this->t('End date format for occurrences (if same day as start date)');
    $form['same_end_date_format_type']['#default_value'] = $this->getSetting('same_end_date_format_type');
    return $form;
  }

  /**
   * Validation callback for count_per_item.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function validateSettingsCountPerItem(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $countPerItem = $element['#value'] == 'per_item';
    $arrayParents = array_slice($element['#array_parents'], 0, -2);
    $formatterForm = NestedArray::getValue($complete_form, $arrayParents);
    $parents = $formatterForm['#parents'];
    $parents[] = 'count_per_item';
    $form_state->setValue($parents, $countPerItem);
  }

  /**
   * Validation callback for show_next.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function validateSettingsShowNext(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $arrayParents = array_slice($element['#array_parents'], 0, -2);
    $formatterForm = NestedArray::getValue($complete_form, $arrayParents);
    $parents = $formatterForm['#parents'];
    $parents[] = 'show_next';
    $form_state->setValue($parents, $element['#value']);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    if ($this->getSetting('show_rrule')) {
      $summary[] = $this->t('Showing repeat rule');
    }

    $countPerItem = $this->getSetting('count_per_item');
    $showOccurencesCount = $this->getSetting('show_next');
    if ($showOccurencesCount > 0) {
      $summary[] = $this->formatPlural(
        $showOccurencesCount,
        'Show maximum of @count occurrence @per',
        'Show maximum of @count occurrences @per',
        ['@per' => $countPerItem ? $this->t('per field item') : $this->t('across all field items')]
      );
    }

    $date = new DrupalDateTime();
    $date->_dateRecurIsOccurrence = TRUE;
    $summary[] = $this->t('Occurrence format: @display', [
      '@display' => $this->formatDate($date),
    ]);
    return $summary;
  }

  protected function buildDateRangeValue(DrupalDateTime $start_date, DrupalDateTime $end_date, $isOccurrence = FALSE) {
    if ($isOccurrence) {
      $start_date->_dateRecurIsOccurrence = $end_date->_dateRecurIsOccurrence = TRUE;
    }
    if ($start_date->format('Ymd') == $end_date->format('Ymd')) {
      $end_date->_same_end_date = TRUE;
    }
    if ($start_date->format('U') !== $end_date->format('U')) {
      $element = [
        'start_date' => $this->buildDateWithIsoAttribute($start_date),
        'separator' => ['#plain_text' => ' ' . $this->getSetting('separator') . ' '],
        'end_date' => $this->buildDateWithIsoAttribute($end_date),
      ];
    }
    else {
      $element = $this->buildDateWithIsoAttribute($start_date);
    }
    return $element;
  }

  protected function formatDate($date) {
    if (!empty($date->_same_end_date)) {
      $format_type = $this->getSetting('same_end_date_format_type');
    }
    else if (empty($date->_dateRecurIsOccurrence)) {
      $format_type = $this->getSetting('format_type');
    }
    else {
      $format_type = $this->getSetting('occurrence_format_type');
    }
    $timezone = $this->getSetting('timezone_override') ?: $date->getTimezone()->getName();
    return $this->dateFormatter->format($date->getTimestamp(), $format_type, '', $timezone != '' ? $timezone : NULL);
  }


  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $isSharedMax = !$this->getSetting('count_per_item');
    $maxOccurrences = (int) $this->getSetting('show_next');
    foreach ($items as $delta => $item) {
      $elements[$delta] = $this->viewValue($item, $maxOccurrences);

      if ($isSharedMax) {
        // Subtract the occurrences found in this item if occurrence count is
        // shared across all field items.
        $maxOccurrences -= count($elements[$delta]['#occurrences']);
      }
    }

    return $elements;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param DateRecurItem $item
   *   One field item.
   *
   * @return string
   *   The textual output generated.
   */
  protected function viewValue(DateRecurItem $item, $maxOccurrences) {
    $build = [
      '#theme' => 'date_recur_default_formatter',
    ];

    if (empty($item->end_date)) {
      $item->end_date = clone $item->start_date;
    }
    $build['#date'] = $this->buildDateRangeValue($item->start_date, $item->end_date);

    if (empty($item->rrule)) {
      $build['#isRecurring'] = FALSE;
    }
    else {
      $build['#isRecurring'] = TRUE;
    }

    if ($this->getSetting('show_rrule') && !empty($item->rrule)) {
      $build['#repeatrule'] = $item->getOccurrenceHandler()->humanReadable();
    }

    $build['#occurrences'] = $this->viewOccurrences($item, $maxOccurrences);

    if (!empty($item->_attributes)) {
      $build += $item->_attributes;
      // Unset field item attributes since they have been included in the
      // formatter output and should not be rendered in the field template.
      unset($item->_attributes);
    }

    return $build;
  }

  protected function viewOccurrences(DateRecurItem $item, $maxOccurrences) {
    if ($maxOccurrences <= 0) {
      return [];
    }

    $build = [];
    $start = new \DateTime('now');

    $occurrences = $item->getOccurrenceHandler()
      ->getHelper()
      // @todo change to generator.
      ->getOccurrences($start, NULL, $maxOccurrences);
    foreach ($occurrences as $occurrence) {
      $startDate = DrupalDateTime::createFromDateTime($occurrence->getStart());
      $endDate = DrupalDateTime::createFromDateTime($occurrence->getEnd());
      $build[] = $this->buildDateRangeValue(
        $startDate,
        $endDate,
        TRUE
      );
    }

    return $build;
  }

}
