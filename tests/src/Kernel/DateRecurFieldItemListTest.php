<?php

namespace Drupal\Tests\date_recur\Kernel;

use Drupal\date_recur\DateRange;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests date_recur field lists.
 *
 * Tests the default occurrence property definition.
 *
 * @group date_recur
 * @coversDefaultClass \Drupal\date_recur\Plugin\Field\DateRecurOccurrencesComputed
 * @covers \Drupal\date_recur\Plugin\DateRecurOccurrenceHandler\DateRecurRlOccurrenceHandler::occurrencePropertyDefinition
 */
class DateRecurFieldItemListTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'datetime',
    'datetime_range',
    'date_recur',
    'field',
    'user',
  ];

  /**
   * Entity for testing.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'foo',
      'type' => 'date_recur',
      'settings' => [
        'datetime_type' => DateRecurItem::DATETIME_TYPE_DATETIME,
        'occurrence_handler_plugin' => 'date_recur_occurrence_handler',
      ],
    ]);
    $field_storage->save();

    $field = [
      'field_name' => 'foo',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ];
    FieldConfig::create($field)->save();

    // @todo convert to base field, attach field not required to test.
    $this->entity = EntityTest::create();
  }

  /**
   * Tests list.
   */
  public function testList() {
    $this->entity->foo = [
      'value' => '2014-06-15T23:00:00',
      'end_value' => '2014-06-16T07:00:00',
      'rrule' => 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR',
      'infinite' => '1',
      'timezone' => 'Australia/Sydney',
    ];

    $this->assertTrue($this->entity->foo->occurrences instanceof \Generator);
    // Iterate over it a bit, because this is an infinite RRULE it will go
    // forever.
    $iterationCount = 0;
    $maxIterations = 7;
    foreach ($this->entity->foo->occurrences as $occurrence) {
      $this->assertTrue($occurrence instanceof DateRange);
      $iterationCount++;
      if ($iterationCount >= $maxIterations) {
        break;
      }
    }
    $this->assertEquals($maxIterations, $iterationCount);
  }

}
