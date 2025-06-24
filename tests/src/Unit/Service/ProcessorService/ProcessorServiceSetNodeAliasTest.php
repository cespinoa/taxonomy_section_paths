<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit\Service\ProcessorService;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy_section_paths\Contract\Service\AliasActionsServiceInterface;
use Drupal\taxonomy_section_paths\Contract\Utility\AliasConflictResolverInterface;
use Drupal\taxonomy_section_paths\Contract\Utility\AliasMessageLoggerInterface;
use Drupal\taxonomy_section_paths\Contract\Service\PathResolverServiceInterface;
use Drupal\taxonomy_section_paths\Contract\Service\RelatedNodesServiceInterface;
use Drupal\taxonomy_section_paths\Contract\Service\RequestContextStoreServiceInterface;
use Drupal\taxonomy_section_paths\Service\BatchProcessorService;
use Drupal\taxonomy_section_paths\Service\ProcessorService;
use Drupal\Tests\taxonomy_section_paths\Stub\FieldValueStub;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * @group taxonomy_section_paths
 * @covers \Drupal\taxonomy_section_paths\Service\ProcessorService::setNodeAlias
 */
class ProcessorServiceSetNodeAliasTest extends TestCase {

  /**
   * @scenario Node has a taxonomy term in the configured field.
   * @context Term entity exists in storage.
   * @expected Saves new alias based on term alias and logs insert operation.
   */
  public function testSetNodeAliasInsertWithTerm(): void {
    $termId = 123;
    $nodeId = 456;

    // Mock del término con id y label.
    $termMock = $this->createMock(TermInterface::class);
    $termMock->method('id')->willReturn($termId);
    $termMock->method('label')->willReturn('Term Label');

    // Mock storage para taxonomy_term que devuelve el término mockeado.
    $termStorageMock = $this->createMock(EntityStorageInterface::class);
    $termStorageMock->method('load')->with($termId)->willReturn($termMock);

    // Mock del entityTypeManager con getStorage y getViewBuilder.
    $entityTypeManagerMock = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManagerMock->method('getStorage')->with('taxonomy_term')->willReturn($termStorageMock);
    $entityTypeManagerMock->method('getViewBuilder')->with('node')->willReturn(
      $this->createConfiguredMock(EntityViewBuilderInterface::class, [
        'resetCache' => null,
      ])
    );

    // Mock del LanguageManager.
    $languageManagerMock = $this->createMock(LanguageManagerInterface::class);

    // Stub del campo que devuelve target_id del término.
    $fieldItemListMock = new FieldValueStub(['target_id' => 123]);

    // Mock del nodo con bundle, get (campo), id, label, language.
    $nodeMock = $this->createMock(NodeInterface::class);
    $nodeMock->method('bundle')->willReturn('some_bundle');
    $nodeMock->method('get')->willReturnCallback(fn($field) => $field === 'field_tags' ? $fieldItemListMock : null);
    $nodeMock->method('id')->willReturn($nodeId);
    $nodeMock->method('label')->willReturn('Node Title');
    $nodeMock->method('language')->willReturn($this->createConfiguredMock(LanguageInterface::class, ['getId' => 'en']));

    // Config mock para bundles y generación de alias.
    $configMock = $this->createMock(Config::class);
    $configMock->method('get')->willReturnCallback(fn($key) => match ($key) {
      'bundles' => ['some_bundle' => ['field' => 'field_tags']],
      'generate_node_alias_if_term_empty' => false,
      default => null,
    });

    $configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')->with('taxonomy_section_paths.settings')->willReturn($configMock);

    // Mock resolver que devuelve el alias basado en el término.
    $resolverMock = $this->createMock(PathResolverServiceInterface::class);
    $resolverMock->expects($this->once())
      ->method('getNodeAliasPath')
      ->with($termMock, $nodeMock)
      ->willReturn('/some/alias/path');

    // Resolver de conflictos que devuelve el mismo alias.
    $aliasConflictResolverMock = $this->createMock(AliasConflictResolverInterface::class);
    $aliasConflictResolverMock->method('ensureUniqueAlias')->willReturn('/some/alias/path');

    // Alias actions que espera llamada a saveNewAlias.
    $aliasActionsMock = $this->createMock(AliasActionsServiceInterface::class);
    $aliasActionsMock->expects($this->once())
      ->method('saveNewAlias')
      ->with('/node/456', '/some/alias/path', 'en');

    // Logger que espera log de operación insert.
    $messageLoggerMock = $this->createMock(AliasMessageLoggerInterface::class);
    $messageLoggerMock->expects($this->once())
      ->method('logOperation')
      ->with('insert', 'node', $nodeId, 'Node Title', '/some/alias/path', '');

    // Otros mocks vacíos necesarios.
    $contextServiceMock = $this->createMock(RequestContextStoreServiceInterface::class);
    $relatedNodesMock = $this->createMock(RelatedNodesServiceInterface::class);
    $batchProcessorMock = $this->createMock(BatchProcessorService::class);

    $service = new ProcessorService(
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

    $service->setNodeAlias($nodeMock, false);
  }

  /**
   * @scenario Node has no term but alias generation enabled in config.
   * @context Field exists but is empty.
   * @expected Alias generated and saved, operation logged.
   */
  public function testSetNodeAliasInsertWithoutTerm(): void {
    $nodeId = 456;

    // Mock campo sin término (target_id null).
    $fieldItemListMock = $this->createMock(FieldItemListInterface::class);
    $fieldItemListMock->target_id = null;

    $nodeMock = $this->createMock(NodeInterface::class);
    $nodeMock->method('bundle')->willReturn('some_bundle');
    $nodeMock->method('get')->willReturnCallback(fn($field) => $field === 'field_tags' ? $fieldItemListMock : null);
    $nodeMock->method('id')->willReturn($nodeId);
    $nodeMock->method('label')->willReturn('Node Title');
    $nodeMock->method('language')->willReturn($this->createConfiguredMock(LanguageInterface::class, ['getId' => 'en']));

    // Config activa para generación sin término.
    $configMock = $this->createMock(Config::class);
    $configMock->method('get')->willReturnCallback(fn($key) => match ($key) {
      'bundles' => ['some_bundle' => ['field' => 'field_tags']],
      'generate_node_alias_if_term_empty' => true,
      default => null,
    });

    $configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')->with('taxonomy_section_paths.settings')->willReturn($configMock);

    $aliasPath = '/auto/alias/for/node';

    // Resolver llamado con term = null.
    $resolverMock = $this->createMock(PathResolverServiceInterface::class);
    $resolverMock->expects($this->once())
      ->method('getNodeAliasPath')
      ->with(null, $nodeMock)
      ->willReturn($aliasPath);

    // Resolver de conflictos que devuelve mismo alias.
    $aliasConflictResolverMock = $this->createMock(AliasConflictResolverInterface::class);
    $aliasConflictResolverMock->method('ensureUniqueAlias')
      ->with($aliasPath, 'en', '/node/456')
      ->willReturn($aliasPath);

    // Alias actions que guarda el alias.
    $aliasActionsMock = $this->createMock(AliasActionsServiceInterface::class);
    $aliasActionsMock->expects($this->once())
      ->method('saveNewAlias')
      ->with('/node/456', $aliasPath, 'en');

    // Logger que registra la operación insert.
    $messageLoggerMock = $this->createMock(AliasMessageLoggerInterface::class);
    $messageLoggerMock->expects($this->once())
      ->method('logOperation')
      ->with('insert', 'node', $nodeId, 'Node Title', $aliasPath, null);

    // Otros mocks vacíos.
    $languageManagerMock = $this->createMock(LanguageManagerInterface::class);
    $contextServiceMock = $this->createMock(RequestContextStoreServiceInterface::class);
    $relatedNodesMock = $this->createMock(RelatedNodesServiceInterface::class);
    $batchProcessorMock = $this->createMock(BatchProcessorService::class);

    $entityTypeManagerMock = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManagerMock->method('getViewBuilder')->willReturn(
      $this->createMock(EntityViewBuilderInterface::class)
    );

    $processorService = new ProcessorService(
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

    $processorService->setNodeAlias($nodeMock, false);

    $this->assertEquals($nodeId, $nodeMock->id());
  }

  /**
   * @scenario Node has no term and alias generation disabled in config.
   * @context Field exists but is empty.
   * @expected No alias is generated or saved, no errors.
   */
  public function testSetNodeAliasInsertWithoutTermAliasGenerationDisabled(): void {
    $nodeId = 456;

    // Mock LanguageManager.
    $languageManagerMock = $this->createMock(LanguageManagerInterface::class);

    // Config mock with alias generation disabled.
    $configMock = $this->createMock(Config::class);
    $configMock->method('get')->willReturnCallback(fn($key) => match ($key) {
      'bundles' => ['some_bundle' => ['field' => 'field_tags']],
      'generate_node_alias_if_term_empty' => false,
      default => null,
    });

    $configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')->with('taxonomy_section_paths.settings')->willReturn($configMock);

    // Mock field with target_id null.
    $fieldItemListMock = $this->createMock(FieldItemListInterface::class);
    $fieldItemListMock->target_id = null;

    // Mock node.
    $nodeMock = $this->createMock(NodeInterface::class);
    $nodeMock->method('bundle')->willReturn('some_bundle');
    $nodeMock->method('get')->willReturnCallback(fn($field) => $field === 'field_tags' ? $fieldItemListMock : null);
    $nodeMock->method('id')->willReturn($nodeId);
    $nodeMock->method('label')->willReturn('Node Title');
    $nodeMock->method('language')->willReturn($this->createConfiguredMock(LanguageInterface::class, ['getId' => 'en']));

    // Mock storage and view builder.
    $entityStorageMock = $this->createMock(EntityStorageInterface::class);
    $entityTypeManagerMock = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManagerMock->method('getStorage')->with('taxonomy_term')->willReturn($entityStorageMock);

    $viewBuilderMock = $this->createMock(EntityViewBuilderInterface::class);
    $viewBuilderMock->expects($this->once())
      ->method('resetCache')
      ->with([$nodeMock]);

    $entityTypeManagerMock->method('getViewBuilder')->with('node')->willReturn($viewBuilderMock);

    // Other mocks not used in this test.
    $resolverMock = $this->createMock(PathResolverServiceInterface::class);
    $aliasActionsMock = $this->createMock(AliasActionsServiceInterface::class);
    $contextServiceMock = $this->createMock(RequestContextStoreServiceInterface::class);
    $messageLoggerMock = $this->createMock(AliasMessageLoggerInterface::class);
    $aliasConflictResolverMock = $this->createMock(AliasConflictResolverInterface::class);
    $relatedNodesMock = $this->createMock(RelatedNodesServiceInterface::class);
    $batchProcessorMock = $this->createMock(BatchProcessorService::class);

    $processorService = new ProcessorService(
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

    // Ejecutamos el método, no debería crear alias.
    $processorService->setNodeAlias($nodeMock, false);

    // Verificamos que no da error.
    $this->assertTrue(true);
  }

}
