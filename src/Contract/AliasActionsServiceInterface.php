<?php

namespace Drupal\taxonomy_section_paths\Contract;

/**
 * Interface actions related with alias.
 */
interface AliasActionsServiceInterface {

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
  public function getOldAlias(string $path, string $langcode): ?string;

  /**
   * Delete old alias.
   *
   * @param string $path
   *   Entity path (node or taxonomy_term).
   * @param string $langcode
   *   Content language.
   *
   * @return bool
   *   TRUE if delete, FALSE otherwise.
   */
  public function deleteOldAlias(string $path, string $langcode): bool;

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
  public function saveNewAlias(string $path, string $alias, string $langcode): bool;

}
