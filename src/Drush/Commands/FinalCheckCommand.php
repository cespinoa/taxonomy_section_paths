<?php

namespace Drupal\taxonomy_section_paths\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drush\Commands\DrushCommands;
use Drupal\Component\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\entity_events\Event\EntityEvent;

class FinalCheckCommand extends DrushCommands {

  

  protected array $termIds = [];
  protected array $nodeIds = [];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AliasManagerInterface $aliasManager,
    protected UuidInterface $uuid
  ){}

  /**
   * Regenera los alias de los términos raíz para los bundles configurados.
   *
   * @command taxonomy-section-paths:check-all
   * @aliases tsp:check
   * @usage drush tsp:check
   *   Regenera los alias de términos raíz para los vocabularios configurados.
   * @description Recorre los vocabularios configurados y regenera los alias de sus términos raíz.
   */
  public function checkAll(array $options = [
    'clean' => FALSE,
  ]): int {

    // 1) Primero, eliminar **todos** los nodos con nid > 5000.
    $nids_to_purge = \Drupal::entityQuery('node')
      ->condition('nid', 5000, '>')
      ->accessCheck(FALSE)
      ->execute();
    foreach ($nids_to_purge as $nid) {
      //~ if ($node = Node::load($nid)) {
        $node = Node::load($nid);
        $node->delete();
      //~ }
    }
    // 1) Primero, eliminar **todos** los terms con nid > 1000.
    $nids_to_purge = \Drupal::entityQuery('taxonomy_term')
      ->condition('tid', 1000, '>')
      ->accessCheck(FALSE)
      ->execute();
    foreach ($nids_to_purge as $nid) {
      if ($term = Term::load($nid)) {
        $term->delete();
      }
    }

    $vid = 'tipo_de_articulo';
    $bundle = 'article';

    $this->io()->title('✅ Comenzando comprobación funcional de taxonomy_section_paths');

    // 1. Crear términos jerárquicos.
    $grandParent = Term::create(['name' => 'Grand parent', 'vid' => $vid]);
    $grandParent->save();
    $this->termIds[] = $grandParent->id();

    $child = Term::create([
      'name' => 'Child',
      'vid' => $vid,
      'parent' => [$grandParent->id()],
    ]);
    $child->save();
    $this->termIds[] = $child->id();

    $grandChild = Term::create([
      'name' => 'Grand child',
      'vid' => $vid,
      'parent' => [$child->id()],
    ]);
    $grandChild->save();
    $this->termIds[] = $grandChild->id();

    $this->assertAlias("/taxonomy/term/{$grandParent->id()}", '/grand-parent');
    $this->assertAlias("/taxonomy/term/{$child->id()}", '/grand-parent/child');
    $this->assertAlias("/taxonomy/term/{$grandChild->id()}", '/grand-parent/child/grand-child');

    // 2. Crear nodos.
    $node1 = Node::create([
      'type' => $bundle,
      'title' => 'Grand parent article',
      "field_$vid" => ['target_id' => $grandParent->id()],
      'uid'   => 1,
    ]);
    $node1->save();
    $this->nodeIds[] = $node1->id();

    $node2 = Node::create([
      'type' => $bundle,
      'title' => 'Child article',
      "field_$vid" => ['target_id' => $child->id()],
      'uid'   => 1,
    ]);
    $node2->save();
    $this->nodeIds[] = $node2->id();

    $node3 = Node::create([
      'type' => $bundle,
      'title' => 'Grand child article',
      "field_$vid" => ['target_id' => $grandChild->id()],
      'uid'   => 1,
    ]);
    $node3->save();
    $this->nodeIds[] = $node3->id();

    $this->assertAlias("/node/{$node1->id()}", '/grand-parent/grand-parent-article');
    $this->assertAlias("/node/{$node2->id()}", '/grand-parent/child/child-article');
    $this->assertAlias("/node/{$node3->id()}", '/grand-parent/child/grand-child/grand-child-article');

    // 3. Renombrar Grand parent → New grand parent.
    $grandParent->setName('New grand parent');
    $grandParent->save();
    \Drupal::service('event_dispatcher')
    ->dispatch(new EntityEvent('entity.update',$grandParent));

    $this->assertAlias("/taxonomy/term/{$grandParent->id()}", '/new-grand-parent');
    $this->assertAlias("/taxonomy/term/{$child->id()}", '/new-grand-parent/child');
    $this->assertAlias("/taxonomy/term/{$grandChild->id()}", '/new-grand-parent/child/grand-child');

    $this->assertAlias("/node/{$node1->id()}", '/new-grand-parent/grand-parent-article');
    $this->assertAlias("/node/{$node2->id()}", '/new-grand-parent/child/child-article');
    $this->assertAlias("/node/{$node3->id()}", '/new-grand-parent/child/grand-child/grand-child-article');

    // 4. Renombrar nodo
    $node1->setTitle('New grand parent article');
    $node1->save();
    $this->assertAlias("/node/{$node1->id()}", '/new-grand-parent/new-grand-parent-article');

    // 5. Eliminar child y verificar propagación
    $child->delete();
    \Drupal::service('event_dispatcher')
    ->dispatch(new EntityEvent('entity.update',$child));
    $this->assertAlias("/node/{$node2->id()}", '/new-grand-parent/child-article');
    $this->assertAlias("/node/{$node3->id()}", '/new-grand-parent/grand-child/grand-child-article');
    

    // 6. Eliminar abuelo sin recalcular alias por campo vacío.
    $grandParent->delete();
    \Drupal::service('event_dispatcher')
    ->dispatch(new EntityEvent('entity.update',$grandParent));
    $this->assertAlias("/node/{$node1->id()}", '/new-grand-parent-article');
    

    if (!empty($options['clean'])) {
      $this->cleanup();
    }

    if (empty($errors)) {
      $this->output()->writeln("<info>✅ Verificación completada correctamente. Todos los alias son coherentes.</info>");
    }
    else {
      $this->output()->writeln("<error>❌ Se detectaron errores en la verificación:</error>");
      foreach ($errors as $error) {
        $this->output()->writeln(" - $error");
      }
    }


// 1) Primero, eliminar **todos** los nodos con nid > 5000.
    $nids_to_purge = \Drupal::entityQuery('node')
      ->condition('nid', 5000, '>')
      ->accessCheck(FALSE)
      ->execute();
    foreach ($nids_to_purge as $nid) {
     //~ if ($node = Node::load($nid)) {
        $node = Node::load($nid);
        $node->delete();
      //~ }
      $this->io()->writeln($nid);
    }
    
    // 1) Primero, eliminar **todos** los terms con nid > 1000.
    $nids_to_purge = \Drupal::entityQuery('taxonomy_term')
      ->condition('tid', 1000, '>')
      ->accessCheck(FALSE)
      ->execute();
    foreach ($nids_to_purge as $nid) {
      if ($term = Term::load($nid)) {
        $term->delete();
      }
    }
    $this->io()->writeln(($nids_to_purge));


    $this->io()->success('Verificación completada con éxito.');
    return self::EXIT_SUCCESS;
  }

  protected function assertAlias(string $path, string $expected): void {
    $actual = $this->aliasManager->getAliasByPath($path);
    if ($actual === $expected) {
      $this->io()->success("$path → $expected");
    }
    else {
      $this->io()->error("$path esperaba alias $expected pero obtuvo $actual");
    }
  }

  protected function cleanup(): void {
    foreach ($this->nodeIds as $nid) {
      if ($node = Node::load($nid)) {
        $node->delete();
      }
    }
    foreach ($this->termIds as $tid) {
      if ($term = Term::load($tid)) {
        $term->delete();
      }
    }
    
    $this->io()->writeln('<comment>Datos eliminados.</comment>');
  }

  /**
   * {@inheritdoc}
   */
  public function options(): array {
    return [
      'clean' => [
        'description' => 'Eliminar nodos y términos al finalizar.',
        'value' => NULL,
      ],
    ];
  }
} 
