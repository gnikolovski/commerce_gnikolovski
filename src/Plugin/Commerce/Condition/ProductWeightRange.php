<?php

namespace Drupal\commerce_gnikolovski\Plugin\Commerce\Condition;

use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\physical\MeasurementType;
use Drupal\physical\Weight;

/**
 * Provides the shipment weight range condition for shipments.
 *
 * @CommerceCondition(
 *   id = "shipment_weight_range",
 *   label = @Translation("Shipment weight range"),
 *   display_label = @Translation("Limit by shipment weight range"),
 *   category = @Translation("Shipment"),
 *   entity_type = "commerce_shipment",
 * )
 */
class ProductWeightRange extends ConditionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'from_weight' => NULL,
      'to_weight' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['from_weight'] = [
      '#type' => 'physical_measurement',
      '#measurement_type' => MeasurementType::WEIGHT,
      '#title' => $this->t('From weight'),
      '#default_value' => $this->configuration['from_weight'],
      '#required' => TRUE,
    ];
    $form['to_weight'] = [
      '#type' => 'physical_measurement',
      '#measurement_type' => MeasurementType::WEIGHT,
      '#title' => $this->t('To weight'),
      '#default_value' => $this->configuration['to_weight'],
      '#required' => TRUE,
    ];
    $form['notice'] = [
      '#type' => 'markup',
      '#markup' => $this->t('NOTICE: This shipping method will be applied if products (all products in the cart) weight is greater than or equal to "From weight" and less than "To weight".'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);
    if ($values['from_weight']['unit'] != $values['to_weight']['unit']) {
      $form_state->setErrorByName('conditions', $this->t('You must select the same weight unit for both values.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);
    $this->configuration['from_weight'] = $values['from_weight'];
    $this->configuration['to_weight'] = $values['to_weight'];
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $entity;
    $weight = $shipment->getWeight();
    if (!$weight) {
      // The conditions can't be applied until the weight is known.
      return FALSE;
    }
    // These two units must be the same.
    $condition_unit_from = $this->configuration['from_weight']['unit'];
    $condition_unit_to = $this->configuration['to_weight']['unit'];
    /** @var \Drupal\physical\Weight $weight */
    $weight = $weight->convert($condition_unit_from);
    $condition_weight_from = new Weight($this->configuration['from_weight']['number'], $condition_unit_from);
    $condition_weight_to = new Weight($this->configuration['to_weight']['number'], $condition_unit_to);

    if ($weight->greaterThanOrEqual($condition_weight_from) && $weight->lessThan($condition_weight_to)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

}
