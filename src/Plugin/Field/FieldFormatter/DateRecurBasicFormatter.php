<?php

namespace Drupal\date_recur\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\DependencyTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\date_recur\DateRange;
use Drupal\date_recur\Entity\DateRecurInterpreterInterface;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;
use Drupal\datetime_range\Plugin\Field\FieldFormatter\DateRangeDefaultFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Basic recurring date formatter.
 *
 * @FieldFormatter(
 *   id = "date_recur_basic_formatter",
 *   label = @Translation("Date recur basic formatter"),
 *   field_types = {
 *     "date_recur"
 *   }
 * )
 */
class DateRecurBasicFormatter extends DateRangeDefaultFormatter {

  use DependencyTrait;

  const COUNT_PER_ITEM_ITEM = 'per_item';

  const COUNT_PER_ITEM_ALL = 'all_items';

  /**
   * The date recur interpreter entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $dateRecurInterpreterStorage;

  /**
   * Date format config ID.
   *
   * @var string|null
   */
  protected $formatType;

  /**
   * Constructs a new DateRecurBasicFormatter.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityStorageInterface $dateFormatStorage
   *   The date format entity storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $dateRecurInterpreterStorage
   *   The date recur interpreter entity storage.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, DateFormatterInterface $dateFormatter, EntityStorageInterface $dateFormatStorage, EntityStorageInterface $dateRecurInterpreterStorage) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $dateFormatter, $dateFormatStorage);
    $this->dateRecurInterpreterStorage = $dateRecurInterpreterStorage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('date.formatter'),
      $container->get('entity_type.manager')->getStorage('date_format'),
      $container->get('entity_type.manager')->getStorage('date_recur_interpreter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      // Show number of occurrences.
      'show_next' => 5,
      // Whether number of occurrences should be per item or in total.
      'count_per_item' => TRUE,
      // Date format for occurrences.
      'occurrence_format_type' => 'medium',
      // Date format for end date, if same day as start date.
      'same_end_date_format_type' => 'medium',
      // Specify whether "All day" settings should be observed.
      'allow_all_day' => FALSE,
      // Date format for "All day" dates.
      'all_day_format_type' => 'html_date',
      'interpreter' => NULL,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $this->dependencies = parent::calculateDependencies();

    /** @var string|null $dateFormatId */
    $interpreterId = $this->getSetting('interpreter');
    if ($interpreterId && ($interpreter = $this->dateRecurInterpreterStorage->load($interpreterId))) {
      $this->addDependency('config', $interpreter->getConfigDependencyName());
    }

    $dateFormatDependencies = [
      'format_type',
      'occurrence_format_type',
      'same_end_date_format_type',
      'all_day_format_type',
    ];
    foreach ($dateFormatDependencies as $dateFormatId) {
      $id = $this->getSetting($dateFormatId);
      $dateFormat = $this->dateFormatStorage->load($id);
      if (!$dateFormat) {
        continue;
      }
      $this->addDependency('config', $dateFormat->getConfigDependencyName());
    }

    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $originalFormatType = $form['format_type'];
    unset($form['format_type']);

    // Redefine format type to change the natural order of form fields.
    $form['format_type'] = $originalFormatType;
    $form['format_type']['#title'] = $this->t('Non-Repeating Date format');
    $form['format_type']['#description'] = $this->t('Date format used for field values without repeat rules.');
    $form['occurrence_format_type'] = $originalFormatType;
    $form['occurrence_format_type']['#title'] = $this->t('Start and end date format');
    $form['occurrence_format_type']['#default_value'] = $this->getSetting('occurrence_format_type');
    $form['occurrence_format_type']['#description'] = $this->t('Date format used for field values with repeat rules.');
    $form['same_end_date_format_type'] = $originalFormatType;
    $form['same_end_date_format_type']['#title'] = $this->t('Same day end date format');
    $form['same_end_date_format_type']['#description'] = $this->t('Date format used for end date if field value has repeat rule. Used only if occurs on same calendar day as start date.');
    $form['same_end_date_format_type']['#default_value'] = $this->getSetting('same_end_date_format_type');

    $form['allow_all_day'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow all day'),
      '#description' => $this->t('Whether to check for "all day" dates.'),
      '#default_value' => $this->getSetting('allow_all_day'),
    ];
    $form['all_day_format_type'] = $originalFormatType;
    $form['all_day_format_type']['#title'] = $this->t('"All day" date format');
    $form['all_day_format_type']['#description'] = $this->t('Date format used if date is set as "All day"');
    $form['all_day_format_type']['#default_value'] = $this->getSetting('all_day_format_type');
    $form['all_day_format_type']['#states']['visible'] = [
      ':input[name="fields[field_recurring_date_field][settings_edit_form][settings][allow_all_day]"]' => [
        'checked' => TRUE,
      ],
    ];

