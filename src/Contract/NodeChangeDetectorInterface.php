<?php

namespace Drupal\taxonomy_section_paths\Contract;

use Drupal\node\NodeInterface;

/**
 * Interface for detecting changes in nodes relevant to alias updates.
 */
interface NodeChangeDetectorInterface {

  /**
   * Checks whether a node requires its alias to be updated.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node being evaluated.
   * @param bool $is_update
   *   TRUE if the node is being updated, FALSE if it's a new insert.
   *
   * @return bool
   *   TRUE if the alias should be updated, FALSE otherwise.
   */
  public function needsAliasUpdate(NodeInterface $node, bool $is_update): bool;

}
