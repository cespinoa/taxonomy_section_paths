<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit\Helper\EntityHelper;

use Drupal\Core\Entity\EntityInterface;
use Drupal\taxonomy_section_paths\Helper\EntityHelper;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the EntityHelper utility class.
 *
 * @group taxonomy_section_paths
 */
class EntityHelperTest extends TestCase {

  /**
   * @covers \Drupal\taxonomy_section_paths\Helper\EntityHelper::getSecureOriginalEntity
   * @scenario Entity is a PHPUnit mock object with 'original' property
   * @context Mock object returns an 'original' stdClass object
   * @expected Returns the mocked original object correctly
   */
  public function testGetSecureOriginalEntityWithMock() {
    $mockOriginal = new \stdClass();
    $mockOriginal->mocked = true;

    $mock = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['get'])
      ->getMock();

    $mock->expects($this->once())
      ->method('get')
      ->with('original')
      ->willReturn($mockOriginal);

    $result = EntityHelper::getSecureOriginalEntity($mock);
    $this->assertNotNull($result);
    $this->assertTrue($result->mocked);
  }


  /**
   * @covers \Drupal\taxonomy_section_paths\Helper\EntityHelper::getSecureOriginalEntity
   * @scenario Entity is a real object with public 'original' property
   * @context Access original property directly
   * @expected Returns the original object correctly
   */
  public function testGetSecureOriginalEntityWithRealObject() {
    $realEntity = new class {
      public object $original;
    };
    $original = new \stdClass();
    $realEntity->original = $original;

    $this->assertSame($original, EntityHelper::getSecureOriginalEntity($realEntity));
  }

  /**
   * @covers \Drupal\taxonomy_section_paths\Helper\EntityHelper::isPhpUnitMock
   * @scenario Test detection of PHPUnit mock objects
   * @context Test with a PHPUnit mock and a normal stdClass object
   * @expected Returns true for PHPUnit mock, false otherwise
   */
  public function testIsPhpUnitMock() {
    $mock = $this->createMock(EntityInterface::class);
    $this->assertTrue(EntityHelper::isPhpUnitMock($mock));

    $real = new \stdClass();
    $this->assertFalse(EntityHelper::isPhpUnitMock($real));
  }

}
