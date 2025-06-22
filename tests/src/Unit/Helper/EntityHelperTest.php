<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit\Helper;

use Drupal\Core\Entity\EntityInterface;
use Drupal\taxonomy_section_paths\Helper\EntityHelper;
use PHPUnit\Framework\TestCase;

class EntityHelperTest extends TestCase {

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


  public function testGetSecureOriginalEntityWithRealObject() {
    $realEntity = new class {
      public object $original;
    };
    $original = new \stdClass();
    $realEntity->original = $original;

    $this->assertSame($original, EntityHelper::getSecureOriginalEntity($realEntity));
  }

  public function testIsPhpUnitMock() {
    $mock = $this->createMock(EntityInterface::class);
    $this->assertTrue(EntityHelper::isPhpUnitMock($mock));

    $real = new \stdClass();
    $this->assertFalse(EntityHelper::isPhpUnitMock($real));
  }

}
