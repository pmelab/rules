<?php

namespace Drupal\rules\Plugin\RulesAction;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rules\Core\RulesActionBase;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a generic 'Fetch entities by view' action.
 *
 * @RulesAction(
 *   id = "rules_entity_fetch_by_view",
 *   deriver = "Drupal\rules\Plugin\RulesAction\EntityFetchByViewDeriver",
 *   category = @Translation("Entity")
 * )
 */
class EntityFetchByView extends RulesActionBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $viewStorage;

  /**
   * Constructs an EntityFetchByView object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->viewStorage = $entity_type_manager->getStorage('view');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {

    $view_id = $this->pluginDefinition['view_id'];
    $display_id = $this->pluginDefinition['display_id'];

    // Fetch the list of available contexts.
    $contexts = $this->getContexts();

    // Pull values out of contexts.
    $contexts = array_map(function ($context) {
      return $context->getContextData()->getValue();
    }, $contexts);

    // Convert entities into entity ids.
    $contexts = array_map(function ($context) {
      return $context instanceof EntityInterface ? $context->id() : $context;
    }, $contexts);

    // Request the views executable for the current display.
    $view = $this->viewStorage->load($view_id)->getExecutable();
    $view->setDisplay($display_id);

    $arguments = [];

    // Reverse- loop through the views contextual arguments and skip empty
    // arguments until the first defined one.
    foreach (array_reverse(array_keys($view->display_handler->getOption('arguments'))) as $arg) {
      if ($contexts[$arg] == '' && count($arguments) == 0) {
        continue;
      }
      $arguments[$arg] = $contexts[$arg];
    }

    // Execute the view and pass the result as provided value.
    $view->setArguments($arguments);
    $entities = $view->render($this->pluginDefinition['display_id']) ?: [];
    $this->setProvidedValue('entity_fetched', $entities);
  }
}
