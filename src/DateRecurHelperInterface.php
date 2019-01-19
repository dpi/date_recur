<?php

declare(strict_types = 1);

namespace Drupal\date_recur;

/**
 * Interface for recurring rule helper.
 *
 * @method DateRange current()
 */
interface DateRecurHelperInterface extends \Iterator {

  /**
   * Create a new instance.
   *
   * @param string $string
   *   The repeat rule.
   * @param \DateTimeInterface $dtStart
   *   The initial occurrence start date.
   * @param \DateTimeInterface|null $dtStartEnd
   *   The initial occurrence end date, or NULL to use start date.
   *
   * @return \Drupal\date_recur\DateRecurHelperInterface
   *   An instance of helper.
   */
  public static function createInstance(string $string, \DateTimeInterface $dtStart, ?\DateTimeInterface $dtStartEnd = NULL): DateRecurHelperInterface;

  /**
   * Get the rules that comprise this helper.
   *
   * @return \Drupal\date_recur\DateRecurRuleInterface[]
   *   The rules that comprise this helper.
   */
  public function getRules(): array;

  /**
   * Determine whether this is infinite.
   *
   * @return bool
   *   Whether this is infinite.
   */
  public function isInfinite(): bool;

  /**
   * Calculates occurrences as a generator.
   *
   * @param \DateTimeInterface|null $rangeStart
   *   The start of the range, or start with the first available occurrence.
   * @param \DateTimeInterface|null $rangeEnd
   *   The end of the range, or never end.
   *
   * @return \Generator|\Drupal\date_recur\DateRange[]
   *   A date range generator.
   */
  public function generateOccurrences(?\DateTimeInterface $rangeStart = NULL, ?\DateTimeInterface $rangeEnd = NULL): \Generator;

  /**
   * Get all occurrences.
   *
   * A limit and/or range-end must be passed.
   *
   * @param \DateTimeInterface|null $rangeStart
   *   The start of the range, or start with the first available occurrence.
   * @param \DateTimeInterface|null $rangeEnd
   *   The end of the range.
   * @param int|null $limit
   *   A limit.
   *
   * @return \Drupal\date_recur\DateRange[]
   *   The occurrences.
   *
   * @throws \InvalidArgumentException
   *   Exceptions thrown if ranges are invalid or undefined.
   */
  public function getOccurrences(\DateTimeInterface $rangeStart = NULL, ?\DateTimeInterface $rangeEnd = NULL, ?int $limit = NULL): array;

}
