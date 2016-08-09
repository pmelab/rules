<?php

namespace Drupal\Rules\Plugin\views\style;

use Drupal\Core\Entity\EntityInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\views\ResultRow;

/**
 * @ViewsStyle(
 *   id = "rules",
 *   title = @Translation("Rules entity listing"),
 *   help = @Translation("Returns a list of entities as result"),
 *   theme = "views_view_unformatted",
 *   register_theme = FALSE,
 *   display_types = {"rules"}
*  )
 */
class Rules extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesFields = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowClass = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = FALSE;

  /**
   * {@inheritdoc}
   */
  public function render() {

    $entities = array_map(function (ResultRow $row) {
      return $row->_entity;
    }, $this->view->result);
    
    $entities = array_filter($entities, function ($value) { return (bool) $value; });
    
    if (!empty($this->view->live_preview)) {
      return [
        '#theme' => 'item_list',
        '#items' => array_map(function (EntityInterface $entity) {
          return $entity->label();
        }, $entities)
      ];
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function evenEmpty() {
    return TRUE;
  }
}