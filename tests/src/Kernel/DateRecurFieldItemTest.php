<?php

namespace Drupal\Tests\date_recur\Kernel;

use Drupal\date_recur\Exception\DateRecurHelperArgumentException;
use Drupal\date_recur_entity_test\Entity\DrEntityTest;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests date_recur field.
 *
 * @group date_recur
 * @coversDefaultClass \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem
 */
class DateRecurFieldItemTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'date_recur_entity_test',
    'entity_test',
    'datetime',
    'datetime_range',
    'date_recur',
    'field',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('dr_entity_test');
  }

  /**
   * Tests infinite flag is set if an infinite RRULE is set.
   */
  public function testInfinite() {
    $entity = DrEntityTest::create();
    $entity->dr = [
      [
        'value' => '2008-06-16T00:00:00',
        'end_value' => '2008-06-16T06:00:00',
        'rrule' => 'FREQ=DAILY',
        'timezone' => 'Australia/Sydney',
      ],
    ];
    $entity->save();
    $this->assertTrue($entity->dr[0]->infinite === TRUE);
  }

  /**
   * Tests infinite flag is set if an non-infinite RRULE is set.
   */
  public function testNonInfinite() {
    $entity = DrEntityTest::create();
    $entity->dr = [
      [
        'value' => '2008-06-16T00:00:00',
        'end_value' => '2008-06-16T06:00:00',
        'rrule' => 'FREQ=DAILY;COUNT=100',
        'timezone' => 'Australia/Sydney',
      ],
    ];
    $entity->save();
    $this->assertTrue($entity->dr[0]->infinite === FALSE);
  }

  /**
   * Tests no violations when time zone is recognised by PHP.
   */
  public function testTimeZoneConstraintValid() {
    $entity = DrEntityTest::create();
    $entity->dr = [
      'value' => '2014-06-15T23:00:00',
      'end_value' => '2014-06-16T07:00:00',
      'rrule' => 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR;COUNT=3',
      'infinite' => '0',
      'timezone' => 'Australia/Sydney',
    ];

    /** @var \Symfony\Component\Validator\ConstraintViolationListInterface $violations */
    $violations = $entity->dr->validate();
    $this->assertEquals(0, $violations->count());
  }

  /**
   * Tests violations when time zone is not a recognised by PHP.
   */
  public function testTimeZoneConstraintInvalidZone() {
    $entity = DrEntityTest::create();
    $entity->dr = [
      'value' => '2014-06-15T23:00:00',
      'end_value' => '2014-06-16T07:00:00',
      'rrule' => 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR;COUNT=3',
      'infinite' => '0',
      'timezone' => 'Mars/Mariner',
    ];

    /** @var \Symfony\Component\Validator\ConstraintViolationListInterface $violations */
    $violations = $entity->dr->validate();
    $this->assertEquals(1, $violations->count());

    $violation = $violations->get(0);
    $message = (string) $violation->getMessage();
    $this->assertEquals('<em class="placeholder">Mars/Mariner</em> is not a valid time zone.', $message);
  }

  /**
   * Tests violations when time zone is not a string.
   */
  public function testTimeZoneConstraintInvalidFormat() {
    $entity = DrEntityTest::create();
    $entity->dr = [
      'value' => '2014-06-15T23:00:00',
      'end_value' => '2014-06-16T07:00:00',
      'rrule' => 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR;COUNT=3',
      'infinite' => '0',
      'timezone' => new \StdClass(),
    ];

    /** @var \Symfony\Component\Validator\ConstraintViolationListInterface $violations */
    $violations = $entity->dr->validate();
    $this->assertEquals(1, $violations->count());

    $violation = $violations->get(0);
    $message = (string) $violation->getMessage();
    $this->assertEquals('This value should be of the correct primitive type.', $message);
  }

  /**
   * Test exception thrown if time zone is missing when getting a item helper.
   */
  public function testTimeZoneMissing() {
    $entity = DrEntityTest::create();
    $entity->dr = [
      [
        'value' => '2008-06-16T00:00:00',
        'end_value' => '2008-06-16T06:00:00',
        'rrule' => 'FREQ=DAILY;COUNT=100',
        'timezone' => '',
      ],
    ];
    $this->setExpectedException(DateRecurHelperArgumentException::class, 'Invalid time zone');
    $entity->dr[0]->getHelper();
  }

  /**
   * Test exception thrown for invalid time zones when getting a item helper.
   */
  public function testTimeZoneInvalid() {
    $entity = DrEntityTest::create();
    $entity->dr = [
      [
        'value' => '2008-06-16T00:00:00',
        'end_value' => '2008-06-16T06:00:00',
        'rrule' => 'FREQ=DAILY;COUNT=100',
        'timezone' => 'Mars/Mariner',
      ],
    ];
    $this->setExpectedException(DateRecurHelperArgumentException::class, 'Invalid time zone');
    $entity->dr[0]->getHelper();
  }

}
