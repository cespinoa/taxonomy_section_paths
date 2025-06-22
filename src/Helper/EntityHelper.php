<?php

namespace Drupal\taxonomy_section_paths\Helper;

use Drupal\Core\Entity\EntityInterface;

/**
 * Helper class for safe access to original entities in mocks and production.
 */
final class EntityHelper {

  /**
   * Detects if the given entity is a PHPUnit mock.
   */
  public static function isPhpUnitMock(object $entity): bool {
    return str_contains(get_class($entity), 'MockObject');
  }

  /**
   * Gets the 'original' version of an entity safely.
   *
   * In production, $entity->original may be present.
   * In PHPUnit tests, we retrieve it via get('original').
   *
   * @param object $entity
   *   An entity object that might have an 'original' version.
   *
   * @return object|null
   *   The original entity, or NULL if not available.
   */
  public static function getSecureOriginalEntity(object $entity): ?object {
    if (self::isPhpUnitMock($entity)) {
      return $entity->get('original');
    }
    return $entity->original ?? NULL;
  }

}
