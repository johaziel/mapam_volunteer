<?php

namespace Drupal\mapam_volunteer\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mapam_volunteer\PoleReferents;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the MapamVolunteerManagedPole constraint.
 */
class ManagedPoleConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    protected PoleReferents $poleReferents,
    protected AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mapam_volunteer.pole_referents'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint): void {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items */
    if ($items->isEmpty() || $this->currentUser->hasPermission(PoleReferents::BYPASS_PERMISSION)) {
      return;
    }
    foreach ($items->referencedEntities() as $delta => $pole) {
      if ($pole instanceof NodeInterface && !$this->poleReferents->isPoleReferent($pole, $this->currentUser)) {
        $this->context->buildViolation($constraint->message, ['%pole' => $pole->label()])
          ->atPath((string) $delta)
          ->addViolation();
      }
    }
  }

}
