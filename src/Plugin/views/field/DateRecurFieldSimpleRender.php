<?php

namespace Drupal\date_recur\Plugin\views\field;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Form\FormStateInterface;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;
use Drupal\views\Plugin\views\field\EntityField;
use Drupal\views\ResultRow;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers

 * @ViewsField("date_recur_field_simple_render")
 */
class DateRecurFieldSimpleRender extends EntityField {

  /**
   * @var EntityViewDisplay The entity display.
   */
  protected $display;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['hide_alter_empty'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function query($use_groupby = FALSE) {
    $this->ensureMyTable();
    // Add the field.
    $params = $this->options['group_type'] != 'group' ? ['function' => $this->options['group_type']] : [];
    $this->field_alias = $this->query->addField($this->tableAlias, $this->realField, NULL, $params);
    $this->addAdditionalFields();
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL) {
    $alias = isset($field) ? $this->aliases[$field] : $this->field_alias;
    if (isset($values->{$alias})) {
      return $values->{$alias};
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getEntity($values);

    if (empty($this->display)) {
      $this->display = EntityViewDisplay::create([
        'targetEntityType' => $this->getEntityType(),
        'bundle' => $entity->bundle(),
        'status' => TRUE,
      ]);

      $this->display->setComponent($this->definition['field_name'], [
        'type' => $this->options['type'],
        'settings' => $this->options['settings'],
        'label' => 'hidden',
      ]);
    }

    $build = $this->display->build($entity);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(ResultRow $values) {
    $entity = parent::getEntity($values);
    $field_name = $this->definition['field_name'];
    $item = $entity->{$field_name}->first();
    $item->value = $values->{$this->field_alias};
    if (!empty($this->aliases[$field_name . '_end_value'])) {
      $item->end_value = $values->{$this->aliases[$field_name . '_end_value']};
    }
    $entity->{$field_name}->filter(function(DateRecurItem $item) {
      if ($item->getDelta() === 0) {
        return TRUE;
      }
      return FALSE;
    });
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function allowAdvancedRender() {
    return FALSE;
  }

}
