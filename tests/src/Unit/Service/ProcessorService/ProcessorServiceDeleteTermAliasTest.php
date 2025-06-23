<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit\Service\ProcessorService;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\taxonomy_section_paths\Contract\PathResolverServiceInterface;
use Drupal\taxonomy_section_paths\Contract\AliasActionsServiceInterface;
use Drupal\taxonomy_section_paths\Contract\AliasConflictResolverInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy_section_paths\Contract\AliasMessageLoggerInterface;
use Drupal\taxonomy_section_paths\Contract\RelatedNodesServiceInterface;
use Drupal\taxonomy_section_paths\Contract\RequestContextStoreServiceInterface;
use Drupal\taxonomy_section_paths\Service\BatchProcessorService;
use Drupal\taxonomy_section_paths\Service\ProcessorService;
use PHPUnit\Framework\TestCase;

/**
 * @group taxonomy_section_paths
 * @covers \Drupal\taxonomy_section_paths\Service\ProcessorService::deleteTermAlias
 */
class ProcessorServiceDeleteTermAliasTest extends TestCase {

  /**
   * @covers \Drupal\taxonomy_section_paths\Service\ProcessorService::deleteTermAlias
   * @scenario no_batch_without_children
   * @context Only one term present, batch disabled
   * @expected Should log one deletions and apply alias removal
   */
  public function testDeleteTermAliasSingleTermWithoutBatch(): void {
    $term_id = 42;

    $termMock = $this->createMock(TermInterface::class);
    $termMock->method('id')->willReturn($term_id);
    $termMock->method('label')->willReturn('Term Label');

    // Context Store: simulate it’s the last in the group.
    $contextServiceMock = $this->createMock(RequestContextStoreServiceInterface::class);
    $contextServiceMock->expects($this->once())
      ->method('transition')
      ->with(RequestContextStoreServiceInterface::GROUP_INPUT, RequestContextStoreServiceInterface::GROUP_OUTPUT, $term_id);
    $contextServiceMock->expects($this->once())
      ->method('isLastInGroup')
      ->willReturn(TRUE);
    $contextServiceMock->expects($this->once())
      ->method('get')
      ->with(RequestContextStoreServiceInterface::GROUP_OUTPUT)
      ->willReturn([
        $term_id => [
          'original' => $termMock,
          'old_alias' => '/alias/old/path',
        ],
      ]);

    // Config: return FALSE for use_batch_for_term_operations.
    $configMock = $this->createMock(Config::class);
    $configMock->expects($this->once())
      ->method('get')
      ->with('use_batch_for_term_operations')
      ->willReturn(FALSE);

    $configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')
      ->with('taxonomy_section_paths.settings')
      ->willReturn($configMock);

    // Logger: assert logOperation is called with delete.
    $loggerMock = $this->createMock(AliasMessageLoggerInterface::class);
    $loggerMock->expects($this->once())
      ->method('logOperation')
      ->with(
        'delete',
        'taxonomy term',
        $term_id,
        $termMock->label(),
        '',
        '/alias/old/path'
      );

    // RelatedNodesService: should apply to this term.
    $relatedNodesMock = $this->createMock(RelatedNodesServiceInterface::class);
    $relatedNodesMock->expects($this->once())
      ->method('applyToRelatedNodes')
      ->with('delete', $term_id);

    $service = new ProcessorService(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(\Drupal\Core\Language\LanguageManagerInterface::class),
      $this->createMock(\Drupal\taxonomy_section_paths\Contract\PathResolverServiceInterface::class),
      $configFactoryMock,
      $this->createMock(\Drupal\taxonomy_section_paths\Contract\AliasActionsServiceInterface::class),
      $contextServiceMock,
      $loggerMock,
      $this->createMock(\Drupal\taxonomy_section_paths\Contract\AliasConflictResolverInterface::class),
      $relatedNodesMock,
      $this->createMock(BatchProcessorService::class),
    );

    $service->deleteTermAlias($termMock, TRUE);
  }

