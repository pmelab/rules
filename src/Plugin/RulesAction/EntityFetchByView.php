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
    $view = $this->viewStorage->load($this->pluginDefinition['view_id'])->getExecutable();
    $arguments = [];

    foreach ($this->getContexts() as $name => $context) {
      $data = $context->getContextData()->getValue();
      $arguments[$name] = $data instanceof EntityInterface ? $data->id() : $data;
    }

    $view->setDisplay($this->pluginDefinition['display_id']);

    $real_args = array_map(function ($key) use ($arguments) {
      return $arguments[$key];
    }, array_keys($view->display_handler->getOption('arguments')));

    $view->setArguments($real_args);

    $entities = $view->render($this->pluginDefinition['display_id']);
    $this->setProvidedValue('entity_fetched', $entities ? $entities : []);
  }
}