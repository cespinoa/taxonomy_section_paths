<?php

namespace Drupal\taxonomy_section_paths\Utility;

use Drupal\path_alias\AliasRepositoryInterface;
use Drupal\taxonomy_section_paths\Contract\AliasConflictResolverInterface;

/**
 * Resolves conflicts for URL aliases.
 */
class AliasConflictResolver implements AliasConflictResolverInterface {

  /**
   * Constructs the service.
   */
  public function __construct(
    protected AliasRepositoryInterface $aliasRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function ensureUniqueAlias(string $base_alias, string $langcode, string $path): string {
    $alias = $base_alias;
    $suffix = 2;

    while (TRUE) {
      $existing = $this->aliasRepository->lookupByAlias($alias, $langcode);

      if (!$existing) {
        break;
      }

      if (isset($existing['source']) && $existing['source'] === $path) {
        break;
      }

      $alias = $base_alias . '-' . $suffix;
      $suffix++;
    }

    return $alias;
  }

}
