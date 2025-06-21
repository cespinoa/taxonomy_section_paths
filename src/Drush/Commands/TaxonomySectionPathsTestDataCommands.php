<?php

namespace Drupal\taxonomy_section_paths\Drush\Commands;

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drush\Commands\DrushCommands;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Uuid\UuidInterface;

/**
 * Insert and delete taxonomy terms and nodes for tests purposes.
 */
class TaxonomySectionPathsTestDataCommands extends DrushCommands {

  /**
   * The config factory interface.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The uuid factory interface.
   */
  protected UuidInterface $uuid;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, UuidInterface $uuid) {
    $this->configFactory = $config_factory;
    $this->uuid = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('config.factory'),
      $container->get('uuid')
    );
  }

  /**
   * Genera datos de prueba (términos y nodos).
   *
   * @command taxonomy-section-paths:generate-test-data
   * @aliases tsp:generate-test
   */
  public function generateTestData(): int {
    $config = $this->configFactory->get('taxonomy_section_paths.settings')->get('bundles');
    if (empty($config)) {
      $this->output()->writeln('<error>❌ El módulo no ha sido configurado aún.</error>');
      return self::EXIT_FAILURE;
    }

    $options = [];
    $index = 1;
    foreach ($config as $bundle => $values) {
      $vid = $values['vocabulary'] ?? NULL;
      if ($vid) {
        $options[$index++] = ['bundle' => $bundle, 'vid' => $vid];
      }
    }
    $options[$index] = 'Todos';

    $this->output()->writeln("Elige el bundle-vocabulary a usar:");
    foreach ($options as $i => $data) {
      if (is_array($data)) {
        $this->output()->writeln("  [$i] Bundle: {$data['bundle']} — Vocabulario: {$data['vid']}");
      }
      else {
        $this->output()->writeln("  [$i] Todos");
      }
    }

    $selection = $this->io()->ask("Selecciona una opción (número)", '1');
    if (!isset($options[(int) $selection])) {
      $this->output()->writeln("<error>Opción inválida.</error>");
      return self::EXIT_FAILURE;
    }

    $targets = is_array($options[(int) $selection])
      ? [$options[(int) $selection]]
      : array_map(
          fn($bundle, $values) => ['bundle' => $bundle, 'vid' => $values['vocabulary']],
          array_keys($config), $config
        );

    $term_ids = [];
    $node_ids = [];

    foreach ($targets as $target) {
      $bundle = $target['bundle'];
      $vid = $target['vid'];

      $this->output()->writeln("📂 Generando datos para bundle <info>$bundle</info> y vocabulario <info>$vid</info>");

      foreach (['AAA', 'ABB', 'ACC', 'ADD', 'AEE'] as $prefix) {
        $parent = Term::create(['name' => $prefix, 'vid' => $vid]);
        $parent->save();
        $term_ids[] = $parent->id();

        for ($i = 0; $i < 5; $i++) {
          $child = Term::create([
            'name' => $prefix . '-' . chr(65 + $i),
            'vid' => $vid,
            'parent' => [$parent->id()],
          ]);
          $child->save();
          $term_ids[] = $child->id();

          for ($j = 0; $j < 5; $j++) {
            $name = substr($this->uuid->generate(), 0, 10);
            $grandchild = Term::create([
              'name' => $prefix . '-' . chr(65 + $i) . '-' . $name,
              'vid' => $vid,
              'parent' => [$child->id()],
            ]);
            $grandchild->save();
            $term_ids[] = $grandchild->id();
          }
        }
      }

      $terms = Term::loadMultiple($term_ids);
      foreach ($terms as $term) {
        for ($k = 0; $k < 10; $k++) {
          $node = Node::create([
            'type' => $bundle,
            'title' => 'tsp-test-' . substr($this->uuid->generate(), 0, 8),
            "field_$vid" => ['target_id' => $term->id()],
          ]);
          $node->save();
          $node_ids[] = $node->id();
        }
      }
    }

    $data = ['terms' => $term_ids, 'nodes' => $node_ids];
    file_put_contents(DRUPAL_ROOT . '/modules/custom/taxonomy_section_paths/test_ids.json', json_encode($data, JSON_PRETTY_PRINT));
    $this->output()->writeln("<info>✅ Datos de prueba creados con éxito.</info>");

    return self::EXIT_SUCCESS;
  }

  /**
   * Elimina los datos de prueba generados anteriormente.
   *
   * @command taxonomy-section-paths:delete-test-data
   * @aliases tsp:delete-test
   */
  public function deleteTestData(): int {
    $path = DRUPAL_ROOT . '/modules/custom/taxonomy_section_paths/test_ids.json';

    if (!file_exists($path)) {
      $this->output()->writeln('<comment>⚠ No se encontró test_ids.json.</comment>');
      return self::EXIT_SUCCESS;
    }

    $data = json_decode(file_get_contents($path), TRUE);
    if (!is_array($data)) {
      $this->output()->writeln('<error>❌ test_ids.json no es válido.</error>');
      return self::EXIT_FAILURE;
    }

    foreach ($data['nodes'] ?? [] as $nid) {
      if ($node = Node::load($nid)) {
        $node->delete();
        $this->output()->writeln("🗑 Nodo $nid eliminado.");
      }
    }

    foreach ($data['terms'] ?? [] as $tid) {
      if ($term = Term::load($tid)) {
        $term->delete();
        $this->output()->writeln("🗑 Término $tid eliminado.");
      }
    }

    unlink($path);
    $this->output()->writeln("<info>✅ Archivo test_ids.json eliminado.</info>");
    return self::EXIT_SUCCESS;
  }

}
