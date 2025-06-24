<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit\EventSubscriber\TaxonomySectionPathSubscriber;

use Drupal\taxonomy_section_paths\EventSubscriber\TaxonomySectionPathSubscriber;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy_section_paths\Contract\Service\ProcessorServiceInterface;
use Drupal\taxonomy_section_paths\Contract\Service\AliasActionsServiceInterface;
use Drupal\taxonomy_section_paths\Contract\Service\NodeChangeDetectorInterface;
use Drupal\taxonomy_section_paths\Contract\Service\TermChangeDetectorInterface;
use Drupal\taxonomy_section_paths\Contract\Service\RequestContextStoreServiceInterface;
use Drupal\entity_events\Event\EntityEvent;
use Drupal\Tests\taxonomy_section_paths\Stub\FieldValueStub;

use PHPUnit\Framework\TestCase;

class TaxonomySectionPathSubscriberTest extends TestCase {

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
   * @covers ::onEntityDelete
   * @scenario Entity deletion
   * @context Entity is a node
   * @expected No alias deletion should be triggered
   */
  public function testOnEntityDeleteWithNode(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(1);

    // No se espera llamada a deleteTermAlias
    $this->processor->expects($this->never())->method('deleteTermAlias');

    $event = new EntityEvent('entity.delete', $node);
    $this->subscriber->onEntityDelete($event);
  }

  /**
   * @covers ::onEntityDelete
   * @scenario Borrado de término
   * @context El término requiere eliminación del alias
   * @expected Se invoca deleteTermAlias con el término
   */
  public function testOnEntityDeleteWithTerm(): void {
    $term = $this->createMock(TermInterface::class);
    $term->method('id')->willReturn(6);

    $this->termChangeDetector->method('needsAliasUpdate')->with($term, FALSE)->willReturn(TRUE);
    $this->processor->expects($this->once())->method('deleteTermAlias')->with($term);

    $event = new EntityEvent('entity.delete',$term);
    $this->subscriber->onEntityDelete($event);
  }

  /**
   * @covers ::onEntityInsert
   * @scenario Insertar nodo
   * @context El nodo requiere alias tras ser insertado
   * @expected Se invoca setNodeAlias con el nodo
   */
  public function testOnEntityInsertWithNode(): void {
    $node = $this->createMock(NodeInterface::class);
    $this->nodeChangeDetector->method('needsAliasUpdate')->with($node, FALSE)->willReturn(TRUE);
    $this->processor->expects($this->once())->method('setNodeAlias')->with($node, FALSE);

    $event = new EntityEvent('entity.insert',$node);
    $this->subscriber->onEntityInsert($event);
  }
  
  /**
   * @covers ::onEntityInsert
   * @scenario Insertar término de taxonomía
   * @context El término requiere alias tras ser insertado
   * @expected Se invoca setTermAlias con el término
   */
  public function testOnEntityInsertWithTerm(): void {
    $term = $this->createMock(TermInterface::class);
    $this->termChangeDetector->method('needsAliasUpdate')->with($term, FALSE)->willReturn(TRUE);
    $this->processor->expects($this->once())->method('setTermAlias')->with($term, FALSE);

    $event = new EntityEvent('entity.insert',$term);
    $this->subscriber->onEntityInsert($event);
  }

  /**
   * @covers ::onEntityPredelete
   * @scenario Entity predelete
   * @context Entity is a node and alias update is needed
   * @expected The node and its old alias should be stored in request context
   */
  public function testOnEntityPredeleteWithNode(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(5);

    $langcode_stub = $this->createStub(\Drupal\Core\Language\LanguageInterface::class);
    $langcode_stub->method('getId')->willReturn('en');
    $node->method('language')->willReturn($langcode_stub);

    $this->nodeChangeDetector
      ->method('needsAliasUpdate')
      ->with($node, TRUE)
      ->willReturn(TRUE);

    $this->aliasActions
      ->method('getOldAlias')
      ->with('/node/5', 'en')
      ->willReturn('/old-alias');

    $this->contextService
      ->expects($this->once())
      ->method('set')
      ->with(
        RequestContextStoreServiceInterface::GROUP_INPUT,
        5,
        [
          'original' => $node,
          'old_alias' => '/old-alias',
        ]
      );

    $event = new EntityEvent('entity.predelete', $node);
    $this->subscriber->onEntityPredelete($event);
  }

  /**
   * @covers ::onEntityPredelete
   * @scenario Entity predelete
   * @context Entity is a taxonomy term and alias update is needed
   * @expected The term and its old alias should be stored in request context
   */
  public function testOnEntityPredeleteWithTerm(): void {
    $term = $this->createMock(TermInterface::class);
    $term->method('id')->willReturn(123);

    $langcode_stub = $this->createStub(\Drupal\Core\Language\LanguageInterface::class);
    $langcode_stub->method('getId')->willReturn('en');
    $term->method('language')->willReturn($langcode_stub);

    $this->termChangeDetector
      ->method('needsAliasUpdate')
      ->with($term, FALSE)
      ->willReturn(TRUE);

    $this->aliasActions
      ->method('getOldAlias')
      ->with('/taxonomy/term/123', 'en')
      ->willReturn('/old-term-alias');

    $this->contextService
      ->expects($this->once())
      ->method('set')
      ->with(
        RequestContextStoreServiceInterface::GROUP_INPUT,
        123,
        [
          'original' => $term,
          'old_alias' => '/old-term-alias',
        ]
      );

    $event = new EntityEvent('entity.predelete', $term);
    $this->subscriber->onEntityPredelete($event);
  }


  /**
   * @covers ::onEntityUpdate
   * @scenario Entity update
   * @context Entity is a node and alias update is needed
   * @expected The processor should be called to set the node alias
   */
  public function testOnEntityUpdateWithNode(): void {
    $node = $this->createMock(NodeInterface::class);

    $this->nodeChangeDetector
      ->method('needsAliasUpdate')
      ->with($node, TRUE)
      ->willReturn(TRUE);

    $this->processor
      ->expects($this->once())
      ->method('setNodeAlias')
      ->with($node, TRUE);

    $event = new EntityEvent('entity.update', $node);
    $this->subscriber->onEntityUpdate($event);
  }


  /** 
   * @covers ::onEntityUpdate
   * @scenario Actualizar término de taxonomía
   * @context El nombre del término ha cambiado
   * @expected Se invoca setTermAlias con el término
   */
  public function testOnEntityUpdateWithTerm(): void {
    $original = $this->createMock(TermInterface::class);
    $original->method('label')->willReturn('Old title');

    $term = $this->createMock(TermInterface::class);
    $term->method('label')->willReturn('New title');

    $term->method('get')->willReturnMap([
      ['original', $original],
    ]);

    $this->termChangeDetector
      ->method('needsAliasUpdate')
      ->with($term, TRUE)
      ->willReturn(TRUE);

    $this->processor
      ->expects($this->once())
      ->method('setTermAlias')
      ->with($term, TRUE);

    $event = new EntityEvent('entity.update', $term);
    $this->subscriber->onEntityUpdate($event);
  }


}
