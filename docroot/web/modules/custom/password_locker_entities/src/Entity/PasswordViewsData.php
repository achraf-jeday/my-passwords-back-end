<?php

namespace Drupal\password_locker_entities\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Password entities.
 */
class PasswordViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.
    return $data;
  }

}
