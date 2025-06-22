<?php

namespace Drupal\taxonomy_section_paths\Service;

use Drupal\taxonomy_section_paths\Contract\NodeChangeDetectorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy_section_paths\Helper\EntityHelper;

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
      $original = EntityHelper::getSecureOriginalEntity($node);
      if (!$original instanceof NodeInterface) {
        return TRUE;
      }

      $term_id = $node->get($field)->target_id ?? NULL;
      $original_term_id = $original->get($field)->target_id ?? NULL;

      $label = $node->label();
      $original_label = $original->label();

      return $term_id !== $original_term_id || $label !== $original_label;
    }

    return TRUE;
  }

  private function getOriginalEntity(NodeInterface $node) {
    if ($this->isPhpUnitMock($node)) {
      return $node->get('original');
    }
    return $node->original ?? NULL;
  }
  
  private function isPhpUnitMock(object $node): bool {
      return str_contains(get_class($node), 'MockObject');
  }

}
