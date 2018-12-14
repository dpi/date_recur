<?php

namespace Drupal\date_recur\Plugin\Field\FieldType;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\date_recur\DateRecurHelper;
use Drupal\date_recur\DateRecurNonRecurringHelper;
use Drupal\date_recur\DateRecurUtility;
use Drupal\date_recur\Exception\DateRecurHelperArgumentException;
use Drupal\date_recur\Plugin\Field\DateRecurOccurrencesComputed;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem;

/**
 * Plugin implementation of the 'date_recur' field type.
 *
 * @FieldType(
 *   id = "date_recur",
 *   label = @Translation("Date Recur"),
 *   description = @Translation("Recurring dates field"),
 *   default_widget = "date_recur_basic_widget",
 *   default_formatter = "date_recur_basic_formatter",
 *   list_class = "\Drupal\date_recur\Plugin\Field\FieldType\DateRecurFieldItemList"
 * )
 */
class DateRecurItem extends DateRangeItem {

  /**
   * The date recur helper.
   *
   * @var \Drupal\date_recur\DateRecurHelperInterface|null
   */
  protected $helper;

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['rrule'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('RRule'))
      ->setRequired(FALSE);

    $properties['timezone'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Timezone'))
      ->setRequired(TRUE)
      ->addConstraint('DateRecurTimeZone');

    $properties['infinite'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Whether the RRule is an infinite rule. Derived value from RRULE.'))
      ->setRequired(FALSE);

    $properties['occurrences'] = ListDataDefinition::create('any')
      ->setLabel(new TranslatableMarkup('Occurrences'))
      ->setComputed(TRUE)
      ->setClass(DateRecurOccurrencesComputed::class);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $schema['columns']['rrule'] = [
      'description' => 'The repeat rule.',
      'type' => 'text',
    ];
    $schema['columns']['timezone'] = [
      'description' => 'The timezone.',
      'type' => 'varchar',
      'length' => 255,
    ];
    $schema['columns']['infinite'] = [
      'description' => 'Whether the RRule is an infinite rule. Derived value from RRULE.',
      'type' => 'int',
      'size' => 'tiny',
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      // @todo needs settings tests.
      'precreate' => 'P2Y',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::fieldSettingsForm($form, $form_state);

    // @todo Needs UI tests.
    $options = [];
    foreach (range(1, 5) as $i) {
      $options['P' . $i . 'Y'] = $this->formatPlural($i, '@year year', '@year years', ['@year' => $i]);
    }
    // @todo allow custom values.
    $element['precreate'] = [
      '#type' => 'select',
      '#title' => $this->t('Precreate occurrences'),
      '#description' => $this->t('For infinitely repeating dates, precreate occurrences for this amount of time in the views cache table.'),
      '#options' => $options,
      '#default_value' => $this->getSetting('precreate'),
    ];

    return $element;
  }

  /**
   * Get the date storage format of this field.
   *
   * @return string
   *   A date format string.
   */
  public function getDateStorageFormat() {
    // @todo tests
    return $this->getSetting('datetime_type') == static::DATETIME_TYPE_DATE ? static::DATE_STORAGE_FORMAT : static::DATETIME_STORAGE_FORMAT;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();
    $isInfinite = $this->getHelper()->isInfinite();
    $this->get('infinite')->setValue($isInfinite);
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    // Cast infinite to boolean on load.
    $values['infinite'] = !empty($values['infinite']);
    parent::setValue($values, $notify);
  }

  /**
   * Determine whether the field value is recurring/repeating.
   *
   * @return bool
   *   Whether the field value is recurring.
   */
  public function isRecurring() {
    return !empty($this->rrule);
  }

  /**
   * Get the helper for this field item.
   *
   * Will always return a helper even if field value is non-recurring.
   *
   * @return \Drupal\date_recur\DateRecurHelperInterface
   *   The helper.
   *
   * @throws \Drupal\date_recur\Exception\DateRecurHelperArgumentException
   *   If a helper could not be created due to faulty field value.
   */
  public function getHelper() {
    if (isset($this->helper)) {
      return $this->helper;
    }

    try {
      $timeZone = new \DateTimeZone($this->timezone);
    }
    catch (\Exception $exception) {
      throw new DateRecurHelperArgumentException('Invalid time zone');
    }

    $startDate = NULL;
    $startDateEnd = NULL;
    if ($this->start_date instanceof DrupalDateTime) {
      $startDate = DateRecurUtility::toPhpDateTime($this->start_date);
      $startDate->setTimezone($timeZone);
    }
    else {
      throw new DateRecurHelperArgumentException('Start date is required.');
    }
    if ($this->end_date instanceof DrupalDateTime) {
      $startDateEnd = DateRecurUtility::toPhpDateTime($this->end_date);
      $startDateEnd->setTimezone($timeZone);
    }
    $this->helper = $this->isRecurring() ?
      DateRecurHelper::create($this->rrule, $startDate, $startDateEnd) :
      DateRecurNonRecurringHelper::createInstance('', $startDate, $startDateEnd);
    return $this->helper;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $rrule = $this->get('rrule')->getValue();
    return parent::isEmpty() && ($rrule === NULL || $rrule === '');
  }

}
