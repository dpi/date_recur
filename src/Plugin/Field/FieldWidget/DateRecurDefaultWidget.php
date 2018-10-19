<?php

namespace Drupal\date_recur\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Datetime\Element\Datetime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\date_recur\DateRecurHelper;
use Drupal\date_recur\DateRecurUtility;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime_range\Plugin\Field\FieldWidget\DateRangeDefaultWidget;

/**
 * Basic RRULE widget.
 *
 * Displays an input textarea accepting RRULE strings.
 *
 * @FieldWidget(
 *   id = "date_recur_basic_widget",
 *   label = @Translation("Simple Recurring Date Widget"),
 *   field_types = {
 *     "date_recur"
 *   }
 * )
 */
class DateRecurDefaultWidget extends DateRangeDefaultWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'timezone_override' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    // Aka default time zone.
    $elements['timezone_override'] = [
      '#type' => 'select',
      '#title' => $this->t('Default time zone'),
      '#description' => $this->t('Dates will repeat differently depending on time zone. For example: if you want a rule to repeat every Wednesday, the Wednesday will start and end at different times depending on the time zone. Recommended value: use current user time zone.'),
      '#options' => $this->getTimeZoneOptions(),
      '#default_value' => $this->getSetting('timezone_override'),
      '#empty_option' => $this->t('- Use current user time zone -'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $timeZone = $this->getSetting('timezone_override') ?: $this->t('User time zone');
    $summary[] = $this->t('Default time zone: @time_zone', ['@time_zone' => $timeZone]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#theme'] = 'date_recur_basic_widget';
    $element['#element_validate'][] = [$this, 'validateRrule'];

    // ::createDefaultValue isnt given enough context about the field item, so
    // override its functions here.
    $element['value']['#default_value'] = $element['end_value']['#default_value'] = NULL;
    $element['value']['#date_timezone'] = $element['end_value']['#date_timezone'] = NULL;
    $this->createDateRecurDefaultValue($element, $items[$delta]);

    // Move fields into a first occurrence container as 'End date' can be
    // confused with 'End date' RRULE concept.
    $element['first_occurrence'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('First occurrence'),
    ];
    $firstOccurrenceParents = array_merge(
      $element['#field_parents'],
      [$this->fieldDefinition->getName(), $delta, 'first_occurrence']
    );
    $element['value']['#title'] = $this->t('Start');
    $element['end_value']['#title'] = $this->t('End');
    $element['end_value']['#description'] = $this->t('Leave end empty to copy start date; the occurrence will therefore not have any duration.');
    $element['value']['#group'] = $element['end_value']['#group'] = implode('][', $firstOccurrenceParents);

    // Add custom value callbacks to correctly form a date from time zone field.
    $element['value']['#value_callback'] = $element['end_value']['#value_callback'] = [$this, 'dateValueCallback'];

    // Saved values (should) always have a time zone.
    $timeZone = isset($items[$delta]->timezone)
      ? $items[$delta]->timezone
      : $this->getSetting('timezone_override') ?: $this->getCurrentUserTimeZone();

    $zones = system_time_zones(NULL, TRUE);
    $element['timezone'] = [
      '#type' => 'select',
      '#title' => t('Time zone'),
      '#default_value' => $timeZone,
      '#options' => $zones,
    ];

    $element['rrule'] = [
      '#type' => 'textarea',
      '#default_value' => isset($items[$delta]->rrule) ? $items[$delta]->rrule : NULL,
      '#title' => $this->t('Repeat rule'),
      '#description' => $this->t('Repeat rule in <a href=":link">iCalendar Recurrence Rule</a> (RRULE) format.', [
        ':link' => 'https://icalendar.org/iCalendar-RFC-5545/3-8-5-3-recurrence-rule.html',
      ]),
    ];

    return $element;
  }

  /**
   * Validator for start and end elements.
   *
   * Sets the time zone before datetime element processes values.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param array|false $input
   *   Input, if any.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return mixed
   *   The value to assign to the element.
   */
  public function dateValueCallback(array $element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE) {
      $timeZonePath = $element['#parents'];
      array_pop($timeZonePath);
      $timeZonePath[] = 'timezone';

      // Warning: The time zone is not yet validated, make sure it is valid
      // before using.
      $submittedTimeZone = NestedArray::getValue($form_state->getUserInput(), $timeZonePath);
      $allTimeZones = \DateTimeZone::listIdentifiers();
      // @todo Add test for invalid submitted time zone.
      if (!in_array($submittedTimeZone, $allTimeZones)) {
        // A date is invalid if the time zone is invalid.
        // Need to kill inputs otherwise
        // \Drupal\Core\Datetime\Element\Datetime::validateDatetime thinks there
        // is valid input.
        return [
          'date' => '',
          'time' => '',
          'object' => NULL,
        ];
      }

      $element['#date_timezone'] = $submittedTimeZone;
    }

    // Setting a callback overrides default value callback in the element,
    // call original now.
    return Datetime::valueCallback($element, $input, $form_state);
  }

  /**
   * Validates RRULE and first occurrence dates.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function validateRrule(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $input = NestedArray::getValue($form_state->getValues(), $element['#parents']);
    /** @var \Drupal\Core\Datetime\DrupalDateTime|array|null $startDate */
    $startDate = $input['value'];
    /** @var \Drupal\Core\Datetime\DrupalDateTime|array|null $startDateEnd */
    $startDateEnd = $input['end_value'];
    if (is_array($startDate) || is_array($startDate)) {
      // Dates are an array if invalid input was submitted (e.g date:
      // 80616-02-01).
      return;
    }

    /** @var string $rrule */
    $rrule = $input['rrule'];

    if ($startDateEnd && !isset($startDate)) {
      $form_state->setError($element['value'], $this->t('Start date must be set if end date is set.'));
    }

    // If end was empty, copy start date over.
    if (!isset($startDateEnd) && $startDate) {
      $form_state->setValueForElement($element['end_value'], $startDate);
      $startDateEnd = $startDate;
    }

    // Validate RRULE.
    // Only ensure start date is set, as end date is optional.
    if (strlen($rrule) > 0 && $startDate) {
      try {
        DateRecurHelper::create(
          $rrule,
          DateRecurUtility::toPhpDateTime($startDate),
          DateRecurUtility::toPhpDateTime($startDateEnd)
        );
      }
      catch (\Exception $e) {
        $form_state->setError($element['rrule'], $this->t('Repeat rule is formatted incorrectly.'));
      }
    }
  }

  /**
   * Get a list of time zones suitable for a select field.
   *
   * @return array
   *   A list of time zones where keys are PHP time zone codes, and values are
   *   human readable and translatable labels.
   */
  protected function getTimeZoneOptions() {
    return \system_time_zones(TRUE);
  }

  /**
   * Get the current users time zone.
   *
   * @return string
   *   A PHP time zone string.
   */
  protected function getCurrentUserTimeZone() {
    return \drupal_get_user_timezone();
  }

  /**
   * {@inheritdoc}
   */
  protected function createDefaultValue($date, $timezone) {
    // Cannot set time zone here as field item contains time zone.
    if ($this->getFieldSetting('datetime_type') == DateTimeItem::DATETIME_TYPE_DATE) {
      $date->setDefaultDateTime();
    }
    return $date;
  }

  /**
   * Set element default value and time zone.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem $item
   *   The date recur field item.
   */
  protected function createDateRecurDefaultValue(array &$element, DateRecurItem $item) {
    $startDate = $item->start_date;
    $startDateEnd = $item->end_date;
    $timeZone = isset($item->timezone) ? new \DateTimeZone($item->timezone) : NULL;
    if ($timeZone) {
      $element['value']['#date_timezone'] = $element['end_value']['#date_timezone'] = $timeZone;
      if ($startDate) {
        $startDate->setTimezone($timeZone);
        $element['value']['#default_value'] = $this->createDefaultValue($startDate, $timeZone->getName());
      }
      if ($startDateEnd) {
        $startDateEnd->setTimezone($timeZone);
        $element['end_value']['#default_value'] = $this->createDefaultValue($startDateEnd, $timeZone->getName());
      }
    }
  }

}
