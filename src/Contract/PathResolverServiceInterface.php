<?php

namespace Drupal\taxonomy_section_paths\Contract;

use Drupal\taxonomy\TermInterface;
use Drupal\node\NodeInterface;

/**
 * Provide the alias for terms and nodes.
 */
interface PathResolverServiceInterface {

  /**
   * Returns the full hierarchy from the root to the given term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term object for the hierarchy.
   *
   * @return array
   *   The full hierarchy. of the term.
   */
  public function getFullHierarchy(TermInterface $term): array;

  /**
   * Returns the full alias (e.g., "/category/subcategory") of the term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term object for the alias.
   *
   * @return string
   *   The full alias of the term.
   */
  public function getTermAliasPath(TermInterface $term): string;

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
  public function getNodeAliasPath(?TermInterface $term, NodeInterface $node): string;

}
