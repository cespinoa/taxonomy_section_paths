<?php

namespace Drupal\taxonomy_section_paths\Service;

use Drupal\taxonomy_section_paths\Contract\Service\TermChangeDetectorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy_section_paths\Helper\EntityHelper;

/**
 * Detects changes in taxonomy terms that affect alias generation.
 */
class TermChangeDetector implements TermChangeDetectorInterface {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Checks whether a term requires alias processing.
   *
   * The function verifies:
   * - If the term's vocabulary is managed by the module.
   * - If we're updating, whether the label or parent term has changed.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term being evaluated.
   * @param bool $is_update
   *   TRUE if the term is being updated, FALSE if inserted or deleted.
   *
   * @return bool
   *   TRUE if the term requires processing.
   */
  public function needsAliasUpdate(TermInterface $term, bool $is_update): bool {
    $config = $this->configFactory->get('taxonomy_section_paths.settings');
    $bundles = $config->get('bundles') ?? [];

    foreach ($bundles as $bundle_id => $data) {
      if ($data['vocabulary'] === $term->bundle()) {

        if ($is_update) {
          $original = EntityHelper::getSecureOriginalEntity($term);
          
          if ($original instanceof TermInterface) {
            $original_parent = $original->get('parent')->target_id ?? NULL;
            $current_parent = $term->get('parent')->target_id ?? NULL;

            $original_label = $original->label();
            $current_label = $term->label();

            if ($original_parent === $current_parent && $original_label === $current_label) {
              return FALSE;
            }
          }
          
        }

        // Insert, delete, or relevant changes on update.
        return TRUE;
      }
    }

    return FALSE;
  }

}
