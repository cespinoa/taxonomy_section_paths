<?php

namespace Drupal\taxonomy_section_paths\Contract\Service;

use Drupal\taxonomy\TermInterface;
use Drupal\node\NodeInterface;

/**
 * Process the alias changes.
 */
interface ProcessorServiceInterface {

  /**
   * Generates the alias for a taxonomy term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term created or updated.
   * @param bool $update_action
   *   TRUE if update term, FALSE if insert it.
   *
   * @return void
   *   Nothing is returned.
   */
  public function setTermAlias(TermInterface $term, bool $update_action): void;

  /**
   * Deletes the alias associated with a taxonomy term and updates its children.
   *
   * This operation is executed recursively for all child terms.
   * If the 'use_batch_for_term_operations' setting is enabled,
   * the terms are queued for later processing via batch.
   * Otherwise, the operation is performed inline.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term whose alias should be deleted.
   * @param bool|null $not_use_batch
   *   TRUE to avoid using batch processing, useful when called from batch.
   *
   * @return void
   *   This method does not return anything.
   */
  public function deleteTermAlias(TermInterface $term, ?bool $not_use_batch = FALSE): void;

  /**
   * Controls node alias for insert and update nodes.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node inserted or updated.
   * @param bool $is_update
   *   True if update, false if insert.
   */
  public function setNodeAlias(NodeInterface $node, bool $is_update): void;

}
