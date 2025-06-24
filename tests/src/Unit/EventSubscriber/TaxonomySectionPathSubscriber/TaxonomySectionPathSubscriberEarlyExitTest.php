<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit\EventSubscriber\TaxonomySectionPathSubscriber;

use Drupal\taxonomy_section_paths\EventSubscriber\TaxonomySectionPathSubscriber;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy_section_paths\Contract\ProcessorServiceInterface;
use Drupal\taxonomy_section_paths\Contract\AliasActionsServiceInterface;
use Drupal\taxonomy_section_paths\Contract\NodeChangeDetectorInterface;
use Drupal\taxonomy_section_paths\Contract\TermChangeDetectorInterface;
use Drupal\taxonomy_section_paths\Contract\RequestContextStoreServiceInterface;
use Drupal\entity_events\Event\EntityEvent;
use PHPUnit\Framework\TestCase;

class TaxonomySectionPathSubscriberEarlyExitTest extends TestCase {

  protected $processor;
  protected $nodeChangeDetector;
  protected $termChangeDetector;
  protected $aliasActions;
  protected $configFactory;
  protected $contextService;
  protected $subscriber;

  protected function setUp(): void {
    $this->processor = $this->createMock(ProcessorServiceInterface::class);
    $this->nodeChangeDetector = $this->createMock(NodeChangeDetectorInterface::class);
    $this->termChangeDetector = $this->createMock(TermChangeDetectorInterface::class);
    $this->aliasActions = $this->createMock(AliasActionsServiceInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->contextService = $this->createMock(RequestContextStoreServiceInterface::class);

    $this->subscriber = new TaxonomySectionPathSubscriber(
      $this->processor,
      $this->nodeChangeDetector,
      $this->termChangeDetector,
      $this->aliasActions,
      $this->configFactory,
      $this->contextService,
    );
  }

  /**
   * @covers ::onEntityInsert
   * @scenario Early exit
   * @context Insert node not needing alias
   * @expected No alias should be generated
   */
  public function testOnEntityInsertWithNodeNoChange(): void {
    $node = $this->createMock(NodeInterface::class);
    $this->nodeChangeDetector->method('needsAliasUpdate')->with($node, FALSE)->willReturn(FALSE);
    $this->processor->expects($this->never())->method('setNodeAlias');
    $event = new EntityEvent('entity.insert', $node);
    $this->subscriber->onEntityInsert($event);
  }

  /**
   * @covers ::onEntityInsert
   * @scenario Early exit
   * @context Insert term not needing alias
   * @expected No alias should be generated
   */
  public function testOnEntityInsertWithTermNoChange(): void {
    $term = $this->createMock(TermInterface::class);
    $this->termChangeDetector->method('needsAliasUpdate')->with($term, FALSE)->willReturn(FALSE);
    $this->processor->expects($this->never())->method('setTermAlias');
    $event = new EntityEvent('entity.insert', $term);
    $this->subscriber->onEntityInsert($event);
  }

  /**
   * @covers ::onEntityUpdate
   * @scenario Early exit
   * @context Update node not needing alias
   * @expected No alias should be generated
   */
  public function testOnEntityUpdateWithNodeNoChange(): void {
    $node = $this->createMock(NodeInterface::class);
    $this->nodeChangeDetector->method('needsAliasUpdate')->with($node, TRUE)->willReturn(FALSE);
    $this->processor->expects($this->never())->method('setNodeAlias');
    $event = new EntityEvent('entity.update', $node);
    $this->subscriber->onEntityUpdate($event);
  }

  /**
   * @covers ::onEntityUpdate
   * @scenario Early exit
   * @context Update term with no label change
   * @expected No alias should be generated
   */
  public function testOnEntityUpdateWithTermSameLabel(): void {
    $term = $this->createMock(TermInterface::class);
    $term->method('label')->willReturn('Same title');
    
    $original = $this->createMock(TermInterface::class);
    $original->method('label')->willReturn('Same title');

    $term->method('get')->willReturnMap([
      ['original', $original],
    ]);

    $this->processor->expects($this->never())->method('setTermAlias');

    $event = new EntityEvent('entity.update', $term);
    $this->subscriber->onEntityUpdate($event);
  }

  /**
   * @covers ::onEntityPredelete
   * @scenario Early exit
   * @context Predelete node not needing alias change
   * @expected Context should not be set
   */
  public function testOnEntityPredeleteWithNodeNoChange(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(99);
    $langcode = $this->createStub(\Drupal\Core\Language\LanguageInterface::class);
    $langcode->method('getId')->willReturn('en');
    $node->method('language')->willReturn($langcode);

    $this->nodeChangeDetector->method('needsAliasUpdate')->with($node, TRUE)->willReturn(FALSE);
    $this->contextService->expects($this->never())->method('set');

    $event = new EntityEvent('entity.predelete', $node);
    $this->subscriber->onEntityPredelete($event);
  }

  /**
   * @covers ::onEntityPredelete
   * @scenario Early exit
   * @context Predelete term not needing alias change
   * @expected Context should not be set
   */
  public function testOnEntityPredeleteWithTermNoChange(): void {
    $term = $this->createMock(TermInterface::class);
    $term->method('id')->willReturn(11);
    $langcode = $this->createStub(\Drupal\Core\Language\LanguageInterface::class);
    $langcode->method('getId')->willReturn('en');
    $term->method('language')->willReturn($langcode);

    $this->termChangeDetector->method('needsAliasUpdate')->with($term, FALSE)->willReturn(FALSE);
    $this->contextService->expects($this->never())->method('set');

    $event = new EntityEvent('entity.predelete', $term);
    $this->subscriber->onEntityPredelete($event);
  }

}
