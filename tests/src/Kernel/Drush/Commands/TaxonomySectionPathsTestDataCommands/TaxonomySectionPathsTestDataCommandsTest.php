<?php

namespace Drupal\Tests\taxonomy_section_paths\Kernel\Drush\Commands;

use Drupal\KernelTests\KernelTestBase;
use Drush\TestTraits\DrushTestTrait; // Requiere drush test traits instalados
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drush\Commands\DrushCommands;
use Drupal\taxonomy_section_paths\Drush\Commands\TaxonomySectionPathsTestDataCommands;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Drush\Style\DrushStyle;

require_once __DIR__ . '/DrushStyleStub.php';

//~ use function file_exists;
//~ use function file_get_contents;
//~ use function json_decode;
//~ use function unlink;

/**
 * Test para comandos Drush de TaxonomySectionPathsTestDataCommands.
 *
 * @group taxonomy_section_paths
 */
class TaxonomySectionPathsTestDataCommandsTest extends KernelTestBase {
  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  //~ /**
   //~ * @var \Drupal\taxonomy_section_paths\Drush\Commands\TaxonomySectionPathsTestDataCommands
   //~ */
  //~ protected $command;


  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'node',
    'taxonomy',
    'field',
    'user',
    'taxonomy_section_paths',
    'config_test',
    'path_alias',
    'text',
  ];

  /** @var TaxonomySectionPathsTestDataCommands&MockObject */
  protected $command;



  protected function setUp(): void {
    parent::setUp();

    //~ echo "hola";
    //~ $dir = __DIR__ . '/DrushStyleStub.php';
    //~ var_dump($dir);
    //~ echo "hola";

    if (!defined('DRUPAL_TEST_IN_CHILD_SITE')) {
      define('DRUPAL_TEST_IN_CHILD_SITE', FALSE);
    }

    $this->installEntitySchema('user');
    $this->installConfig(['user']);              // Configuración mínima de user
    $this->installEntitySchema('node_type');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('taxonomy_term');
    
    $this->installConfig(['taxonomy_section_paths']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();

    $this->config('taxonomy_section_paths.settings')
      ->set('bundles', ['article' => ['vocabulary' => 'tags']])
      ->save();

    // Instanciar el stub DrushStyle.
    $input = new ArgvInput([]);
    $output = new BufferedOutput();
    $drushStyle = new DrushStyle($input, $output);

    // Crear un anon class que extiende tu comando y sobreescribe io() y output()
    $this->command = new class(
      $this->container->get('config.factory'),
      $this->container->get('uuid'),
      $drushStyle,
      $drushStyle
    ) extends TaxonomySectionPathsTestDataCommands {
      protected DrushStyle $ioStyle;
      protected DrushStyle $outStyle;
      public function __construct($configFactory, $uuid, DrushStyle $ioStyle, DrushStyle $outStyle) {
        parent::__construct($configFactory, $uuid);
        $this->ioStyle = $ioStyle;
        $this->outStyle = $outStyle;
      }
      public function io(): DrushStyle { return $this->ioStyle; }
      public function output(): DrushStyle { return $this->outStyle; }
    };
  }




  public function testGenerateAndDeleteDataCommands(): void {
    // Ejecuta el método de generación.
    $result_generate = $this->command->generateTestData();
    $this->assertEquals(0, $result_generate, 'El comando generateTestData debería finalizar con éxito.');

    // Verifica que el archivo se haya creado y tenga contenido válido.
    $path = DRUPAL_ROOT . '/modules/custom/taxonomy_section_paths/test_ids.json';
    $this->assertFileExists($path, 'El archivo test_ids.json debería haberse creado.');

    $data = json_decode(file_get_contents($path), TRUE);
    $this->assertIsArray($data);
    $this->assertNotEmpty($data['terms'], 'Debe haber términos creados.');
    $this->assertNotEmpty($data['nodes'], 'Debe haber nodos creados.');

    // Ejecuta el método de borrado.
    $result_delete = $this->command->deleteTestData();
    $this->assertEquals(0, $result_delete, 'El comando deleteTestData debería finalizar con éxito.');

    // Verifica que el archivo ya no exista.
    $this->assertFileDoesNotExist($path, 'El archivo test_ids.json debería haberse eliminado.');
  }


}