  /**
   * @covers \Drupal\taxonomy_section_paths\Service\ProcessorService::deleteTermAlias
   * @scenario no_batch_with_children
   * @context Parent and child terms present, batch disabled
   * @expected Should log two deletions and apply alias removal
   */
  public function testDeleteTermAliasWithChildrenWithoutBatch(): void {
    $termId = 42;
    $childId = 43;

    // Term mocks
    $termMock = $this->createMock(TermInterface::class);
    $termMock->method('id')->willReturn($termId);
    $termMock->method('label')->willReturn('Parent Term');

    $childTermMock = $this->createMock(TermInterface::class);
    $childTermMock->method('id')->willReturn($childId);
    $childTermMock->method('label')->willReturn('Child Term');

    $contextServiceMock = $this->createMock(RequestContextStoreServiceInterface::class);
    $contextServiceMock->expects($this->once())
      ->method('transition')
      ->with(RequestContextStoreServiceInterface::GROUP_INPUT, RequestContextStoreServiceInterface::GROUP_OUTPUT, $termId);
    $contextServiceMock->expects($this->once())
      ->method('isLastInGroup')
      ->willReturn(TRUE);
    $contextServiceMock->expects($this->once())
      ->method('get')
      ->with(RequestContextStoreServiceInterface::GROUP_OUTPUT)
      ->willReturn([
        $termId => ['original' => $termMock, 'old_alias' => '/alias/old/parent'],
        $childId => ['original' => $childTermMock, 'old_alias' => '/alias/old/child'],
      ]);

    $configMock = $this->createMock(Config::class);
    $configMock->method('get')
      ->with('use_batch_for_term_operations')
      ->willReturn(FALSE);

    $configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')
      ->with('taxonomy_section_paths.settings')
      ->willReturn($configMock);

    // Logger: validate both delete calls.
    $messageLoggerMock = $this->createMock(AliasMessageLoggerInterface::class);

    $expectedLoggerCalls = [
      ['delete','taxonomy term', $termId, 'Parent Term', '', '/alias/old/parent'],
      ['delete','taxonomy term', $childId, 'Child Term', '', '/alias/old/child'],
    ];

    $messageLoggerMock->expects($this->exactly(2))
      ->method('logOperation')
      ->willReturnCallback(function ($op, $type, $id, $label, $alias, $old_alias) use (&$expectedLoggerCalls) {
        $expected = array_shift($expectedLoggerCalls);
        
        [$expected_op, $expected_type, $expected_id, $expected_label, $expected_alias, $expected_old_alias] = $expected;
        $this->assertSame($expected_op, $op);
        $this->assertSame($expected_type, $type);
        $this->assertSame((string) $expected_id, (string) $id);
        $this->assertSame($expected_label, $label);
        $this->assertSame($expected_alias, $alias);
        $this->assertSame($expected_old_alias, $old_alias);
      });

    $contextServiceMock->expects($this->once())
    ->method('get')
    ->with(RequestContextStoreServiceInterface::GROUP_OUTPUT)
    ->willReturn([
      $termId => [
        'original' => $termMock,
        'old_alias' => '/alias/old/parent',
      ],
      $childId => [
        'original' => $childTermMock,
        'old_alias' => '/alias/old/child',
      ],
    ]);

    $relatedNodesMock = $this->createMock(RelatedNodesServiceInterface::class);
    $expectedCalls = [
      ['delete', $termId],
      ['delete', $childId],
    ];

    $relatedNodesMock->expects($this->exactly(2))
      ->method('applyToRelatedNodes')
      ->willReturnCallback(function ($action, $term_id) use (&$expectedCalls) {

        $expected = array_shift($expectedCalls);
        [$expectedAction, $expectedTerm] = $expected;

        $this->assertEquals($expectedAction, $action);
        $this->assertSame((int) $expectedTerm, (int) $term_id);
      });
    
    $batchProcessorMock = $this->createMock(BatchProcessorService::class);
    $batchProcessorMock->expects($this->never())->method('queueTermsForNodeUpdate');

    // Otros mocks vacíos
    $entityTypeManagerMock = $this->createMock(EntityTypeManagerInterface::class);
    $languageManagerMock = $this->createMock(LanguageManagerInterface::class);
    $resolverMock = $this->createMock(PathResolverServiceInterface::class);
    $aliasActionsMock = $this->createMock(AliasActionsServiceInterface::class);
    $aliasConflictResolverMock = $this->createMock(AliasConflictResolverInterface::class);

    $processor = new ProcessorService(
      $entityTypeManagerMock,
      $languageManagerMock,
      $resolverMock,
      $configFactoryMock,
      $aliasActionsMock,
      $contextServiceMock,
      $messageLoggerMock,
      $aliasConflictResolverMock,
      $relatedNodesMock,
      $batchProcessorMock,
    );

    // Ejecutar
    $processor->deleteTermAlias($termMock);
  }


