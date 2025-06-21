<?php

namespace Drupal\taxonomy_section_paths\Service;

use Drupal\taxonomy_section_paths\Contract\NodeChangeDetectorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\node\NodeInterface;

/**
 * Detects changes in nodes that affect alias generation.
 */
class NodeChangeDetector implements NodeChangeDetectorInterface {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function needsAliasUpdate(NodeInterface $node, bool $is_update): bool {
    $bundles = $this->configFactory->get('taxonomy_section_paths.settings')->get('bundles');
    $node_bundle = $node->bundle();
    $config = $bundles[$node_bundle] ?? NULL;

    if (!$config) {
      return FALSE;
    }

    $field = $config['field'];
    if (!$node->hasField($field)) {
      return FALSE;
    }

    if ($is_update) {
      if (!isset($node->original)) {
        return TRUE;
      }

      $term_id = $node->get($field)->target_id ?? NULL;
      $original_term_id = $node->original->get($field)->target_id ?? NULL;

      $label = $node->label();
      $original_label = $node->original->label();

      return $term_id !== $original_term_id || $label !== $original_label;
    }

    return TRUE;
  }

}
