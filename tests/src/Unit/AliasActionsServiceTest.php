<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit;

use Drupal\taxonomy_section_paths\Service\AliasActionsService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\Core\Entity\EntityStorageInterface;
use PHPUnit\Framework\TestCase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\taxonomy_section_paths\Contract\AliasFactoryInterface;


class AliasActionsServiceTest extends TestCase {

  protected EntityStorageInterface $storage;
  protected EntityTypeManagerInterface $entityTypeManager;
  protected AliasFactoryInterface $aliasFactory;
  protected AliasActionsService $service;

  protected function setUp(): void {
    $this->storage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager->method('getStorage')
      ->with('path_alias')
      ->willReturn($this->storage);

    $this->aliasFactory = $this->createMock(AliasFactoryInterface::class);

    $this->service = new AliasActionsService(
      aliasRepository: $this->createMock(\Drupal\path_alias\AliasRepositoryInterface::class),
      entityTypeManager: $this->entityTypeManager,
      aliasFactory: $this->aliasFactory,
    );
  }


  public function testGetOldAliasReturnsAlias() {
    $alias_entity = $this->createMock(PathAlias::class);
    $alias_entity->method('getAlias')->willReturn('/my-alias');

    $this->storage->method('loadByProperties')
      ->willReturn([$alias_entity]);

    $result = $this->service->getOldAlias('/node/123', 'en');
    $this->assertSame('/my-alias', $result);
  }

  public function testGetOldAliasReturnsNullIfNoneFound() {
    $this->storage->method('loadByProperties')->willReturn([]);

    $result = $this->service->getOldAlias('/node/123', 'en');
    $this->assertNull($result);
  }

  public function testSaveNewAlias(): void {
    $values = [
      'path' => '/node/123',
      'alias' => '/my-alias',
      'langcode' => 'en',
    ];

    // Creamos el mock del PathAlias.
    $alias = $this->createMock(PathAlias::class);
    $alias->expects($this->once())
      ->method('save');

    // La factory debe devolver el alias mockeado.
    $this->aliasFactory
      ->method('create')
      ->with($values)
      ->willReturn($alias);

    // Ejecutamos el método a testear.
    $result = $this->service->saveNewAlias(
      $values['path'],
      $values['alias'],
      $values['langcode'],
    );

    // Afirmamos que todo fue OK.
    $this->assertTrue($result, 'Alias was saved successfully.');
  }

  public function testSaveNewAliasFails(): void {
    $values = [
      'path' => '/node/456',
      'alias' => '/fail-alias',
      'langcode' => 'en',
    ];

    $alias = $this->createMock(PathAlias::class);
    $alias->method('save')->willThrowException(new EntityStorageException('Error'));

    $this->aliasFactory
      ->method('create')
      ->with($values)
      ->willReturn($alias);

    $result = $this->service->saveNewAlias(
      $values['path'],
      $values['alias'],
      $values['langcode'],
    );

    $this->assertFalse($result, 'Alias saving should fail.');
  }

  public function testDeleteOldAlias(): void {
    $aliasMock = $this->createMock(PathAlias::class);
    $aliasMock->expects($this->once())
      ->method('delete');

    $this->storage->method('loadByProperties')
      ->with([
        'path' => '/node/123',
        'langcode' => 'en',
      ])
      ->willReturn([$aliasMock]);

    $result = $this->service->deleteOldAlias('/node/123', 'en');

    $this->assertTrue($result, 'Alias should be deleted and return true.');
  }


}
