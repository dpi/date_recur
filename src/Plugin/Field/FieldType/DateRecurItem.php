<?php

namespace Drupal\date_recur\Plugin\Field\FieldType;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\date_recur\DateRecurHelper;
use Drupal\date_recur\DateRecurNonRecurringHelper;
use Drupal\date_recur\DateRecurPartGrid;
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
 *   list_class = "\Drupal\date_recur\Plugin\Field\FieldType\DateRecurFieldItemList",
 *   constraints = {"DateRecurRuleParts" = {}}
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
      ->setRequired(FALSE)
      ;//->addConstraint('DateRecurRuleParts');
    $rruleMaxLength = $field_definition->getSetting('rrule_max_length');
    assert(empty($rruleMaxLength) || (is_numeric($rruleMaxLength) && $rruleMaxLength > 0));
    if (!empty($rruleMaxLength)) {
      $properties['rrule']->addConstraint('Length', ['max' => $rruleMaxLength]);
    }

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
  public static function defaultStorageSettings() {
    return [
      'rrule_max_length' => 256,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      // @todo needs settings tests.
      'precreate' => 'P2Y',
      'parts' => [
        'all' => TRUE,
        'frequencies' => [],
      ],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = parent::storageSettingsForm($form, $form_state, $has_data);

    $element['rrule_max_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum character length of RRULE'),
      '#description' => $this->t('Define the maximum characters a RRULE can contain.'),
      '#default_value' => $this->getSetting('rrule_max_length'),
      '#min' => 0,
    ];

    return $element;
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

    $element['parts'] = [
      '#type' => 'container',
    ];
    $element['parts']['#after_build'][] = [get_class($this), 'afterBuildXyz'];

    $parents = [
      'settings',
      'parts',
      'all',
    ];
    // Constructs a name that looks like settings[parts][all].
    $allFrequencyName = $parents[0] . '[' . implode('][', array_slice($parents, 1)) . ']';

    $allPartsSettings = $this->getSetting('parts');
    $element['parts']['all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow all frequency and parts'),
      '#default_value' => $allPartsSettings['all'],
    ];

    $frequencyLabels = DateRecurPartGrid::frequencyLabels();
    $partLabels = DateRecurPartGrid::partLabels();

    $partsCheckboxes = [];
    foreach (DateRecurPartGrid::PARTS as $part) {
      $partsCheckboxes[$part] = [
        '#type' => 'checkbox',
        '#title' => $partLabels[$part],
      ];
    }

    $partsOptions = [
      'disabled' => $this->t('Disabled'),
      'all-parts' => $this->t('All parts'),
      'some-parts' => $this->t('Specify parts'),
    ];

    // Table is a container so visibility states can be added.
    $element['parts']['table'] = [
      '#theme' => 'date_recur_settings_frequency_table',
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="' . $allFrequencyName . '"]' => ['checked' => FALSE],
        ],
      ],
    ];
    foreach (DateRecurPartGrid::FREQUENCIES as $frequency) {
      $row = [];

      $row['frequency']['#plain_text'] = $frequencyLabels[$frequency];

      $parents = [
        'settings',
        'parts',
        'table',
        $frequency,
        'setting',
      ];
      // Constructs a name that looks like
      // settings[parts][table][MINUTELY][setting].
      $settingId = $parents[0] . '[' . implode('][', array_slice($parents, 1)) . ']';

      $enabledParts = isset($allPartsSettings['frequencies'][$frequency]) ? $allPartsSettings['frequencies'][$frequency] : [];
      $defaultSetting = NULL;
      if (count($enabledParts) === 0) {
        $defaultSetting = 'disabled';
      }
      elseif (in_array('*', $enabledParts)) {
        $defaultSetting = 'all-parts';
      }
      elseif (count($enabledParts) > 0) {
        $defaultSetting = 'some-parts';
      }

      $row['setting'] = [
        '#type' => 'radios',
        '#options' => $partsOptions,
        '#required' => TRUE,
        '#default_value' => $defaultSetting,
      ];

      $row['parts'] = $partsCheckboxes;
      foreach ($row['parts'] as $part => &$partsCheckbox) {
        $partsCheckbox['#states']['visible'][] = [
          ':input[name="' . $settingId . '"]' => ['value' => 'some-parts'],
        ];
        $partsCheckbox['#default_value'] = in_array($part, $enabledParts, TRUE);
      }

      $element['parts']['table'][$frequency] = $row;
    }

    $list = [];
    $partLabels = DateRecurPartGrid::partLabels();
    foreach (DateRecurPartGrid::partDescriptions() as $part => $partDescription) {
      $list[] = $this->t('<strong>@label:</strong> @description', [
        '@label' => $partLabels[$part],
        '@description' => $partDescription,
      ]);
    }
    $element['parts']['help']['#markup'] = '<ul><li>' . implode('</li><li>', $list) . '</li></ul></ul>';

    return $element;
  }

  /**
   * Change the format of values.
   *
   * FormBuilder has finished processing the input of children, now re-arrange
   * the values.
   *
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public static function afterBuildXyz(array $element, FormStateInterface $form_state) {
    // Original parts container.
    $values = NestedArray::getValue($form_state->getValues(), $element['#parents']);

    // Remove the original parts values so they dont get saved in same structure
    // as the form.
    NestedArray::unsetValue($form_state->getValues(), $element['#parents']);

    $parts = [];
    $parts['all'] = !empty($values['all']);
    $parts['frequencies'] = [];
    foreach ($values['table'] as $frequency => $row) {
      $enabledParts = array_keys(array_filter($row['parts']));
      if ($row['setting'] === 'all-parts') {
        $enabledParts[] = '*';
      }
      elseif ($row['setting'] === 'disabled') {
        $enabledParts = [];
      }

      $parts['frequencies'][$frequency] = $enabledParts;
    }

    // Set the new value.
    $form_state->setValue($element['#parents'], $parts);

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

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values = parent::generateSampleValue($field_definition);

    $timeZoneList = timezone_identifiers_list();
    $values['timezone'] = $timeZoneList[array_rand($timeZoneList)];
    $values['rrule'] = 'FREQ=DAILY;COUNT=' . rand(2, 10);
    $values['infinite'] = FALSE;

    return $values;
  }

}
