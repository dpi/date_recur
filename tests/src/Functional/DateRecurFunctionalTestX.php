<?php

namespace Drupal\Tests\date_recur\Functional;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\BrowserTestBase;

/**
 * Testststestsetset
 *
 * @group date_recur
 */
class DateRecurFunctionalTest extends WebDriverTestBase {


  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'date_recur',
    'field_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser([
      'administer entity_test fields',
    ]));
  }

  /**
   * Tests
   */
  public function testXyzDef() {

    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'abc',
      'type' => 'date_recur',
    ]);
    $field_storage->save();

    $field = [
      'field_name' => 'abc',
      'label' => 'abc',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ];
    \Drupal\field\Entity\FieldConfig::create($field)->save();

    $this->drupalGet('entity_test/structure/entity_test/fields');

    $this->assertEquals(1,1);
  }

}
