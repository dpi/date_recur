<?php

namespace Drupal\date_recur\Plugin\Field\FieldType;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeFieldItemList;

/**
 * Represents a configurable entity date_recur field.
 */
class DateRecurFieldItemList extends DateRangeFieldItemList {
  public function postSave($update) {
    parent::postSave($update);
    /** @var DateRecurItem $item */
    foreach ($this as $field_delta => $item) {
      $item->getOccurrenceHandler()->onSave($update, $field_delta);
    }
    if ($update && isset($field_delta)) {
      $item->getOccurrenceHandler()->onSaveMaxDelta($field_delta);
    }
  }

  public function delete() {
    parent::delete();
    /** @var DateRecurItem $item */
    foreach ($this as $field_delta => $item) {
      $item->getOccurrenceHandler()->onDelete();
    }
  }

  public function deleteRevision() {
    parent::deleteRevision();
    /** @var DateRecurItem $item */
    foreach ($this as $field_delta => $item) {
      $item->getOccurrenceHandler()->onDeleteRevision();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::defaultValuesForm($form, $form_state);

    $default_value = $this->getFieldDefinition()->getDefaultValueLiteral();
    $element['default_rrule'] = [
      '#type' => 'textarea',
      '#title' => $this->t('RRULE'),
      '#default_value' => isset($default_value[0]['default_rrule']) ? $default_value[0]['default_rrule'] : '',
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
    $default_value = parent::processDefaultValue($default_value, $entity, $definition);
    $default_value[0]['rrule'] = $rrule;
    return $default_value;
  }

}
