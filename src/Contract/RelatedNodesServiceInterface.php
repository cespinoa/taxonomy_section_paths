<?php

namespace Drupal\taxonomy_section_paths\Contract;

use Drupal\taxonomy\TermInterface;

/**
 * Apply massive update to nodes related to terms.
 */
interface RelatedNodesServiceInterface {

  /**
   * Find the nodes affected by the term alias change and propagate them.
   *
   * @param string $action
   *   Indicates whether the taxonomy term was updated or deleted.
   * @param Term $term
   *   The taxonomy term that triggered the event.
   */
  public function applyToRelatedNodes(string $action, TermInterface $term): void;

  /**
   * Loads nodes of a given bundle that reference a specific taxonomy term.
   *
   * This method queries the node storage to retrieve all nodes of the specified
   * bundle that reference the provided taxonomy term through a given entity
   * reference field.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term to match in the entity reference field.
   * @param string $bundle
   *   The content type (node bundle) to filter by.
   * @param string $field
   *   Machine name of the entity reference field pointing to the taxonomy term.
   *
   * @return \Drupal\node\NodeInterface[]
   *   An array of loaded node entities that match the criteria.
   */
  public function getNodesByBundleAndField(TermInterface $term, string $bundle, string $field): array;

  /**
   * Process alias updates or deletions for nodes related to a taxonomy term.
   *
   * @param string $action
   *   The action to perform: 'updated' or 'delete'.
   * @param array $nodes
   *   An array of NodeInterface objects related to the taxonomy term.
   * @param \Drupal\taxonomy\TermInterface|null $term
   *   The taxonomy term that has been updated or deleted.
   *   Will be NULL in case of deletion if alias regeneration is enabled.
   */
  public function processRelatedNodes(string $action, array $nodes, ?TermInterface $term): void;

}
