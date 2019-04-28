<?php

declare(strict_types = 1);

namespace Drupal\date_recur\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\date_recur\DateRecurOccurrences;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Form to rebuild occurrence tables.
 */
class DateRecurRebuildTablesForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'date_recur_rebuild_tables';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $entityTypeManager = \Drupal::entityTypeManager();
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager */
    $fieldManager = \Drupal::service('entity_field.manager');

    $options = [];
    foreach ($fieldManager->getFieldMapByFieldType('date_recur') as $entityTypeId => $fields) {
      $entityFieldDefinitions = $fieldManager->getFieldStorageDefinitions($entityTypeId);
      foreach (array_keys($fields) as $fieldName) {
        $options[$entityTypeId . ':' . $fieldName] = [
          'label' => $entityTypeManager->getDefinition($entityTypeId)->getLabel(),
          'field_name' => $entityFieldDefinitions[$fieldName]->getLabel(),
        ];
      }
    }

    $form['entity_types'] = [
      '#type' => 'tableselect',
      '#header' => [
        'label' => $this->t('Entity type'),
        'field_name' => $this->t('Field name'),
      ],
      '#options' => $options,
      '#multiple' => TRUE,
      '#empty' => $this->t('There are fields.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Rebuild'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager */
    $fieldManager = \Drupal::service('entity_field.manager');

    $fieldMap = $fieldManager->getFieldMapByFieldType('date_recur');
    $entityTypeKeys = $form_state->getValue('entity_types');
    $batch = new BatchBuilder();
    foreach (array_keys(array_filter($entityTypeKeys)) as $key) {
      [$entityTypeId, $fieldName] = explode(':', $key);
      $batch->addOperation(
        [static::class, 'deleteAll'],
        [$entityTypeId, $fieldName]
      );

      $bundles = $fieldMap[$entityTypeId][$fieldName]['bundles'] ?? [];
      foreach ($bundles as $bundle) {
        $batch->addOperation(
          [static::class, 'batchCallback'],
          [$entityTypeId, $bundle, $fieldName]
        );
      }
    }

    \batch_set($batch->toArray());
    $form_state->setResponse(\batch_process(Url::fromRoute('date_recur.rebuild')));
  }

  static function deleteAll(string $entityTypeId, string $fieldName, &$context): void {
    $database = \Drupal::database();
    $fieldDefinition = FieldStorageConfig::loadByName($entityTypeId, $fieldName);
    $tableName = DateRecurOccurrences::getOccurrenceCacheStorageTableName($fieldDefinition);
    $database->truncate($tableName);
    $context['message'] = \t('Deleted existing entries @count of @total');
  }

  static function batchCallback(string $entityTypeId, ?string $bundle, string $fieldName, &$context): void {
    $perBatch = 10;
    $storage = \Drupal::entityTypeManager()->getStorage($entityTypeId);
    /** @var string $bundleField */
    $idField = $storage->getEntityType()->getKey('id');
    /** @var string|FALSE $bundleField */
    $bundleField = $storage->getEntityType()->getKey('bundle');

    $context['sandbox']['count'] = $context['sandbox']['count'] ?? 0;
    if (!isset($context['sandbox']['total'])) {
      $totalQuery = $storage->getQuery();
      if ($bundleField) {
        $totalQuery->condition($bundleField, $bundle);
      }
      $context['sandbox']['total'] = (int) $totalQuery->count()->execute();
      if ($context['sandbox']['total'] <= 0) {
        return;
      }
    }

    $currentId = $context['sandbox']['currentId'] ?? NULL;

    $query = $storage->getQuery();
    if (isset($currentId)) {
      // Fortunately this strategy works for both string and integer IDs.
      $query->condition($idField, $currentId, '>');
    }
    if ($bundleField) {
      $query->condition($bundleField, $bundle);
    }

    $ids = $query
      ->range(0, $perBatch)
      ->sort($idField, 'ASC')
      ->execute();

    foreach ($ids as $id) {
      /** @var mixed $id */
      $context['sandbox']['count']++;
      $context['sandbox']['currentId'] = $id;

      $entity = $storage->load($id);
      // Fire update event to delete and re-create occurrence values.
      $entity->{$fieldName}->postSave(TRUE);
    }

    $context['finished'] = round($context['sandbox']['count'] / $context['sandbox']['total'], 3);
    $context['message'] = \t('Processed @count of @total', [
      '@count' => $context['sandbox']['count'],
      '@total' => $context['sandbox']['total'],
    ]);
  }

}