  /**
   * @covers \Drupal\taxonomy_section_paths\Service\ProcessorService::deleteTermAlias
   * @scenario batch_without_children
   * @context Only one term present, batch enabled
   * @expected Should log one deletions and apply alias removal
   */
  public function testDeleteTermAliasSingleTermWithBatch(): void {
    $term_id = 42;

    $termMock = $this->createMock(TermInterface::class);
    $termMock->method('id')->willReturn($term_id);
    $termMock->method('label')->willReturn('Term Label');

    $contextServiceMock = $this->createMock(RequestContextStoreServiceInterface::class);
    $contextServiceMock->expects($this->once())
      ->method('transition')
      ->with(RequestContextStoreServiceInterface::GROUP_INPUT, RequestContextStoreServiceInterface::GROUP_OUTPUT, $term_id);

    $contextServiceMock->method('isLastInGroup')->willReturn(TRUE);
    $contextServiceMock->method('get')->willReturn([
      $term_id => [
        'original' => $termMock,
        'old_alias' => '/alias/old/path',
      ],
    ]);

    $configMock = $this->createMock(Config::class);
    $configMock->method('get')->with('use_batch_for_term_operations')->willReturn(TRUE);

    $configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')->with('taxonomy_section_paths.settings')->willReturn($configMock);

    $batchProcessorMock = $this->createMock(BatchProcessorService::class);

    $terms_data = [
      '42' => '/alias/old/path',
    ];

    $batchProcessorMock->expects($this->once())
      ->method('queueTermsForNodeUpdate')
      ->with('delete', $terms_data);

    $relatedNodesMock = $this->createMock(RelatedNodesServiceInterface::class);
    $relatedNodesMock->expects($this->never())
      ->method('applyToRelatedNodes');


    // Logger: assert logOperation is called with delete.
    $loggerMock = $this->createMock(AliasMessageLoggerInterface::class);
    $loggerMock->expects($this->once())
      ->method('logOperation')
      ->with(
        'delete',
        'taxonomy term',
        $term_id,
        $termMock->label(),
        '',
        '/alias/old/path'
      );

    $entityTypeManagerMock = $this->createMock(EntityTypeManagerInterface::class);
    $languageManagerMock = $this->createMock(LanguageManagerInterface::class);
    $resolverMock = $this->createMock(PathResolverServiceInterface::class);
    $aliasActionsMock = $this->createMock(AliasActionsServiceInterface::class);
    $aliasConflictResolverMock = $this->createMock(AliasConflictResolverInterface::class);

    $processorService = new ProcessorService(
      $entityTypeManagerMock,
      $languageManagerMock,
      $resolverMock,
      $configFactoryMock,
      $aliasActionsMock,
      $contextServiceMock,
      $loggerMock,
      $aliasConflictResolverMock,
      $relatedNodesMock,
      $batchProcessorMock
    );

    $processorService->deleteTermAlias($termMock);

  }

