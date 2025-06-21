<?php

namespace Drupal\taxonomy_section_paths\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy_section_paths\Service\BatchRegenerationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This form fire the batch regeneration service.
 */
class TaxonomySectionPathsBatchForm extends FormBase {

  /**
   * The batch regeneration service.
   */
  protected BatchRegenerationService $regenerationBatch;

  public function __construct(BatchRegenerationService $regenerationBatch) {
    $this->regenerationBatch = $regenerationBatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('taxonomy_section_paths.regenerate_alias')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'taxonomy_section_paths_batch_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Podrías obtener los vocabularios o bundles desde la configuración.
    $config = $this->config('taxonomy_section_paths.settings');
    $bundles = $config->get('bundles');

    $options = [];
    foreach ($bundles as $bundle => $settings) {
      $options[$bundle] = $bundle . ' (' . $settings['vocabulary'] . ')';
    }

    $form['bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Seleccionar bundles para regenerar alias'),
      '#options' => $options,
      '#default_value' => array_keys($options),
      '#description' => $this->t('Selecciona los bundles cuyos alias deseas regenerar.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Regenerar alias'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_bundles = array_filter($form_state->getValue('bundles'));

    if (empty($selected_bundles)) {
      $this->messenger()->addError($this->t('Debes seleccionar al menos un bundle.'));
      return;
    }

    // Construir el array de vocabularios para el batch.
    $config = $this->config('taxonomy_section_paths.settings');
    $vocabularies = [];

    foreach ($selected_bundles as $bundle) {
      if (isset($config->get('bundles')[$bundle]['vocabulary'])) {
        $vocabularies[$bundle] = $config->get('bundles')[$bundle]['vocabulary'];
      }
    }

    // Preparar y lanzar el batch.
    $batch = $this->regenerationBatch->prepareBatch($vocabularies);
    batch_set($batch->toArray());
  }

}
