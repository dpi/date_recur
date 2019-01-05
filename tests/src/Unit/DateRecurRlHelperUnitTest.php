<?php

namespace Drupal\Tests\date_recur\Unit;

use Drupal\date_recur\Exception\DateRecurHelperArgumentException;
use Drupal\date_recur\Rl\RlHelper;
use Drupal\Tests\UnitTestCase;

/**
 * Tests Rlanvin implementation of helper.
 *
 * @coversDefaultClass \Drupal\date_recur\Rl\RlHelper
 * @group date_recur
 *
 * @ingroup RLanvinPhpRrule
 */
class DateRecurRlHelperUnitTest extends UnitTestCase {

  /**
   * Test occurrence generation with range limiters.
   *
   * @covers ::getOccurrences
   * @covers ::generateOccurrences
   */
  public function testOccurrence() {
    $helper = $this->createHelper(
      'FREQ=DAILY;COUNT=1',
      new \DateTime('2am 14 April 2014'),
      new \DateTime('4am 14 April 2014')
    );

    // Test out of range (before).
    $occurrences = $helper->getOccurrences(
      new \DateTime('1am 14 April 2014'),
      new \DateTime('1:30am 14 April 2014')
    );
    $this->assertCount(0, $occurrences);

    // Test out of range (after).
    $occurrences = $helper->getOccurrences(
      new \DateTime('4:30am 14 April 2014'),
      new \DateTime('5am 14 April 2014')
    );
    $this->assertCount(0, $occurrences);

    // Test in range (intersects occurrence start).
    $occurrences = $helper->getOccurrences(
      new \DateTime('1am 14 April 2014'),
      new \DateTime('3am 14 April 2014')
    );
    $this->assertCount(1, $occurrences);

    // Test in range (exact).
    $occurrences = $helper->getOccurrences(
      new \DateTime('2am 14 April 2014'),
      new \DateTime('4am 14 April 2014')
    );
    $this->assertCount(1, $occurrences);

    // Test in range (within).
    $occurrences = $helper->getOccurrences(
      new \DateTime('2:30am 14 April 2014'),
      new \DateTime('3:30am 14 April 2014')
    );
    $this->assertCount(1, $occurrences);

    // Test in range (intersects occurrence end).
    $occurrences = $helper->getOccurrences(
      new \DateTime('3am 14 April 2014'),
      new \DateTime('5am 14 April 2014')
    );
    $this->assertCount(1, $occurrences);

    // Test in range but zero limit.
    $occurrences = $helper->getOccurrences(
      new \DateTime('1am 14 April 2014'),
      new \DateTime('3am 14 April 2014'),
      0
    );
    $this->assertCount(0, $occurrences);
  }

  /**
   * Tests invalid argument for limit.
   */
  public function testInvalidLimit() {
    $helper = $this->createHelper(
      'FREQ=DAILY;COUNT=10',
      new \DateTime('2am 14 April 2014'),
      new \DateTime('4am 14 April 2014')
    );

    $this->setExpectedException(\InvalidArgumentException::class, 'Invalid count limit.');
    $helper->getOccurrences(
      new \DateTime('1am 14 April 2014'),
      new \DateTime('3am 14 April 2014'),
      -1
    );
  }

  /**
   * Tests frequency method of rules returned by helper.
   */
  public function testFrequency() {
    $dtStart = new \DateTime('9am 16 June 2014');
    $rrule = 'FREQ=DAILY;COUNT=10';
    $instance = $this->createHelper($rrule, $dtStart);

    $rules = $instance->getRules();
    $this->assertCount(1, $rules);
    $rule = $rules[0];
    $this->assertEquals('DAILY', $rule->getFrequency());
  }

  /**
   * Tests parts that were not passed originally, are not returned.
   */
  public function testRedundantPartsOmitted() {
    $dtStart = new \DateTime('9am 16 June 2014');
    $rrule = 'FREQ=DAILY;COUNT=10';
    $instance = $this->createHelper($rrule, $dtStart);

    $rules = $instance->getRules();
    $this->assertCount(1, $rules);
    $rule = $rules[0];

    $parts = $rule->getParts();
    // Rlanvin/rrule will return parts: 'DTSTART', 'FREQ', 'COUNT', 'INTERVAL',
    // 'WKST'. However we just need to test completely unrelated parts such as
    // BYMONTHDAY etc arn't returned here.
    $this->assertArrayHasKey('DTSTART', $parts);
    $this->assertArrayHasKey('COUNT', $parts);
    $this->assertArrayNotHasKey('BYMONTHDAY', $parts);
  }

  /**
   * Tests where a multiline rule without is missing the type prefix.
   */
  public function testMultilineMissingColon() {
    $rrule = 'RRULE:FREQ=DAILY;BYDAY=MO,TU,WE,TH,FR;COUNT=3
EXDATE:19960402T010000Z
foobar';

    $this->setExpectedException(DateRecurHelperArgumentException::class, 'Multiline RRULE must be prefixed with either: RRULE, EXDATE, EXRULE, or RDATE. Missing for line 3');
    $this->createHelper($rrule, new \DateTime());
  }

  /**
   * Creates a new helper.
   *
   * Uses same arguments as
   * \Drupal\date_recur\DateRecurHelperInterface::createInstance.
   *
   * @param string $string
   *   The repeat rule.
   * @param \DateTimeInterface $dtStart
   *   The initial occurrence start date.
   * @param \DateTimeInterface|null $dtStartEnd
   *   The initial occurrence end date, or NULL to use start date.
   *
   * @return \Drupal\date_recur\DateRecurHelperInterface
   *   A new date recur helper instance.
   *
   * @see \Drupal\date_recur\DateRecurHelperInterface::createInstance
   */
  protected function createHelper($string, \DateTimeInterface $dtStart, \DateTimeInterface $dtStartEnd = NULL) {
    // @todo convert to splat for PHP5.6/7 version upgrade.
    return RlHelper::createInstance($string, $dtStart, $dtStartEnd);
  }

}
