<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit\Service\RelatedNodesService;

use Drupal\taxonomy_section_paths\Service\RelatedNodesService;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\taxonomy_section_paths\Contract\PathResolverServiceInterface;
use Drupal\taxonomy_section_paths\Contract\AliasActionsServiceInterface;
use Drupal\taxonomy_section_paths\Contract\AliasMessageLoggerInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Config\Config;

/**
 * @covers \Drupal\taxonomy_section_paths\Service\RelatedNodesService
 */
class RelatedNodesServiceTest extends UnitTestCase {

  protected $entityTypeManager;
  protected $configFactory;
  protected $resolver;
  protected $aliasActions;
  protected $messageLogger;
  protected $nodeStorage;
  protected $viewBuilder;

  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->resolver = $this->createMock(PathResolverServiceInterface::class);
    $this->aliasActions = $this->createMock(AliasActionsServiceInterface::class);
    $this->messageLogger = $this->createMock(AliasMessageLoggerInterface::class);
    $this->nodeStorage = $this->createMock(EntityStorageInterface::class);
    $this->viewBuilder = $this->createMock(EntityViewBuilderInterface::class);
  }

  /**
   * @covers ::applyToRelatedNodes
   * @scenario Actualizar nodos relacionados tras cambiar alias del término
   * @context Hay un bundle configurado que devuelve nodos relacionados con el término
   * @expected Se invoca getNodesByBundleAndField() y se propaga la actualización con processRelatedNodes()
   */
  public function testApplyToRelatedNodesWithMatchingNodes(): void {
    $service = $this->getMockBuilder(RelatedNodesService::class)
      ->setConstructorArgs([
        $this->entityTypeManager,
        $this->configFactory,
        $this->resolver,
        $this->aliasActions,
        $this->messageLogger,
      ])
      ->onlyMethods(['getNodesByBundleAndField', 'processRelatedNodes'])
      ->getMock();

    // Simulamos configuración con un bundle 'article' y el campo 'field_tags'.
    $bundles_config = [
      'article' => ['field' => 'field_tags'],
    ];

    $config = $this->createMock(Config::class);
    $config->method('get')->with('bundles')->willReturn($bundles_config);
    $this->configFactory->method('get')->with('taxonomy_section_paths.settings')->willReturn($config);

    // Simulamos nodos devueltos
    $mock_nodes = ['node_1', 'node_2'];
    $service->expects($this->once())
      ->method('getNodesByBundleAndField')
      ->with('99', 'article', 'field_tags')
      ->willReturn($mock_nodes);

    $service->expects($this->once())
      ->method('processRelatedNodes')
      ->with('update', $mock_nodes, 'alias/test');

    $service->applyToRelatedNodes('update', '99', 'alias/test');
  }

  /**
   * @scenario Load nodes related to a term by bundle and field
   * @context Queremos obtener los nodos tipo 'article' que referencian el término 99
   * @expected Devuelve un array de nodos simulados correctamente
   */
  public function testGetNodesByBundleAndField(): void {
    $service = new RelatedNodesService(
      $this->entityTypeManager,
      $this->configFactory,
      $this->resolver,
      $this->aliasActions,
      $this->messageLogger,
    );

    $this->entityTypeManager
      ->method('getStorage')
      ->with('node')
      ->willReturn($this->nodeStorage);

    $query = $this->getMockBuilder('Drupal\Core\Entity\Query\QueryInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $this->nodeStorage
      ->method('getQuery')
      ->willReturn($query);

    $query->expects($this->once())
      ->method('accessCheck')
      ->with(TRUE)
      ->willReturnSelf();

    $query->expects($this->exactly(2))
      ->method('condition')
      ->with($this->logicalOr(
        $this->equalTo('type'),
        $this->equalTo('field_tags.target_id')
      ))
      ->willReturnSelf();

    $query->expects($this->once())
      ->method('execute')
      ->willReturn([1, 2]);

    $this->nodeStorage
      ->method('loadMultiple')
      ->with([1, 2])
      ->willReturn(['node_1', 'node_2']);

    $result = $service->getNodesByBundleAndField('99', 'article', 'field_tags');
    $this->assertEquals(['node_1', 'node_2'], $result);
  }


  /**
   * @covers ::processRelatedNodes
   * @scenario Reemplazo de alias cuando se actualiza el término
   * @context El nodo tiene un alias anterior y se debe generar uno nuevo
   * @expected Se borra el alias anterior, se crea uno nuevo, y se registra con log insert
   */
  public function testProcessRelatedNodesOnUpdate(): void {
    $service = new RelatedNodesService(
      $this->entityTypeManager,
      $this->configFactory,
      $this->resolver,
      $this->aliasActions,
      $this->messageLogger,
    );

    $config = $this->createMock(Config::class);
    $config->method('get')->with('generate_node_alias_if_term_empty')->willReturn(FALSE);
    $this->configFactory->method('get')->willReturn($config);

    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(1);
    $node->method('label')->willReturn('Test node');
    $node->method('language')->willReturn(new class {
      public function getId() { return 'en'; }
    });

    $this->aliasActions->expects($this->once())
      ->method('getOldAlias')->with('/node/1', 'en')->willReturn('/old-alias');
    $this->aliasActions->expects($this->once())
      ->method('deleteOldAlias')->with('/node/1', 'en')->willReturn(TRUE);
    $this->resolver->expects($this->once())
      ->method('getNodeAliasPath')->with('section/test', $node)->willReturn('/section/test/node-title');
    $this->aliasActions->expects($this->once())
      ->method('saveNewAlias')->with('/node/1', '/section/test/node-title', 'en')->willReturn(TRUE);
    $this->messageLogger->expects($this->once())
      ->method('logOperation')->with('update', 'node', 1, 'Test node', '/section/test/node-title', '/old-alias');

    $viewBuilder = $this->createMock(EntityViewBuilderInterface::class);
    $viewBuilder->expects($this->once())->method('resetCache')->with([$node]);
    $this->entityTypeManager->method('getViewBuilder')->willReturn($viewBuilder);

    $service->processRelatedNodes('update', [$node], 'section/test');
  }
   
  /**
   * @covers ::processRelatedNodes
   * @scenario Eliminación de alias sin regenerar en borrado de término
   * @context El alias debe eliminarse pero no debe generarse uno nuevo
   * @expected Se borra el alias y se registra con delete_without_new_alias
   */
  public function testProcessRelatedNodesOnDeleteWithoutReplacement(): void {
    $service = new RelatedNodesService(
      $this->entityTypeManager,
      $this->configFactory,
      $this->resolver,
      $this->aliasActions,
      $this->messageLogger,
    );

    $config = $this->createMock(Config::class);
    $config->method('get')->with('generate_node_alias_if_term_empty')->willReturn(FALSE);
    $this->configFactory->method('get')->willReturn($config);

    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(2);
    $node->method('label')->willReturn('Another node');
    $node->method('language')->willReturn(new class {
      public function getId() { return 'en'; }
    });

    $this->aliasActions->expects($this->once())
      ->method('getOldAlias')->with('/node/2', 'en')->willReturn('/deleted-alias');
    $this->aliasActions->expects($this->once())
      ->method('deleteOldAlias')->with('/node/2', 'en')->willReturn(TRUE);
    $this->aliasActions->expects($this->never())->method('saveNewAlias');
    $this->resolver->expects($this->never())->method('getNodeAliasPath');
    $this->messageLogger->expects($this->once())
      ->method('logOperation')->with('delete_without_new_alias', 'node', 2, 'Another node', NULL, '/deleted-alias');

    $viewBuilder = $this->createMock(EntityViewBuilderInterface::class);
    $viewBuilder->expects($this->once())->method('resetCache')->with([$node]);
    $this->entityTypeManager->method('getViewBuilder')->willReturn($viewBuilder);

    $service->processRelatedNodes('delete', [$node], 'ignored-value');
  }

  /**
   * @covers ::processRelatedNodes
   * @scenario Eliminación de alias con regeneración permitida por configuración
   * @context El alias debe eliminarse y regenerarse con un nuevo path basado en NULL
   * @expected Se borra el alias, se crea uno nuevo, y se registra con log insert
   */
  public function testProcessRelatedNodesOnDeleteWithReplacement(): void {
    $service = new RelatedNodesService(
      $this->entityTypeManager,
      $this->configFactory,
      $this->resolver,
      $this->aliasActions,
      $this->messageLogger,
    );

    $config = $this->createMock(Config::class);
    $config->method('get')->with('generate_node_alias_if_term_empty')->willReturn(TRUE);
    $this->configFactory->method('get')->willReturn($config);

    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(3);
    $node->method('label')->willReturn('Node with regenerated alias');
    $node->method('language')->willReturn(new class {
      public function getId() { return 'en'; }
    });

    $this->aliasActions->expects($this->once())
      ->method('getOldAlias')->with('/node/3', 'en')->willReturn('/old-to-be-deleted');
    $this->aliasActions->expects($this->once())
      ->method('deleteOldAlias')->with('/node/3', 'en')->willReturn(TRUE);
    $this->resolver->expects($this->once())
      ->method('getNodeAliasPath')->with(NULL, $node)->willReturn('/new/auto-generated-path');
    $this->aliasActions->expects($this->once())
      ->method('saveNewAlias')->with('/node/3', '/new/auto-generated-path', 'en')->willReturn(TRUE);
    $this->messageLogger->expects($this->once())
      ->method('logOperation')->with('insert', 'node', 3, 'Node with regenerated alias', '/new/auto-generated-path', '/old-to-be-deleted');

    $viewBuilder = $this->createMock(EntityViewBuilderInterface::class);
    $viewBuilder->expects($this->once())->method('resetCache')->with([$node]);
    $this->entityTypeManager->method('getViewBuilder')->willReturn($viewBuilder);

    $service->processRelatedNodes('delete', [$node], 'this-will-be-overwritten');
  }


}
