<?php

namespace Drupal\Tests\taxonomy_section_paths\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy_section_paths\Service\NodeChangeDetector;
use Drupal\node\NodeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Tests\taxonomy_section_paths\Stub\FieldValueStub;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;

/**
 * Tests NodeChangeDetector.
 *
 * @group taxonomy_section_paths
 */
class NodeChangeDetectorTest extends KernelTestBase {


  protected ConfigFactoryInterface $configFactoryMock;
  protected NodeChangeDetector $nodeChangeDetector;
  
  protected function setUp(): void {
      parent::setUp();

      // Mock ConfigInterface que devuelve el array bundles.
      $configSettingsMock = $this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class);
      $configSettingsMock->method('get')
        ->with('bundles')
        ->willReturn([
          'article' => ['field' => 'field_tags'],
          // Puedes añadir más bundles si quieres probar otros casos.
        ]);

      // Mock ConfigFactoryInterface que devuelve el ConfigInterface mock.
      $this->configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
      $this->configFactoryMock->method('get')
        ->with('taxonomy_section_paths.settings')
        ->willReturn($configSettingsMock);

      // Instancia del servicio con la configuración mock.
      $this->nodeChangeDetector = new NodeChangeDetector($this->configFactoryMock);
  }
  
  public function testNeedsAliasUpdateForNewNode() {
    // Mock ConfigInterface para 'taxonomy_section_paths.settings'.
    $configSettingsMock = $this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class);
    $configSettingsMock->method('get')
      ->with('bundles')
      ->willReturn([
        'article' => ['field' => 'field_tags'],
      ]);

    // Mock ConfigFactoryInterface que devuelve el ConfigInterface mock.
    $configFactoryMock = $this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class);
    $configFactoryMock->method('get')
      ->with('taxonomy_section_paths.settings')
      ->willReturn($configSettingsMock);

    $nodeChangeDetector = new NodeChangeDetector($configFactoryMock);

    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('article');
    $node->method('hasField')->with('field_tags')->willReturn(true);

    // Para un nuevo nodo (no update), siempre devuelve TRUE.
    $result = $nodeChangeDetector->needsAliasUpdate($node, FALSE);
    $this->assertTrue($result);
  }

  public function testNeedsAliasUpdateForUpdateNoChange() {
    // Mock ConfigInterface para 'taxonomy_section_paths.settings'.
    $configSettingsMock = $this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class);
    $configSettingsMock->method('get')
      ->with('bundles')
      ->willReturn([
        'article' => ['field' => 'field_tags'],
      ]);

    // Mock ConfigFactoryInterface que devuelve el ConfigInterface mock.
    $configFactoryMock = $this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class);
    $configFactoryMock->method('get')
      ->with('taxonomy_section_paths.settings')
      ->willReturn($configSettingsMock);

    $nodeChangeDetector = new NodeChangeDetector($configFactoryMock);

    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('article');
    $node->method('hasField')->with('field_tags')->willReturn(true);

    // Mock para el nodo original con misma label y target_id
    $original = $this->createMock(NodeInterface::class);
    $original->method('label')->willReturn('Same Label');
    $original->method('get')->willReturnMap([
      ['field_tags', new FieldValueStub(['target_id' => 123])],
    ]);

    // Nodo actual con mismo label y target_id
    $node->method('label')->willReturn('Same Label');

    $node->method('get')->willReturnMap([
      ['field_tags', new FieldValueStub(['target_id' => 123])],
      ['original', $original],
    ]);

    $result = $nodeChangeDetector->needsAliasUpdate($node, TRUE);
    $this->assertFalse($result);
  }

  public function testNeedsAliasUpdateForUpdateChanged() {
    // Mock ConfigInterface para 'taxonomy_section_paths.settings'.
    $configSettingsMock = $this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class);
    $configSettingsMock->method('get')
      ->with('bundles')
      ->willReturn([
        'article' => ['field' => 'field_tags'],
      ]);

    // Mock ConfigFactoryInterface que devuelve el ConfigInterface mock.
    $configFactoryMock = $this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class);
    $configFactoryMock->method('get')
      ->with('taxonomy_section_paths.settings')
      ->willReturn($configSettingsMock);

    $nodeChangeDetector = new NodeChangeDetector($configFactoryMock);

    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('article');
    $node->method('hasField')->with('field_tags')->willReturn(true);

    // Nodo original con label y target_id distintos
    $original = $this->createMock(NodeInterface::class);
    $original->method('label')->willReturn('Old Label');
    $original->method('get')->willReturnMap([
      ['field_tags', new FieldValueStub(['target_id' => 123])],
    ]);

    $node->method('label')->willReturn('New Label');

    $node->method('get')->willReturnMap([
      ['field_tags', new FieldValueStub(['target_id' => 456])],
      ['original', $original],
    ]);

    $result = $nodeChangeDetector->needsAliasUpdate($node, TRUE);
    $this->assertTrue($result);
  }

}
