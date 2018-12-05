<?php

namespace Drupal\date_recur\Plugin\Field\FieldType;

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
  public function defaultValuesForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::defaultValuesForm($form, $form_state);

    $defaultValue = $this->getFieldDefinition()->getDefaultValueLiteral();
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
  public function defaultValuesFormSubmit(array $element, array &$form, FormStateInterface $form_state): array {
    $values = parent::defaultValuesFormSubmit($element, $form, $form_state);

    $rrule = $form_state->getValue(['default_value_input', 'default_rrule']);
    if ($rrule) {
      $values[0]['default_rrule'] = $rrule;
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function processDefaultValue($default_value, FieldableEntityInterface $entity, FieldDefinitionInterface $definition): array {
    $rrule = isset($default_value[0]['default_rrule']) ? $default_value[0]['default_rrule'] : NULL;
    $defaultValue = parent::processDefaultValue($default_value, $entity, $definition);
    $defaultValue[0]['rrule'] = $rrule;
    return $defaultValue;
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

}