    // Redefine separator to change the natural order of form fields.
    $originalSeparator = $form['separator'];
    unset($form['separator']);
    $form['separator'] = $originalSeparator;
    // Change the width of the field if not already set. (Not set by default)
    $form['separator']['#size'] = isset($form['separator']['#size']) ? $form['separator']['#size'] : 5;

    // Redefine timezone to change the natural order of form fields.
    $originalTimezoneOverride = $form['timezone_override'];
    unset($form['timezone_override']);
    $form['timezone_override'] = $originalTimezoneOverride;
    $form['timezone_override']['#empty_option'] = $this->t('Use current user timezone');
    $form['timezone_override']['#description'] = $this->t('Change the timezone used for displaying dates (not recommended).');

    $interpreterOptions = array_map(function (DateRecurInterpreterInterface $interpreter) {
        return $interpreter->label();
    }, $this->dateRecurInterpreterStorage->loadMultiple());
    $form['interpreter'] = [
      '#type' => 'select',
      '#title' => $this->t('Recurring date interpreter'),
      '#description' => $this->t('Choose a plugin for converting rules into a human readable description.'),
      '#default_value' => $this->getSetting('interpreter'),
      '#options' => $interpreterOptions,
      '#required' => FALSE,
      '#empty_option' => $this->t('- Do not show interpreted rule -'),
    ];

    $form['occurrences'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline']],
      '#tree' => FALSE,
    ];

