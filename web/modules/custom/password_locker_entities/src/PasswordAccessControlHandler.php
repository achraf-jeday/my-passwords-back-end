<?php

namespace Drupal\password_locker_entities;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Password entity.
 *
 * @see \Drupal\password_locker_entities\Entity\Password.
 */
class PasswordAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\password_locker_entities\Entity\PasswordInterface $entity */

    switch ($operation) {
      case 'view':
        $access = AccessResult::allowedIfHasPermission($account, 'view any password entities');
        if (!$access->isAllowed() && $account->hasPermission('view own password entities')) {
          $access = $access->orIf(AccessResult::allowedIf($account->id() == $entity->getOwnerId())->cachePerUser()->addCacheableDependency($entity));
        }
        break;

      case 'update':
        $access = AccessResult::allowedIfHasPermission($account, 'edit any password entities');
        if (!$access->isAllowed() && $account->hasPermission('edit own password entities')) {
          $access = $access->orIf(AccessResult::allowedIf($account->id() == $entity->getOwnerId())->cachePerUser()->addCacheableDependency($entity));
        }
        break;

      case 'delete':
        $access =  AccessResult::allowedIfHasPermission($account, 'delete any password entities');
        if (!$access->isAllowed() && $account->hasPermission('delete own password entities')) {
          $access = $access->orIf(AccessResult::allowedIf($account->id() == $entity->getOwnerId())->cachePerUser()->addCacheableDependency($entity));
        }
        break;

      // Unknown operation, no opinion.
      default:
        $access = AccessResult::neutral();
    }

    return $access;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add password entities');
  }


}
