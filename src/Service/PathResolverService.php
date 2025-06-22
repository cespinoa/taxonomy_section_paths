<?php

namespace Drupal\taxonomy_section_paths\Service;

use Drupal\taxonomy\TermInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasRepositoryInterface;
use Drupal\taxonomy_section_paths\Contract\PathResolverServiceInterface;
use Drupal\taxonomy_section_paths\Contract\SlugifierInterface;

use Drupal\Component\Transliteration\TransliterationInterface;

/**
 * Provide the alias for terms and nodes.
 */
class PathResolverService implements PathResolverServiceInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TransliterationInterface $transliteration,
    protected AliasRepositoryInterface $aliasRepository,
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
   * @param \Drupal\taxonomy\TermInterface|null $term
   *   (Optional) A taxonomy term used as prefix.
   * @param \Drupal\node\NodeInterface $node
   *   The node for which to generate the alias.
   *
   * @return string
   *   The alias path.
   */
  public function getNodeAliasPath(?TermInterface $term, NodeInterface $node): string {
    $prefix = $term ? $this->getTermAliasPath($term) : '';
    return $prefix . '/' . $this->slugifier->slugify($node->label());
  }

}
