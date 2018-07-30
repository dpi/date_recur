<?php

namespace Drupal\Tests\date_recur\Kernel;

use Drupal\date_recur\Plugin\DateRecurOccurrenceHandler\DefaultDateRecurOccurrenceHandler;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests occurrence tables.
 *
 * @group date_recur
 */
class DateRecurOccurrenceTableTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('entity_test');
  }

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
   * Ensure occurrence table is created and deleted for field storage entities.
   */
  public function testTableCreateDeleteOnFieldStorageCreate() {
    $tableName = 'date_recur__entity_test__abc';

    $actualExists = $this->container->get('database')
      ->schema()
      ->tableExists($tableName);
    $this->assertFalse($actualExists);

    $fieldStorage = FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'abc',
      'type' => 'date_recur',
      'settings' => [
        'datetime_type' => DateRecurItem::DATETIME_TYPE_DATETIME,
        'occurrence_handler_plugin' => 'date_recur_occurrence_handler',
      ],
    ]);
    $fieldStorage->save();

    $actualExists = $this->container->get('database')
      ->schema()
      ->tableExists($tableName);
    $this->assertTrue($actualExists);

    $fieldStorage->delete();

    $actualExists = $this->container->get('database')
      ->schema()
      ->tableExists($tableName);
    $this->assertFalse($actualExists);
  }

  /**
   * Ensure occurrence table rows are created.
   */
  public function testTableRows() {
    $fieldStorage = FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'abc',
      'type' => 'date_recur',
      'settings' => [
        'datetime_type' => DateRecurItem::DATETIME_TYPE_DATETIME,
        'occurrence_handler_plugin' => 'date_recur_occurrence_handler',
      ],
    ]);
    $fieldStorage->save();

    $preCreate = 'P1Y';
    $fieldConfig = FieldConfig::create([
      'field_name' => 'abc',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'settings' => [
        'precreate' => $preCreate,
      ],
    ]);
    $fieldConfig->save();

    $entity = EntityTest::create();
    $entity->abc = [
      'value' => '2014-06-15T23:00:00',
      'end_value' => '2014-06-16T07:00:00',
      'rrule' => 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR',
      'infinite' => '1',
      'timezone' => 'Australia/Sydney',
    ];
    $entity->save();

    // Calculate number of weekdays between first occurence and end of precreate
    // interval.
    $day = new \DateTime('2014-06-15T23:00:00');
    $until = new \DateTime('now');
    $until->add(new \DateInterval($preCreate));
    // See BYDAY above.
    $countDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
    $count = 0;
    while ($day < $until) {
      if (in_array($day->format('D'), $countDays)) {
        $count++;
      }
      $day->modify('+1 day');
    }

    $tableName = 'date_recur__entity_test__abc';
    $actualCount = $this->container->get('database')
      ->select($tableName)
      ->countQuery()
      ->execute()
      ->fetchField();
    // Make sure more than zero rows created.
    $this->assertGreaterThan(0, $actualCount);
    $this->assertEquals($count, $actualCount);
  }

  /**
   * Test table name generator.
   */
  public function testGetOccurrenceCacheStorageTableName() {
    $fieldStorage = FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'foo',
      'type' => 'date_recur',
    ]);
    $actual = DefaultDateRecurOccurrenceHandler::getOccurrenceCacheStorageTableName($fieldStorage);
    $this->assertEquals('date_recur__entity_test__foo', $actual);
  }

}
