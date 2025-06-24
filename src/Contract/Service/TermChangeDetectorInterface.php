<?php

namespace Drupal\taxonomy_section_paths\Contract\Service;

use Drupal\taxonomy\TermInterface;

/**
 * Interface for detecting changes in taxonomy terms relevant to alias updates.
 */
interface TermChangeDetectorInterface {

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
  public function needsAliasUpdate(TermInterface $term, bool $is_update): bool;

}
