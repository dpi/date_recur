<?php

namespace Drupal\date_recur_entity_test\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;

/**
 * Defines the date recur test entity.
 *
 * @ContentEntityType(
 *   id = "dr_entity_test",
 *   label = @Translation("Date Recur Test entity"),
 *   handlers = {
 *     "list_builder" = "Drupal\entity_test\EntityTestListBuilder",
 *     "view_builder" = "Drupal\entity_test\EntityTestViewBuilder",
 *     "access" = "Drupal\entity_test\EntityTestAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\entity_test\EntityTestForm",
 *       "delete" = "Drupal\entity_test\EntityTestDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *     "translation" = "Drupal\content_translation\ContentTranslationHandler",
 *     "views_data" = "Drupal\entity_test\EntityTestViewsData"
 *   },
 *   base_table = "dr_entity_test",
 *   admin_permission = "administer entity_test content",
 *   persistent_cache = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/dr_entity_test/{dr_entity_test}",
 *     "add-form" = "/dr_entity_test/add",
 *     "edit-form" = "/dr_entity_test/manage/{dr_entity_test}/edit",
 *     "delete-form" = "/dr_entity_test/delete/entity_test/{dr_entity_test}",
 *   },
 *   field_ui_base_route = "entity.dr_entity_test.admin_form",
 * )
 */
class DrEntityTest extends EntityTest {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['dr'] = BaseFieldDefinition::create('date_recur')
      ->setLabel(t('Rule'))
      ->setDisplayConfigurable('form', TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    return $fields;
  }

}
