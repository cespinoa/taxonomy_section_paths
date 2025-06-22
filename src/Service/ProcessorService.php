<?php

namespace Drupal\taxonomy_section_paths\Service;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\taxonomy_section_paths\Contract\PathResolverServiceInterface;
use Drupal\taxonomy_section_paths\Contract\AliasActionsServiceInterface;
use Drupal\taxonomy_section_paths\Contract\AliasMessageLoggerInterface;
use Drupal\taxonomy_section_paths\Contract\AliasConflictResolverInterface;

use Drupal\taxonomy_section_paths\Contract\RelatedNodesServiceInterface;
use Drupal\taxonomy_section_paths\Contract\RequestContextStoreServiceInterface;
use Drupal\taxonomy_section_paths\Contract\ProcessorServiceInterface;

/**
 * Process the alias changes.
 */
class ProcessorService implements ProcessorServiceInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
    protected PathResolverServiceInterface $resolver,
    protected ConfigFactoryInterface $configFactory,
    protected AliasActionsServiceInterface $aliasActions,
    protected RequestContextStoreServiceInterface $contextService,
    protected AliasMessageLoggerInterface $messageLogger,
    protected AliasConflictResolverInterface $aliasConflictResolver,
    protected RelatedNodesServiceInterface $relatedNodes,
    protected BatchProcessorService $batchProcessor,
  ) {}

  /**
   * Generates the alias for a taxonomy term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term created or updated.
   * @param bool $is_update
   *   TRUE if update term, FALSE if insert it.
   *
   * @return void
   *   Nothing is returned.
   */
  public function setTermAlias(TermInterface $term, bool $is_update): void {

    $path = '/taxonomy/term/' . $term->id();
    $langcode = $term->language()->getId();
    if ($is_update) {
      $old_alias = $this->aliasActions->getOldAlias($path, $langcode) ?? NULL;
      if ($old_alias) {
        $this->aliasActions->deleteOldAlias($path, $langcode);
      }
    }
    $alias = $this->resolver->getTermAliasPath($term, $langcode, $path);
    $alias = $this->aliasConflictResolver->ensureUniqueAlias($alias, $langcode, $path);

    $this->aliasActions->saveNewAlias($path, $alias, $langcode);

    if ($is_update) {
      $action = $is_update ? 'update' : 'delete';
      $this->relatedNodes->applyToRelatedNodes($action, $term);

      $children = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadTree($term->bundle(), $term->id(), NULL, TRUE);
      foreach ($children as $child) {
        if ($child instanceof Term) {
          $this->setTermAlias($child, TRUE);
        }
      }

      $this->messageLogger->logOperation('update', 'taxonomy term', $term->id(), $term->label(), $alias, $old_alias);
    }
    else {
      $this->messageLogger->logOperation('insert', 'taxonomy term', $term->id(), $term->label(), $alias, '');
    }
  }

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
  public function deleteTermAlias(TermInterface $term, ?bool $not_use_batch = FALSE): void {

    $this->contextService->transition(RequestContextStoreServiceInterface::GROUP_INPUT, RequestContextStoreServiceInterface::GROUP_OUTPUT, $term->id());

    if ($this->contextService->isLastInGroup(RequestContextStoreServiceInterface::GROUP_INPUT)) {

      // Comprobamos configuración del uso de batch.
      $use_batch = $this->configFactory
        ->get('taxonomy_section_paths.settings')
        ->get('use_batch_for_term_operations');

      // Forzamos a falso si se está dentro de otro proceso batch.
      if ($not_use_batch) {
        $use_batch = FALSE;
      }

      $terms = [];

      foreach ($this->contextService->get(RequestContextStoreServiceInterface::GROUP_OUTPUT) as $term_id => $term_data) {
        $this->messageLogger->logOperation(
          'delete',
          'taxonomy term',
          $term->id(),
          $term_data['original']->label(),
          '',
          $term_data['old_alias']
        );

        if ($use_batch) {
          $terms[] = $term_data['original'];
        }
        else {
          $this->relatedNodes->applyToRelatedNodes('delete', $term_data['original']);
        }
      }
      if ($use_batch) {
        $this->batchProcessor->queueTermsForNodeUpdate('delete', $terms);
      }
    }
  }

  /**
   * Controls node alias for insert and update nodes.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node inserted or updated.
   * @param bool $is_update
   *   True if update, false if insert.
   */
  public function setNodeAlias(NodeInterface $node, bool $is_update): void {
    
    $bundles = $this->configFactory->get('taxonomy_section_paths.settings')->get('bundles');
    $node_bundle = $node->bundle();
    $config = $bundles[$node_bundle] ?? NULL;
    $field = $config['field'];
    $term_id = $node->get($field)->target_id;

    //~ var_dump($field, $term_id);

    $create_alias = FALSE;
    $path = '/node/' . $node->id();
    $langcode = $node->language()->getId();

    $operation_type = 'insert';
    $old_alias = NULL;
    if ($is_update) {
      $old_alias = $this->aliasActions->getOldAlias($path, $langcode);
      $this->aliasActions->deleteOldAlias($path, $langcode);
      $operation_type = 'update';
    }

    if ($term_id) {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($term_id);
      $create_alias = TRUE;
    }
    else {
      // Configured taxonomy term field is empty.
      if ($this->configFactory->get('taxonomy_section_paths.settings')->get('generate_node_alias_if_term_empty')) {
        $term = NULL;
        $create_alias = TRUE;
      }
    }


    if ($create_alias) {
      $alias = $this->resolver->getNodeAliasPath($term, $node);
      $alias = $this->aliasConflictResolver->ensureUniqueAlias($alias, $langcode, $path);
      if ($alias) {
        $this->aliasActions->saveNewAlias($path, $alias, $langcode);
        $this->messageLogger->logOperation($operation_type, 'node', $node->id(), $node->label(), $alias, $old_alias);

      }
    }

    $this->entityTypeManager->getViewBuilder('node')->resetCache([$node]);

  }

}
