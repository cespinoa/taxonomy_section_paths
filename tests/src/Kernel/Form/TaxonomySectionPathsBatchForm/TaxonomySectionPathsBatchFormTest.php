<?php

namespace Drupal\Tests\taxonomy_section_paths\Kernel\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy_section_paths\Form\TaxonomySectionPathsBatchForm;
use Drupal\taxonomy_section_paths\Service\BatchRegenerationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

require_once __DIR__ . '/../../../Stub/BatchStub.php';

/**
 * Tests for TaxonomySectionPathsBatchForm.
 *
 * @group taxonomy_section_paths
 */
class TaxonomySectionPathsBatchFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'taxonomy',
    'config_test',
    'taxonomy_section_paths',
    'path_alias',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * The form under test.
   */
  protected TaxonomySectionPathsBatchForm $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('node_type');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['node', 'taxonomy']);

    // Crear tipos de contenido y vocabularios.
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();

    // Configuración simulada para bundles.
    $this->config('taxonomy_section_paths.settings')
      ->set('bundles', [
        'article' => ['vocabulary' => 'tags'],
      ])
      ->save();

    // Inyectar servicio messenger.
    $this->container->set('messenger', $this->createMock('Drupal\Core\Messenger\MessengerInterface'));

    // Mock del servicio de batch.
    $batch_builder_mock = $this->getMockBuilder(BatchBuilder::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['toArray'])
      ->getMock();
    $batch_builder_mock->method('toArray')->willReturn([
      'title' => 'Batch regeneración',
    ]);

    $regenerator_mock = $this->createMock(BatchRegenerationService::class);
    $regenerator_mock->method('prepareBatch')->willReturn($batch_builder_mock);

    $this->container->set('taxonomy_section_paths.regenerate_alias', $regenerator_mock);

    // Instanciar el formulario con el container.
    $this->form = TaxonomySectionPathsBatchForm::create($this->container);

    // Dummy batch_set() para evitar errores.
    if (!function_exists('batch_set')) {
      function batch_set(array $batch) {
        // Mock funcional.
      }
    }
  }

  /**
   * Testea el buildForm y validación.
   */
  public function testBuildForm(): void {
    $form = [];
    $form_state = new FormState();
    $built_form = $this->form->buildForm($form, $form_state);

    $this->assertArrayHasKey('bundles', $built_form);
    $this->assertEquals('article (tags)', $built_form['bundles']['#options']['article']);
  }

  /**
   * Testea el submit sin bundles seleccionados.
   */
  public function testSubmitFormWithNoBundlesSelected(): void {
    $form = $this->form->buildForm([], new FormState());
    $form_state = new FormState();
    $form_state->setValue('bundles', []);

    // Verifica que no lanza excepciones.
    $this->form->submitForm($form, $form_state);
    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * Testea el submit con bundles seleccionados.
   */
  public function testSubmitFormWithBundlesSelected(): void {
    $form_state = new FormState();
    $form_state->setValue('bundles', ['article']);

    $form = $this->form->buildForm([], $form_state);

    $this->form->submitForm($form, $form_state);
    $this->assertEmpty($form_state->getErrors());
  }

}