    $form['occurrences']['show_next'] = [
      '#field_prefix' => $this->t('Show maximum of'),
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
        static::COUNT_PER_ITEM_ITEM => $this->t('per field item'),
        static::COUNT_PER_ITEM_ALL => $this->t('across all field items'),
      ],
      '#default_value' => $this->getSetting('count_per_item') ? static::COUNT_PER_ITEM_ITEM : static::COUNT_PER_ITEM_ALL,
      '#element_validate' => [[static::class, 'validateSettingsCountPerItem']],
    ];

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
    $countPerItem = $element['#value'] == static::COUNT_PER_ITEM_ITEM;
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
    $this->formatType = $this->getSetting('format_type');
    $summary = parent::settingsSummary();

    $countPerItem = $this->getSetting('count_per_item');
    $showOccurrencesCount = $this->getSetting('show_next');
    if ($showOccurrencesCount > 0) {
      $summary[] = $this->formatPlural(
        $showOccurrencesCount,
        'Show maximum of @count occurrence @per',
        'Show maximum of @count occurrences @per',
        ['@per' => $countPerItem ? $this->t('per field item') : $this->t('across all field items')]
      );
    }

    $start = new DrupalDateTime('today 9am');
    $endSameDay = clone $start;
    $endSameDay->setTime(17, 0, 0);
    $summary['sample_same_day'] = [
      '#type' => 'inline_template',
      '#template' => '{{ label }}: {{ sample }}',
      '#context' => [
        'label' => $this->t('Same day range'),
        'sample' => $this->buildDateRangeValue($start, $endSameDay, TRUE, []),
      ],
    ];
    $endDifferentDay = clone $endSameDay;
    $endDifferentDay->modify('+1 day');
    $summary['sample_different_day'] = [
      '#type' => 'inline_template',
      '#template' => '{{ label }}: {{ sample }}',
      '#context' => [
        'label' => $this->t('Different day range'),
        'sample' => $this->buildDateRangeValue($start, $endDifferentDay, TRUE, []),
      ],
    ];

    if ($this->getSetting('allow_all_day')) {
      $this->formatType = $this->getSetting('all_day_format_type');
      $summary['all_day'] = [
        '#type' => 'inline_template',
        '#template' => '{{ label }}: {{ sample }}',
        '#context' => [
          'label' => $this->t('All day'),
          'sample' => $this->formatDate($start),
        ],
      ];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // Whether maximum is per field item or in total.
    $isSharedMaximum = !$this->getSetting('count_per_item');
    // Maximum amount of occurrences to be displayed.
    $occurrenceQuota = (int) $this->getSetting('show_next');
    // Whether or not the "all day" condition should be evaluated.
    $allowAllDay = $this->getSetting('allow_all_day');

    $elements = [];
    foreach ($items as $delta => $item) {
      $value = $this->viewItem($item, $occurrenceQuota, $allowAllDay);
      $occurrenceQuota -= ($isSharedMaximum ? count($value['#occurrences']) : 0);
      $elements[$delta] = $value;
      if ($occurrenceQuota <= 0) {
        break;
      }
    }

    return $elements;
  }

  /**
   * Generate the output appropriate for a field item.
   *
   * @param \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem $item
   *   A field item.
   * @param int $maxOccurrences
   *   Maximum number of occurrences to show for this field item.
   * @param bool $checkAllDay
   *   Whether or not the "all day" condition should be evaluated.
   *
   * @return array
   *   A render array for a field item.
   */
  protected function viewItem(DateRecurItem $item, $maxOccurrences, $checkAllDay = FALSE) {
    $cacheability = new CacheableMetadata();
    $build = [
      '#theme' => 'date_recur_basic_formatter',
      '#is_recurring' => $item->isRecurring(),
    ];

    $isAllDay = [];
    if ($checkAllDay) {
      if ($item->isStartAllDay()) {$isAllDay[] = 'start';}
      if ($item->isEndAllDay()) {$isAllDay[] = 'end';}
    }

    $timezone = new \DateTimeZone($item->getValue()['timezone']);
    /** @var \Drupal\Core\Datetime\DrupalDateTime|null $startDate */
    $startDate = $item->start_date->setTimezone($timezone);
    /** @var \Drupal\Core\Datetime\DrupalDateTime|null $endDate */
    $endDate = $item->end_date ? $item->end_date->setTimezone($timezone) : $startDate;
    $build['#date'] = $this->buildDateRangeValue($startDate, $endDate, FALSE, $isAllDay);

    // Render the rule.
    if ($item->isRecurring() && $this->getSetting('interpreter')) {
      /** @var string|null $interpreterId */
      $interpreterId = $this->getSetting('interpreter');
      if ($interpreterId && ($interpreter = $this->dateRecurInterpreterStorage->load($interpreterId))) {
        /** @var \Drupal\date_recur\Entity\DateRecurInterpreterInterface $interpreter */
        $rules = $item->getHelper()->getRules();
        $plugin = $interpreter->getPlugin();
        $cacheability->addCacheableDependency($interpreter);
        $build['#interpretation'] = $plugin->interpret($rules, 'en');
      }
    }

    // Occurrences are generated even if the item is not recurring.
    $build['#occurrences'] = array_map(
      function (DateRange $occurrence) {
        $startDate = DrupalDateTime::createFromDateTime($occurrence->getStart());
        $endDate = DrupalDateTime::createFromDateTime($occurrence->getEnd());
        return $this->buildDateRangeValue(
          $startDate,
          $endDate,
          TRUE
        );
      },
      $this->getOccurrences($item, $maxOccurrences)
    );

    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Builds a date range suitable for rendering.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $startDate
   *   The start date.
   * @param \Drupal\Core\Datetime\DrupalDateTime $endDate
   *   The start date.
   * @param bool $isOccurrence
   *   Whether the range is an occurrence of a repeating value.
   * @param array $isAllDay
   *   Whether the "all day" format should be used for the start or end dates.
   *
   * @return array
   *   A render array.
   */
  protected function buildDateRangeValue(DrupalDateTime $startDate, DrupalDateTime $endDate, $isOccurrence, $isAllDay = []) {

    $this->formatType = $isOccurrence ? $this->getSetting('occurrence_format_type') : $this->getSetting('format_type');
    if (array_search('start', $isAllDay) !== FALSE) {
      $this->formatType = $this->getSetting('all_day_format_type');
    }
    $startDateString = $this->buildDateWithIsoAttribute($startDate);

    // Show the range if start and end are different, otherwise only start date.
    if ($startDate->getTimestamp() === $endDate->getTimestamp()) {
      return $startDateString;
    }
    else {
      // Start date and end date are different.
      $this->formatType = $startDate->format('Ymd') == $endDate->format('Ymd') ?
        $this->getSetting('same_end_date_format_type') :
        $this->getSetting('occurrence_format_type');
      if (array_search('end', $isAllDay) !== FALSE) {
        $this->formatType = $this->getSetting('all_day_format_type');
      }
      $endDateString = $this->buildDateWithIsoAttribute($endDate);
      return [
        'start_date' => $startDateString,
        'separator' => ['#plain_text' => $this->getSetting('separator')],
        'end_date' => $endDateString,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function buildDateWithIsoAttribute(DrupalDateTime $date) {
    // Create the ISO date in Universal Time.
    $iso_date = $date->format("Y-m-d\TH:i:s") . 'Z';

    $build = [
      '#theme' => 'time',
      '#text' => $this->formatDate($date),
      '#html' => FALSE,
      '#attributes' => [
        'datetime' => $iso_date,
      ],
      '#cache' => [
        'contexts' => [
          'timezone',
        ],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function formatDate($date) {
    if (!is_string($this->formatType)) {
      throw new \LogicException('Date format must be set.');
    }
    $timezone = $this->getSetting('timezone_override') ?: $date->getTimezone()->getName();
    //kint($date);
    //kint($timezone);
    return $this->dateFormatter->format($date->getTimestamp(), $this->formatType, '', $timezone);
  }

  /**
   * Get the occurrences for a field item.
   *
   * Occurrences are abstracted out to make it easier for extending formatters
   * to change.
   *
   * @param \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem $item
   *   A field item.
   * @param int $maxOccurrences
   *   Maximum number of occurrences to render.
   *
   * @return \Drupal\date_recur\DateRange[]
   *   A render array.
   */
  protected function getOccurrences(DateRecurItem $item, $maxOccurrences) {
    $start = new \DateTime('now');
    return $item->getHelper()
      ->getOccurrences($start, NULL, $maxOccurrences);
  }

}
