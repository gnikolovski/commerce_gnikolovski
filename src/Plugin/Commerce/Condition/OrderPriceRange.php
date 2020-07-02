<?php

namespace Drupal\commerce_gnikolovski\Plugin\Commerce\Condition;

use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the order price condition for orders.
 *
 * @CommerceCondition(
 *   id = "order_price_range",
 *   label = @Translation("Order price range"),
 *   display_label = @Translation("Limit by order price range"),
 *   category = @Translation("Order"),
 *   entity_type = "commerce_order",
 * )
 */
class OrderPriceRange extends ConditionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'type' => NULL,
      'from_amount' => NULL,
      'to_amount' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $from_amount = $this->configuration['from_amount'];
    // An #ajax bug can cause $amount to be incomplete.
    if (isset($from_amount) && !isset($from_amount['number'], $from_amount['currency_code'])) {
      $from_amount = NULL;
    }

    $to_amount = $this->configuration['to_amount'];
    // An #ajax bug can cause $amount to be incomplete.
    if (isset($to_amount) && !isset($to_amount['number'], $to_amount['currency_code'])) {
      $to_amount = NULL;
    }

    $form['type'] = [
      '#title' => $this->t('Type'),
      '#type' => 'select',
      '#options' => [
        'subtotal' => $this->t('Limit by subtotal price'),
        'total' => $this->t('Limit by total price'),
      ],
      '#required' => TRUE,
      '#default_value' => $this->configuration['type'],
      '#description' => $this->t('Subtotal price is price without any order adjustments (fees, promotions, taxes, shipping, etc.). Total price is equal to the price of all products in the cart and all adjustments.'),
    ];
    $form['from_amount'] = [
      '#type' => 'commerce_price',
      '#title' => $this->t('From amount'),
      '#default_value' => $from_amount,
      '#required' => TRUE,
    ];
    $form['to_amount'] = [
      '#type' => 'commerce_price',
      '#title' => $this->t('To amount'),
      '#default_value' => $to_amount,
      '#required' => TRUE,
    ];
    $form['notice'] = [
      '#type' => 'markup',
      '#markup' => $this->t('NOTICE: This shipping method will be applied if order price is greater than or equal to "From amount" and less than "To amount".'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);
    if ($values['from_amount']['currency_code'] != $values['to_amount']['currency_code']) {
      $form_state->setErrorByName('conditions', $this->t('You must select the same currency for both values.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);
    $this->configuration['type'] = $values['type'];
    $this->configuration['from_amount'] = $values['from_amount'];
    $this->configuration['to_amount'] = $values['to_amount'];
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    if ($this->configuration['type'] == 'subtotal') {
      $order_price = $order->getSubtotalPrice();
    }
    else {
      $order_price = $order->getTotalPrice();
    }

    // Both prices will have the same currency.
    $condition_from_price = new Price($this->configuration['from_amount']['number'], $this->configuration['from_amount']['currency_code']);
    $condition_to_price = new Price($this->configuration['to_amount']['number'], $this->configuration['to_amount']['currency_code']);

    if ($order_price->getCurrencyCode() != $condition_from_price->getCurrencyCode()) {
      return FALSE;
    }

    if ($order_price->greaterThanOrEqual($condition_from_price) && $order_price->lessThan($condition_to_price)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

}
