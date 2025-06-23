<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit\Service\AliasActionsService;

use Drupal\taxonomy_section_paths\Service\AliasActionsService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\Core\Entity\EntityStorageInterface;
use PHPUnit\Framework\TestCase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\taxonomy_section_paths\Contract\AliasFactoryInterface;

/**
 * Tests for AliasActionsService.
 *
 * @group taxonomy_section_paths
 */
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

  /**
   * @covers \Drupal\taxonomy_section_paths\Service\AliasActionsService::getOldAlias
   * @scenario There is an existing alias for the given path and langcode
   * @context Alias entity is found by storage
   * @expected Returns the alias string
   */
  public function testGetOldAliasReturnsAlias() {
    $alias_entity = $this->createMock(PathAlias::class);
    $alias_entity->method('getAlias')->willReturn('/my-alias');

    $this->storage->method('loadByProperties')
      ->willReturn([$alias_entity]);

    $result = $this->service->getOldAlias('/node/123', 'en');
    $this->assertSame('/my-alias', $result);
  }

  /**
   * @covers \Drupal\taxonomy_section_paths\Service\AliasActionsService::getOldAlias
   * @scenario No alias entity found for the given path and langcode
   * @context Storage returns empty array
   * @expected Returns null
   */
  public function testGetOldAliasReturnsNullIfNoneFound() {
    $this->storage->method('loadByProperties')->willReturn([]);

    $result = $this->service->getOldAlias('/node/123', 'en');
    $this->assertNull($result);
  }

  /**
   * @covers \Drupal\taxonomy_section_paths\Service\AliasActionsService::saveNewAlias
   * @scenario Saving a new alias succeeds
   * @context AliasFactory creates a PathAlias mock that saves without error
   * @expected Returns true indicating success
   */
  public function testSaveNewAlias(): void {
    $values = [
      'path' => '/node/123',
      'alias' => '/my-alias',
      'langcode' => 'en',
    ];

    $alias = $this->createMock(PathAlias::class);
    $alias->expects($this->once())
      ->method('save');

    $this->aliasFactory
      ->method('create')
      ->with($values)
      ->willReturn($alias);

    $result = $this->service->saveNewAlias(
      $values['path'],
      $values['alias'],
      $values['langcode'],
    );

    $this->assertTrue($result, 'Alias was saved successfully.');
  }

  /**
   * @covers \Drupal\taxonomy_section_paths\Service\AliasActionsService::saveNewAlias
   * @scenario Saving a new alias fails due to storage exception
   * @context AliasFactory creates a PathAlias mock that throws exception on save
   * @expected Returns false indicating failure
   */
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

  /**
   * @covers \Drupal\taxonomy_section_paths\Service\AliasActionsService::deleteOldAlias
   * @scenario An existing alias entity is found and deleted
   * @context Storage returns one alias entity which delete() is called once
   * @expected Returns true indicating successful deletion
   */
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
