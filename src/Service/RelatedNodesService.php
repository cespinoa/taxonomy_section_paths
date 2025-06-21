<?php

namespace Drupal\taxonomy_section_paths\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\taxonomy_section_paths\Contract\PathResolverServiceInterface;
use Drupal\taxonomy_section_paths\Contract\AliasActionsServiceInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy_section_paths\Contract\AliasMessageLoggerInterface;
use Drupal\taxonomy_section_paths\Contract\RelatedNodesServiceInterface;

/**
 * Apply massive update to nodes related to terms.
 */
class RelatedNodesService implements RelatedNodesServiceInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected PathResolverServiceInterface $resolver,
    protected AliasActionsServiceInterface $aliasActions,
    protected AliasMessageLoggerInterface $messageLogger,
  ) {}

  /**
   * Find the nodes affected by the term alias change and propagate them.
   *
   * @param string $action
   *   Indicates whether the taxonomy term was updated or deleted.
   * @param Term $term
   *   The taxonomy term that triggered the event.
   */
  public function applyToRelatedNodes(string $action, TermInterface $term): void {
    $bundles = $this->configFactory->get('taxonomy_section_paths.settings')->get('bundles');

    foreach ($bundles as $bundle => $data) {
      $field = $data['field'];
      $nodes = $this->getNodesByBundleAndField($term, $bundle, $field);

      if (!empty($nodes)) {
        $this->processRelatedNodes($action, $nodes, $term);
      }
    }
  }

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
  public function getNodesByBundleAndField(TermInterface $term, string $bundle, string $field): array {
    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition($field . '.target_id', $term->id());

    $nids = $query->execute();

    return $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
  }

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
  public function processRelatedNodes(string $action, array $nodes, ?TermInterface $term): void {
    // Determine if we are updating or deleting aliases.
    $is_update = ($action === 'update');
    $config = $this->configFactory->get('taxonomy_section_paths.settings');
    $replace_on_delete = (bool) $config->get('generate_node_alias_if_term_empty');
    $action_msg = $replace_on_delete ? 'insert' : 'update';

    // If action is delete and config allows regenerating, nullify the term.
    $term = $action === 'delete' ? NULL : $term;

    foreach ($nodes as $node) {
      $path = '/node/' . $node->id();
      $langcode = $node->language()->getId();

      $old_alias = $this->aliasActions->getOldAlias($path, $langcode);

      if ($this->aliasActions->deleteOldAlias($path, $langcode) &&
        !$is_update && !$replace_on_delete
        ) {
        $this->messageLogger->logOperation('delete_without_new_alias', 'node', $node->id(), $node->label(), NULL, $old_alias);
      }

      if ($is_update || $replace_on_delete) {
        $new_alias = $this->resolver->getNodeAliasPath($term, $node);
        if ($this->aliasActions->saveNewAlias($path, $new_alias, $langcode)) {
          $this->messageLogger->logOperation($action_msg, 'node', $node->id(), $node->label(), $new_alias, $old_alias);
        }
      }

      // Clear node render cache.
      $this->entityTypeManager->getViewBuilder('node')->resetCache([$node]);
    }
  }

}