  /**
   * @covers \Drupal\taxonomy_section_paths\Service\ProcessorService::deleteTermAlias
   * @scenario batch_with_children
   * @context Parent and child terms present, batch enabled
   * @expected Should log two deletions and apply alias removal
   */
  public function testDeleteTermAliasWithChildrenWithBatch(): void {
    $termId = 42;
    $childId = 43;

    // Term mocks.
    $termMock = $this->createMock(TermInterface::class);
    $termMock->method('id')->willReturn($termId);
    $termMock->method('label')->willReturn('Parent Term');

    $childMock = $this->createMock(TermInterface::class);
    $childMock->method('id')->willReturn($childId);
    $childMock->method('label')->willReturn('Child Term');

    // ContextService mock.
    $contextServiceMock = $this->createMock(RequestContextStoreServiceInterface::class);
    $contextServiceMock->expects($this->once())
      ->method('transition')
      ->with(
        RequestContextStoreServiceInterface::GROUP_INPUT,
        RequestContextStoreServiceInterface::GROUP_OUTPUT,
        $termId
      );
    $contextServiceMock->method('isLastInGroup')->willReturn(TRUE);
    $contextServiceMock->method('get')->with(RequestContextStoreServiceInterface::GROUP_OUTPUT)->willReturn([
      $termId => [
        'original' => $termMock,
        'old_alias' => '/alias/old/parent',
      ],
      $childId => [
        'original' => $childMock,
        'old_alias' => '/alias/old/child',
      ],
    ]);



    // Config with batch enabled.
    $configMock = $this->createMock(\Drupal\Core\Config\Config::class);
    $configMock->method('get')->with('use_batch_for_term_operations')->willReturn(TRUE);

    $configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')->with('taxonomy_section_paths.settings')->willReturn($configMock);

    // Logger: validate both delete calls.
    $messageLoggerMock = $this->createMock(AliasMessageLoggerInterface::class);
    $expectedLoggerCalls = [
      ['delete','taxonomy term', $termId, 'Parent Term', '', '/alias/old/parent'],
      ['delete','taxonomy term', $childId, 'Child Term', '', '/alias/old/child'],
    ];
    $messageLoggerMock->expects($this->exactly(2))
      ->method('logOperation')
      ->willReturnCallback(function ($op, $type, $id, $label, $alias, $old_alias) use (&$expectedLoggerCalls) {
        $expected = array_shift($expectedLoggerCalls);
        
        [$expected_op, $expected_type, $expected_id, $expected_label, $expected_alias, $expected_old_alias] = $expected;
        $this->assertSame($expected_op, $op);
        $this->assertSame($expected_type, $type);
        $this->assertSame((string) $expected_id, (string) $id);
        $this->assertSame($expected_label, $label);
        $this->assertSame($expected_alias, $alias);
        $this->assertSame($expected_old_alias, $old_alias);
      });

    // Related nodes should NOT be called when using batch.
    $relatedNodesMock = $this->createMock(RelatedNodesServiceInterface::class);
    $relatedNodesMock->expects($this->never())
      ->method('applyToRelatedNodes');

    $terms_data = [
      '42' => '/alias/old/parent',
      '43' => '/alias/old/child',
    ];

    // Expect batch queueing of both terms.
    $batchProcessorMock = $this->createMock(BatchProcessorService::class);
    $batchProcessorMock->expects($this->once())
      ->method('queueTermsForNodeUpdate')
      ->with('delete', $terms_data);

    // Unused dependencies.
    $languageManagerMock = $this->createMock(LanguageManagerInterface::class);
    $resolverMock = $this->createMock(PathResolverServiceInterface::class);
    $aliasActionsMock = $this->createMock(AliasActionsServiceInterface::class);
    $aliasConflictResolverMock = $this->createMock(AliasConflictResolverInterface::class);
    $entityTypeManagerMock = $this->createMock(EntityTypeManagerInterface::class);

    $processor = new ProcessorService(
      $entityTypeManagerMock,
      $languageManagerMock,
      $resolverMock,
      $configFactoryMock,
      $aliasActionsMock,
      $contextServiceMock,
      $messageLoggerMock,
      $aliasConflictResolverMock,
      $relatedNodesMock,
      $batchProcessorMock
    );

    $processor->deleteTermAlias($termMock);
  }


}
