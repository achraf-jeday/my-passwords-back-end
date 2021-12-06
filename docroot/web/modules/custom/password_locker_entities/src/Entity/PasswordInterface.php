<?php

namespace Drupal\password_locker_entities\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Password entities.
 *
 * @ingroup password_locker_entities
 */
interface PasswordInterface extends ContentEntityInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Password name.
   *
   * @return string
   *   Name of the Password.
   */
  public function getName();

  /**
   * Sets the Password name.
   *
   * @param string $name
   *   The Password name.
   *
   * @return \Drupal\password_locker_entities\Entity\PasswordInterface
   *   The called Password entity.
   */
  public function setName($name);

  /**
   * Gets the Password creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Password.
   */
  public function getCreatedTime();

  /**
   * Sets the Password creation timestamp.
   *
   * @param int $timestamp
   *   The Password creation timestamp.
   *
   * @return \Drupal\password_locker_entities\Entity\PasswordInterface
   *   The called Password entity.
   */
  public function setCreatedTime($timestamp);

}
