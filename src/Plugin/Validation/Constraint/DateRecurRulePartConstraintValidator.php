<?php

namespace Drupal\date_recur\Plugin\Validation\Constraint;

use Drupal\date_recur\DateRecurPartGrid;
use Drupal\date_recur\Exception\DateRecurRulePartIncompatible;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the DateRecurRulePartConstraint constraint.
 */
class DateRecurRulePartConstraintValidator extends ConstraintValidator {

  /**
   * Labels for frequencies.
   *
   * @var array|null
   */
  protected $frequencyLabels = NULL;

  /**
   * Labels for parts.
   *
   * @var array|null
   */
  protected $partLabels = NULL;

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem $value */
    /** @var \Drupal\date_recur\Plugin\Validation\Constraint\DateRecurRulePartConstraint $constraint */
    $definition = $value->getFieldDefinition();
    $partSettings = $definition->getSetting('parts');
    $grid = DateRecurPartGrid::configSettingsToGrid($partSettings);

    // Catch exceptions thrown by invalid rules.
    try {
      $helper = $value->getHelper();
    }
    catch (\Exception $e) {
      $this->context->addViolation($constraint->invalidRrule);
      return;
    }

    foreach ($helper->getRules() as $rule) {
      $frequency = $rule->getFrequency();
      // Check if a frequency is supported.
      if (!$grid->isFrequencySupported($frequency)) {
        $frequencyLabels = $this->getFrequencyLabels();
        $frequencyLabel = isset($frequencyLabels[$frequency]) ? $frequencyLabels[$frequency] : $frequency;
        $this->context->addViolation($constraint->disallowedFrequency, ['%frequency' => $frequencyLabel]);
      }

      $parts = $rule->getParts();
      unset($parts['FREQ']);
      foreach (array_keys($parts) as $part) {
        try {
          // Check if a part is supported.
          if (!$grid->isPartSupported($frequency, $part)) {
            $partLabels = $this->getPartLabels();
            $partLabel = isset($partLabels[$part]) ? $partLabels[$part] : $part;
            $this->context->addViolation($constraint->disallowedPart, ['%part' => $partLabel]);
          }
        }
        catch (DateRecurRulePartIncompatible $e) {
          // If a part is incompatible, add a violation.
          $frequencyLabels = $this->getFrequencyLabels();
          $frequencyLabel = isset($frequencyLabels[$frequency]) ? $frequencyLabels[$frequency] : $frequency;
          $partLabels = $this->getPartLabels();
          $partLabel = isset($partLabels[$part]) ? $partLabels[$part] : $part;
          $this->context->addViolation($constraint->incompatiblePart, [
            '%frequency' => $frequencyLabel,
            '%part' => $partLabel,
          ]);
        }
      }
    }
  }

  /**
   * Labels for frequencies.
   *
   * @return array
   *   Labels for frequencies keyed by part.
   */
  protected function getFrequencyLabels() {
    if (!isset($this->frequencyLabels)) {
      $this->frequencyLabels = DateRecurPartGrid::frequencyLabels();
    }
    return $this->frequencyLabels;
  }

  /**
   * Labels for parts.
   *
   * @return array
   *   Labels for parts keyed by part.
   */
  protected function getPartLabels() {
    if (!isset($this->partLabels)) {
      $this->partLabels = DateRecurPartGrid::partLabels();
    }
    return $this->partLabels;
  }

}
