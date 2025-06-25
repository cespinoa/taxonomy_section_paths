<?php

namespace Drupal\Tests\taxonomy_section_paths\Kernel\Form;

use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy_section_paths\Form\TaxonomySectionPathsSettingsForm;
use Drupal\Core\Form\FormState;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;


/**
 * Test para el formulario de configuración TaxonomySectionPathsSettingsForm.
 *
 * @group taxonomy_section_paths
 */
class TaxonomySectionPathsSettingsFormTest extends KernelTestBase {


  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Módulos necesarios para el test.
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'taxonomy',
    'path_alias',
    'config_test',           // Necesario para config entities y ConfigFormBase.
    'taxonomy_section_paths',
  ];

  /**
   * Instancia del formulario.
   *
   * @var \Drupal\taxonomy_section_paths\Form\TaxonomySectionPathsSettingsForm
   */
  protected $form;

  /**
   * Configuración.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Instalar esquema necesario para node_type y taxonomy vocabularies.
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('node');
    $this->installEntitySchema('node_type');
    $this->installConfig(['node', 'taxonomy', 'path_alias']);
    $this->installConfig(['node', 'taxonomy']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();

    Vocabulary::create([
      'vid' => 'topics',
      'name' => 'Topics',
    ])->save();

    // Campo en storage.
    FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => 1,
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ])->save();

    // Campo en bundle 'article'.
    FieldConfig::create([
      'field_storage' => FieldStorageConfig::loadByName('node', 'field_tags'),
      'bundle' => 'article',
      'label' => 'Tags',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => ['tags' => 'tags'],
        ],
      ],
    ])->save();

    $this->installConfig(['node', 'taxonomy', 'field']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('system', ['sequences']);

    $this->installConfig(['taxonomy_section_paths']);


    $this->config('taxonomy_section_paths.settings')
    ->set('bundles', [
      'article' => [
        'vocabulary' => 'tags',
      ],
    ])
    ->save();

    // Crear el formulario (container auto-inyecta entityTypeManager).
    $this->form = TaxonomySectionPathsSettingsForm::create($this->container);
  }

  /**
   * Test básico para buildForm().
   */
  public function testBuildForm(): void {
    $form = [];
    $form_state = new FormState();

    $built_form = $this->form->buildForm($form, $form_state);

    $this->assertArrayHasKey('bundles', $built_form);
    $this->assertArrayHasKey('article', $built_form['bundles']);

    $article_bundle = $built_form['bundles']['article'];
    $this->assertArrayHasKey('vocabulary', $article_bundle);
    $this->assertArrayHasKey('field', $article_bundle);

    // Asegura que el vocabulario 'tags' está como opción.
    $this->assertArrayHasKey('tags', $article_bundle['vocabulary']['#options']);

    // Asegura que el campo field_tags está disponible si el vocabulario está seleccionado.
    $this->assertArrayHasKey('field_tags', $article_bundle['field']['#options']);

  }

  /**
   * Test básico para submitForm().
   */
  public function testSubmitForm(): void {
    $form = [];
    $form_state = new FormState();

    // Simular los valores que vendrían del formulario.
    $form_state->setValues([
      'bundles' => [
        'article' => [
          'vocabulary' => 'tags',
          'field' => 'field_tags',
        ],
        'page' => [
          'vocabulary' => 'topics',
          'field' => 'field_topics',
        ],
      ],
      'generate_node_alias_if_term_empty' => TRUE,
      'enable_event_logging' => FALSE,
      'silent_messages' => TRUE,
      'use_batch_for_term_operations' => FALSE,
    ]);

    // Ejecutar submit.
    $this->form->submitForm($form, $form_state);

    // Cargar configuración para comprobar que se guardó.
    $config = $this->config('taxonomy_section_paths.settings');

    $bundles = $config->get('bundles');
    $this->assertArrayHasKey('article', $bundles);
    $this->assertEquals('tags', $bundles['article']['vocabulary']);
    $this->assertEquals('field_tags', $bundles['article']['field']);

    $this->assertEquals(TRUE, $config->get('generate_node_alias_if_term_empty'));
    $this->assertEquals(FALSE, $config->get('enable_event_logging'));
    $this->assertEquals(TRUE, $config->get('silent_messages'));
    $this->assertEquals(FALSE, $config->get('use_batch_for_term_operations'));
  } 


  /**
   * Método auxiliar para obtener la configuración.
   */
  protected function config($name) {
    return $this->container->get('config.factory')->getEditable($name);
  }
}
