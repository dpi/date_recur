<?php

namespace Drupal\date_recur\Plugin\Field\FieldType;

use Drupal\date_recur\DateRecurPartGrid;
use Drupal\date_recur\Event\DateRecurEvents;
use Drupal\date_recur\Event\DateRecurValueEvent;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeFieldItemList;

/**
 * Recurring date field item list.
 */
class DateRecurFieldItemList extends DateRangeFieldItemList {

  /**
   * An event dispatcher, primarily for unit testing purposes.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|null
   */
  protected $eventDispatcher = NULL;

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    parent::postSave($update);
    $event = new DateRecurValueEvent($this, !$update);
    $this->getDispatcher()->dispatch(DateRecurEvents::FIELD_VALUE_SAVE, $event);
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    parent::delete();
    $event = new DateRecurValueEvent($this, FALSE);
    $this->getDispatcher()->dispatch(DateRecurEvents::FIELD_ENTITY_DELETE, $event);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRevision() {
    parent::deleteRevision();
    $event = new DateRecurValueEvent($this, FALSE);
    $this->getDispatcher()->dispatch(DateRecurEvents::FIELD_REVISION_DELETE, $event);
  }

  /**
   * Get the event dispatcher.
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   The event dispatcher.
   */
  protected function getDispatcher() {
    if (isset($this->eventDispatcher)) {
      return $this->eventDispatcher;
    }
    return \Drupal::service('event_dispatcher');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesForm(array &$form, FormStateInterface $form_state) {
    $element = parent::defaultValuesForm($form, $form_state);

    $defaultValue = $this->getFieldDefinition()->getDefaultValueLiteral();

    $element['default_time_zone'] = [
      '#type' => 'select',
      '#title' => $this->t('Time zone'),
      '#description' => $this->t('Time zone is required if a default start date or end date is provided.'),
      '#options' => $this->getTimeZoneOptions(),
      '#default_value' => isset($defaultValue[0]['default_time_zone']) ? $defaultValue[0]['default_time_zone'] : '',
    ];

    $element['default_rrule'] = [
      '#type' => 'textarea',
      '#title' => $this->t('RRULE'),
      '#default_value' => isset($defaultValue[0]['default_rrule']) ? $defaultValue[0]['default_rrule'] : '',
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormValidate(array $element, array &$form, FormStateInterface $form_state) {
    $defaultTimeZone = $form_state->getValue(['default_value_input', 'default_time_zone']);
    if (empty($defaultTimeZone)) {
      $defaultStartType = $form_state->getValue(['default_value_input', 'default_date_type']);
      if (!empty($defaultStartType)) {
        $form_state->setErrorByName('default_value_input][default_time_zone', $this->t('Time zone must be provided if a default start date is provided.'));
      }

      $defaultEndType = $form_state->getValue(['default_value_input', 'default_end_date_type']);
      if (!empty($defaultEndType)) {
        $form_state->setErrorByName('default_value_input][default_time_zone', $this->t('Time zone must be provided if a default end date is provided.'));
      }
    }

    parent::defaultValuesFormValidate($element, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormSubmit(array $element, array &$form, FormStateInterface $form_state) {
    $values = parent::defaultValuesFormSubmit($element, $form, $form_state);

    $rrule = $form_state->getValue(['default_value_input', 'default_rrule']);
    if ($rrule) {
      $values[0]['default_rrule'] = $rrule;
    }

    $timeZone = $form_state->getValue(['default_value_input', 'default_time_zone']);
    if ($timeZone) {
      $values[0]['default_time_zone'] = $timeZone;
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function processDefaultValue($default_value, FieldableEntityInterface $entity, FieldDefinitionInterface $definition) {
    $rrule = isset($default_value[0]['default_rrule']) ? $default_value[0]['default_rrule'] : NULL;
    $timeZone = isset($default_value[0]['default_time_zone']) ? $default_value[0]['default_time_zone'] : NULL;
    $defaultValue = parent::processDefaultValue($default_value, $entity, $definition);
    $defaultValue[0]['rrule'] = $rrule;
    $defaultValue[0]['timezone'] = $timeZone;
    return $defaultValue;
  }

  /**
   * Get a list of time zones suitable for a select field.
   *
   * @return array
   *   A list of time zones where keys are PHP time zone codes, and values are
   *   human readable and translatable labels.
   */
  protected function getTimeZoneOptions() {
    return \system_time_zones(TRUE, TRUE);
  }

  /**
   * Set the event dispatcher.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface|null $eventDispatcher
   *   The event dispatcher.
   */
  public function setEventDispatcher($eventDispatcher) {
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Get the parts grid for this field.
   *
   * @return \Drupal\date_recur\DateRecurPartGrid
   *   The parts grid for this field.
   */
  public function getPartGrid() {
    $partSettings = $this->getFieldDefinition()->getSetting('parts');
    // Existing field configs may not have a parts setting yet.
    $partSettings = isset($partSettings) ? $partSettings : [];
    return DateRecurPartGrid::configSettingsToGrid($partSettings);
  }

}
