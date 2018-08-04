<?php

namespace Drupal\Tests\date_recur\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\date_recur\DateRange;
use Drupal\date_recur\DateRecurHelper;
use Drupal\date_recur\DateRecurUtility;
use Drupal\Tests\UnitTestCase;

/**
 * Date recur tests.
 *
 * @coversDefaultClass \Drupal\date_recur\DateRecurHelper
 * @group date_recur
 */
class DateRecurRruleUnitTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // DrupalDateTime wants to access the language manager.
    $languageManager = $this->getMockForAbstractClass(LanguageManagerInterface::class);
    $languageManager->expects($this->any())
      ->method('getCurrentLanguage')
      ->will($this->returnValue(new Language(['id' => 'en'])));

    $container = new ContainerBuilder();
    $container->set('language_manager', $languageManager);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Test timezone.
   *
   * @param \DateTimeZone $tz
   *   A timezone for testing.
   *
   * @dataProvider providerTimezone
   */
  public function testTz(\DateTimeZone $tz) {
    $start = new \DateTime('11pm 7 June 2005', $tz);
    $rule = $this->newRule('FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR;INTERVAL=1', $start);

    // Test new method.
    $results = $rule->getOccurrences(
      NULL,
      NULL,
      1
    );
    $this->assertInstanceOf(\DateTimeInterface::class, $results[0]->getStart());
    $this->assertTrue($start == $results[0]->getStart());
  }

  /**
   * Data provider for ::testTz.
   */
  public function providerTimezone() {
    $data[] = [new \DateTimeZone('America/Los_Angeles')];
    $data[] = [new \DateTimeZone('UTC')];
    $data[] = [new \DateTimeZone('Australia/Sydney')];
    return $data;
  }
//
//  /**
//   * Tests get parts method.
//   *
//   * @covers ::getParts
//   */
//  public function testGetParts() {
//    $start = new \DateTime('11pm 7 June 2005', new \DateTimeZone('America/Los_Angeles'));
//    $rule = $this->newRule('FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR;INTERVAL=1', $start);
//    $assertActual = [
//      'FREQ' => 'WEEKLY',
//      'DTSTART' => $start,
//      'BYDAY' => 'MO,TU,WE,TH,FR',
//      'INTERVAL' => 1,
//    ];
//    $this->assertArrayEquals($assertActual, $rule->getParts());
//  }

  /**
   * Tests common methods.
   */
//  public function testProxy() {
//    $start = new \DateTime('11pm 7 June 2005', new \DateTimeZone('America/Los_Angeles'));
//    $rule = $this->newRule('FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR;INTERVAL=1', $start);
//    $this->assertTrue($rule->isInfinite());
////    $this->assertTrue($start == $rule->getStartDate());
////    $this->assertEquals('Every week on Monday, Tuesday, Wednesday, Thursday and Friday at 23:00', (string) $rule->humanReadable());
//    $rule = $this->newRule('FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR;INTERVAL=1;COUNT=2', $start);
//    $this->assertFalse($rule->isInfinite());
//    // @todo many of these proxy should just do asserts on DateRecurDefaultRSet
//
////    $tzString = 'Indian/Maldives';
////    $date = new \DateTime('11pm 7 June 2005', new \DateTimeZone($tzString));
////    $rule = $this->newRule('FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR;INTERVAL=1', $date);
////    $this->assertEquals($tzString, $rule->getTimezone()->getName());
//  }
//
//  /**
//   * Tests get weekdays method.
//   *
//   * @covers ::getWeekdays
//   */
//  public function testGetWeekdays() {
//    $start = new \DateTime('11pm 7 June 2005', new \DateTimeZone('America/Los_Angeles'));
//    $rule = $this->newRule('FREQ=WEEKLY;BYDAY=MO,WE,TH,FR,SU', $start);
//    $actualWeekdays = [
//      1 => 'MO',
//      3 => 'WE',
//      4 => 'TH',
//      5 => 'FR',
//      7 => 'SU',
//    ];
//    $this->assertEquals($actualWeekdays, $rule->getWeekdays());
//    // Test ordering and de-duplication.
//    $rule2 = $this->newRule('FREQ=WEEKLY;BYDAY=MO,FR,WE,WE,WE,SU,WE,TH', $start);
//    $this->assertEquals($actualWeekdays, $rule2->getWeekdays());
//  }

  /**
   * Tests list.
   *
   * @covers ::generateOccurrences
   */
  public function testGenerateOccurrences() {
    $tz = new \DateTimeZone('Africa/Cairo');
    $start = new \DateTime('11pm 7 June 2005', $tz);
    $end = (clone $start)->modify('+2 hours');
    $rule = $this->newRule('FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR', $start, $end);

    $generator = $rule->generateOccurrences();
    $this->assertTrue($generator instanceof \Generator);

    $assertOccurrences = [
      [
        new \DateTime('11pm 7 June 2005', $tz),
        new \DateTime('1am 8 June 2005', $tz),
      ],
      [
        new \DateTime('11pm 8 June 2005', $tz),
        new \DateTime('1am 9 June 2005', $tz),
      ],
      [
        new \DateTime('11pm 9 June 2005', $tz),
        new \DateTime('1am 10 June 2005', $tz),
      ],
      [
        new \DateTime('11pm 10 June 2005', $tz),
        new \DateTime('1am 11 June 2005', $tz),
      ],
      [
        new \DateTime('11pm 13 June 2005', $tz),
        new \DateTime('1am 14 June 2005', $tz),
      ],
      [
        new \DateTime('11pm 14 June 2005', $tz),
        new \DateTime('1am 15 June 2005', $tz),
      ],
      [
        new \DateTime('11pm 15 June 2005', $tz),
        new \DateTime('1am 16 June 2005', $tz),
      ],
    ];

    // Iterate over it a bit, because this is an infinite RRULE it will go
    // forever.
    $iterationCount = 0;
    $maxIterations = count($assertOccurrences);
    foreach ($generator as $occurrence) {
      $this->assertTrue($occurrence instanceof DateRange);

      [$assertStart, $assertEnd] = $assertOccurrences[$iterationCount];
      $this->assertTrue($assertStart == $occurrence->getStart());
      $this->assertTrue($assertEnd == $occurrence->getEnd());

      $iterationCount++;
      if ($iterationCount >= $maxIterations) {
        break;
      }
    }
    $this->assertEquals($maxIterations, $iterationCount);
  }

  /**
   * Tests human readable.
   *
   * @deprecated will remove.
   */
    public function testHumanReadable() {
      $start = new \DateTime('11pm 7 June 2005', new \DateTimeZone('America/Los_Angeles'));
      $rrule = 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR;INTERVAL=1';
      $rule = \Drupal\date_recur\Rl\RlHelper::createInstance($rrule, $start);
      $this->assertEquals('Every week on Monday, Tuesday, Wednesday, Thursday and Friday at 23:00', (string) $rule->getRlRuleset()->humanReadable());
    }

  /**
   * Constructs a new DateRecurHelper object.
   *
   * @param string $rrule
   *   The repeat rule.
   * @param \DateTime $startDate
   *   The initial occurrence start date.
   * @param \DateTime|null $startDateEnd
   *   The initial occurrence end date, or NULL to use start date.
   *
   * @return \Drupal\date_recur\DateRecurHelper
   *   A new DateRecurHelper object.
   */
  protected function newRule($rrule, \DateTime $startDate, \DateTime $startDateEnd = NULL) {
    return DateRecurHelper::create($rrule, $startDate, $startDateEnd);
  }

}
