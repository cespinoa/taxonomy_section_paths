<?php

namespace Drupal\taxonomy_section_paths\Service;

use Drupal\taxonomy\TermInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy_section_paths\Contract\Service\PathResolverServiceInterface;
use Drupal\taxonomy_section_paths\Contract\Utility\SlugifierInterface;

use Drupal\Component\Transliteration\TransliterationInterface;

/**
 * Provide the alias for terms and nodes.
 */
class PathResolverService implements PathResolverServiceInterface {

  public function __construct(
    protected SlugifierInterface $slugifier,
  ) {}

  /**
   * Returns the full hierarchy from the root to the given term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term object for the hierarchy.
   *
   * @return array
   *   The full hierarchy. of the term.
   */
  public function getFullHierarchy(TermInterface $term): array {
    $path = [];
    $current = $term;
    while ($current instanceof TermInterface) {
      $path[] = $current->label();
      $current = $current->get('parent')->entity;
    }
    return array_reverse($path);
  }

  /**
   * Returns the full alias (e.g., "/category/subcategory") of the term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term object for the alias.
   *
   * @return string
   *   The full alias of the term.
   */
  public function getTermAliasPath(TermInterface $term): string {
    $slugs = array_map(
      fn($label) => $this->slugifier->slugify($label),
      $this->getFullHierarchy($term)
    );

    return '/' . implode('/', $slugs);
  }

  /**
   * Returns the full alias (e.g., "/category/subcategory/title") of the node.
   *
   * @param \Drupal\taxonomy\TermInterface|string|null $term_or_alias
   *   (Optional) A taxonomy term or a path used as prefix.
   * @param \Drupal\node\NodeInterface $node
   *   The node for which to generate the alias.
   *
   * @return string
   *   The alias path.
   */
  public function getNodeAliasPath(TermInterface|string|null $term_or_alias, NodeInterface $node): string {
    $prefix = '';
    if ($term_or_alias instanceof TermInterface) {
      $prefix = $this->getTermAliasPath($term_or_alias) ?? '';
    }
    elseif (is_string($term_or_alias)) {
      $prefix = $term_or_alias;
    }
    return $prefix . '/' . $this->slugifier->slugify($node->label());
  }
    


}
