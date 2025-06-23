<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit\Service\PathResolverService;

use Drupal\taxonomy_section_paths\Service\PathResolverService;
use Drupal\taxonomy\TermInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy_section_paths\Contract\SlugifierInterface;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\taxonomy_section_paths\Service\PathResolverService
 *
 * @group taxonomy_section_paths
 */
class PathResolverServiceTest extends TestCase {

  protected $slugifier;
  protected $service;

  protected function setUp(): void {
    parent::setUp();

    $this->slugifier = $this->createMock(SlugifierInterface::class);
    $this->service = new PathResolverService($this->slugifier);
  }

  /**
   * @covers ::getFullHierarchy
   */
  public function testGetFullHierarchy(): void {
    $grandparent = $this->createMock(TermInterface::class);
    $parent = $this->createMock(TermInterface::class);
    $term = $this->createMock(TermInterface::class);

    $term->method('label')->willReturn('Child');
    $parent->method('label')->willReturn('Parent');
    $grandparent->method('label')->willReturn('Grandparent');

    $term->method('get')->with('parent')->willReturn((object) ['entity' => $parent]);
    $parent->method('get')->with('parent')->willReturn((object) ['entity' => $grandparent]);
    $grandparent->method('get')->with('parent')->willReturn((object) ['entity' => NULL]);

    $result = $this->service->getFullHierarchy($term);
    $this->assertEquals(['Grandparent', 'Parent', 'Child'], $result);
  }

  /**
   * @covers ::getTermAliasPath
   */
  public function testGetTermAliasPath(): void {
    $term = $this->createMock(TermInterface::class);
    $term->method('label')->willReturn('Child');
    $term->method('get')->with('parent')->willReturn((object) ['entity' => NULL]);

    $this->slugifier
      ->method('slugify')
      ->with('Child')
      ->willReturn('child');

    $result = $this->service->getTermAliasPath($term);
    $this->assertEquals('/child', $result);
  }

  /**
   * @covers ::getNodeAliasPath
   */
  public function testGetNodeAliasPathWithTerm(): void {
    $term = $this->createMock(TermInterface::class);
    $node = $this->createMock(NodeInterface::class);

    $term->method('label')->willReturn('Category');
    $term->method('get')->with('parent')->willReturn((object) ['entity' => NULL]);
    $node->method('label')->willReturn('My Node');

    $this->slugifier->method('slugify')->willReturnMap([
      ['Category', 'category'],
      ['My Node', 'my-node'],
    ]);

    $result = $this->service->getNodeAliasPath($term, $node);
    $this->assertEquals('/category/my-node', $result);
  }


  /**
   * @covers ::getNodeAliasPath
   */
  public function testGetNodeAliasPathWithStringPrefix(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('label')->willReturn('Test Node');

    $this->slugifier->method('slugify')->with('Test Node')->willReturn('test-node');

    $result = $this->service->getNodeAliasPath('/section', $node);
    $this->assertEquals('/section/test-node', $result);
  }

}
