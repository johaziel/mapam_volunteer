<?php

namespace Drupal\mapam_volunteer\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that the current user is a referent of the referenced poles.
 */
#[Constraint(
  id: 'MapamVolunteerManagedPole',
  label: new TranslatableMarkup('Pole managed by the current user', [], ['context' => 'Validation']),
)]
class ManagedPoleConstraint extends SymfonyConstraint {

  /**
   * The violation message.
   *
   * @var string
   */
  public $message = 'You are not a referent of the pole %pole.';

}
