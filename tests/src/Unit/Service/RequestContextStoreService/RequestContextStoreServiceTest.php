<?php

declare(strict_types=1);

namespace Drupal\Tests\taxonomy_section_paths\Unit\Service\RequestContextStoreService;

use Drupal\taxonomy_section_paths\Service\RequestContextStoreService;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\taxonomy_section_paths\Service\RequestContextStoreService
 *
 * @group taxonomy_section_paths
 */
class RequestContextStoreServiceTest extends TestCase {

  protected RequestContextStoreService $store;

  protected function setUp(): void {
    parent::setUp();
    $this->store = new RequestContextStoreService();
  }

  /**
   * @covers ::set
   * @covers ::get
   * @covers ::has
   * @covers ::delete
   */
  public function testSetGetHasDelete(): void {
    $this->store->set('input', '123', 'foo');
    $this->assertTrue($this->store->has('input', '123'));
    $this->assertEquals('foo', $this->store->get('input', '123'));

    $this->store->delete('input', '123');
    $this->assertFalse($this->store->has('input', '123'));
    $this->assertNull($this->store->get('input', '123'));
  }

  /**
   * @covers ::get
   */
  public function testGetWholeGroup(): void {
    $this->store->set('input', 'a', 'A');
    $this->store->set('input', 'b', 'B');

    $expected = ['a' => 'A', 'b' => 'B'];
    $this->assertEquals($expected, $this->store->get('input'));
  }

  /**
   * @covers ::transition
   */
  public function testTransition(): void {
    $this->store->set('input', '99', 'moved-value');

    $result = $this->store->transition('input', 'output', '99');
    $this->assertTrue($result);
    $this->assertNull($this->store->get('input', '99'));
    $this->assertEquals('moved-value', $this->store->get('output', '99'));

    $result = $this->store->transition('input', 'output', 'nonexistent');
    $this->assertFalse($result);
  }

  /**
   * @covers ::countInGroup
   * @covers ::isLastInGroup
   */
  public function testCountAndIsLast(): void {
    $this->assertEquals(0, $this->store->countInGroup('input'));
    $this->assertTrue($this->store->isLastInGroup('input'));

    $this->store->set('input', 'a', 'A');
    $this->assertEquals(1, $this->store->countInGroup('input'));
    $this->assertFalse($this->store->isLastInGroup('input'));

    $this->store->delete('input', 'a');
    $this->assertTrue($this->store->isLastInGroup('input'));
  }

  /**
   * @covers ::clearGroup
   */
  public function testClearGroup(): void {
    $this->store->set('input', '1', 'x');
    $this->store->set('input', '2', 'y');
    $this->assertEquals(2, $this->store->countInGroup('input'));

    $this->store->clearGroup('input');
    $this->assertEquals(0, $this->store->countInGroup('input'));
  }

}
