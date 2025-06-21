<?php

namespace Drupal\taxonomy_section_paths\Contract;

/**
 * Provides a service to resolve alias conflicts.
 */
interface AliasConflictResolverInterface {

  /**
   * Ensures that the alias is unique in the system.
   *
   * If the alias already exists and points to a different path, this method
   * appends a numeric suffix (-2, -3, etc.) until it finds a unique alias.
   *
   * @param string $base_alias
   *   The initial alias to check.
   * @param string $langcode
   *   The language code for which the alias applies.
   * @param string $path
   *   The system path the alias is intended to point to (e.g., /node/5).
   *
   * @return string
   *   A unique alias string.
   */
  public function ensureUniqueAlias(string $base_alias, string $langcode, string $path): string;

}
