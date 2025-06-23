<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit\Factory\AliasFactory;

use Drupal\taxonomy_section_paths\Factory\AliasFactory;
use Drupal\path_alias\Entity\PathAlias;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Drupal\taxonomy_section_paths\Factory\AliasFactory
 */
class AliasFactoryTest extends TestCase {


  /**
   * @group taxonomy_section_paths
   * @covers \Drupal\taxonomy_section_paths\Factory\AliasFactory::__construct
   * @covers \Drupal\taxonomy_section_paths\Factory\AliasFactory::create
   * @scenario AliasFactory uses the injected callable to create a PathAlias entity.
   * @context A custom entity creator callable is passed to the factory.
   * @expected The factory should call the custom callable exactly once with the provided values,
   *           returning the expected PathAlias mock.
   */
  public function testCreateCallsEntityCreator() {
    $values = ['path' => '/x', 'alias' => '/y'];

    $mockPathAlias = $this->createMock(PathAlias::class);
    $factory = new AliasFactory(function($vals) use ($mockPathAlias, $values) {
      $this->assertEquals($values, $vals);
      return $mockPathAlias;
    });

    $result = $factory->create($values);

    $this->assertSame($mockPathAlias, $result);
  }
}
