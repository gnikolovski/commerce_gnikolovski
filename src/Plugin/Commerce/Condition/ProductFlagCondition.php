<?php

namespace Drupal\commerce_gnikolovski\Plugin\Commerce\Condition;

use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the order product flag condition.
 *
 * @CommerceCondition(
 *   id = "product_flag",
 *   label = @Translation("Product flag"),
 *   display_label = @Translation("Limit by product flag"),
 *   category = @Translation("Order"),
 *   entity_type = "commerce_order",
 * )
 */
class ProductFlagCondition extends ConditionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'flag' => NULL,
      'negate' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['flag'] = [
      '#title' => $this->t('Flag'),
      '#description' => $this->t('All products must be flagged with this flag, for this condition to apply.'),
      '#type' => 'select',
      '#options' => $this->getFlags(),
      '#required' => TRUE,
      '#default_value' => $this->configuration['flag'],
    ];

    $form['negate'] = [
      '#title' => $this->t('Negate'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['negate'],
    ];

    return $form;
  }

  /**
   * Gets available flags.
   */
  protected function getFlags() {
    $available_flags = [];

    $flags = \Drupal::entityTypeManager()
      ->getStorage('flag')
      ->loadMultiple();

    foreach ($flags as $flag) {
      if ($flag->getFlaggableEntityTypeId() == 'commerce_product') {
        $available_flags[$flag->id()] = $flag->label();
      }
    }

    return $available_flags;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);
    $this->configuration['flag'] = $values['flag'];
    $this->configuration['negate'] = $values['negate'];
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;

    /** @var \Drupal\flag\FlagServiceInterface $flag_service */
    $flag_service = \Drupal::service('flag');
    $flag = $flag_service->getFlagById($this->configuration['flag']);

    $condition = $this->configuration['negate'];

    if (!$flag) {
      return $condition;
    }

    foreach ($order->getItems() as $item) {
      $variation = $item->getPurchasedEntity();
      if (!$variation) {
        return $condition;
      }

      $product = $variation->getProduct();
      if (!$product) {
        return $condition;
      }

      $flagging = $flag_service->getFlagging($flag, $product);
      if (!$flagging) {
        return $condition;
      }
    }

    return !$condition;
  }

}
