<?php

namespace Drupal\taxonomy_section_paths\Service;

use Drupal\path_alias\AliasRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\taxonomy_section_paths\Contract\AliasActionsServiceInterface;

/**
 * Provide actions related with alias.
 */
class AliasActionsService implements AliasActionsServiceInterface {

  public function __construct(
    protected AliasRepositoryInterface $aliasRepository,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Gets the existing alias for a given internal path and language.
   *
   * @param string $path
   *   The internal path (e.g., "/node/123" or "/taxonomy/term/45").
   * @param string $langcode
   *   The language code (e.g., "en", "es").
   *
   * @return string|null
   *   The alias if one exists, or NULL otherwise.
   */
  public function getOldAlias(string $path, string $langcode): ?string {
    $aliases = $this->entityTypeManager
      ->getStorage('path_alias')
      ->loadByProperties([
        'path' => $path,
        'langcode' => $langcode,
      ]);

    /** @var \Drupal\path_alias\Entity\PathAlias $alias */
    foreach ($aliases as $alias) {
      // Equivale a $alias->get('alias')->value;.
      return $alias->getAlias();
    }

    return NULL;
  }

  /**
   * Delete old alias.
   *
   * @param string $path
   *   Entity path (node or taxonomy_term).
   * @param string $langcode
   *   Content language.
   *
   * @return bool
   *   TRUE si se eliminó al menos un alias, FALSE en caso contrario.
   */
  public function deleteOldAlias(string $path, string $langcode): bool {
    $result = FALSE;

    $existing = $this->entityTypeManager
      ->getStorage('path_alias')
      ->loadByProperties([
        'path' => $path,
        'langcode' => $langcode,
      ]);

    foreach ($existing as $alias) {
      $alias->delete();
      $result = TRUE;
    }

    return $result;
  }

  /**
   * Saves a new alias for a path.
   *
   * If alias creation fails, the error is logged in the system log.
   *
   * @param string $path
   *   Internal path (for example, "/node/123" or "/taxonomy/term/456").
   * @param string $alias
   *   Desired public alias (for example, "/my-node").
   * @param string $langcode
   *   Language code for the content (for example, "es", "en").
   *
   * @return bool
   *   TRUE if the alias was saved successfully, FALSE if an error occurred.
   */
  public function saveNewAlias(string $path, string $alias, string $langcode): bool {
    try {
      $path_alias = PathAlias::create([
        'path' => $path,
        'alias' => $alias,
        'langcode' => $langcode,
      ]);
      $path_alias->save();
      return TRUE;
    }
    catch (EntityStorageException $e) {
      return FALSE;
    }
  }

  /**
   * Check if the proposed alias exists. If it exists add a suffix.
   *
   * @param string $base_alias
   *   The proposed alias.
   * @param string $langcode
   *   The language code of entity.
   * @param string $path
   *   Entity path.
   *
   * @return string
   *   The alias with suffix if it's need.
   */
  public function ensureUniqueAlias(string $base_alias, string $langcode, string $path): string {
    $alias = $base_alias;
    $suffix = 2;
    while ($this->aliasRepository->lookupByAlias($alias, $langcode) && $this->aliasRepository->lookupByAlias($alias, $langcode)['source'] !== $path) {
      $alias = $base_alias . '-' . $suffix;
      $suffix++;
    }
    return $alias;
  }

}
