<?php

namespace Drupal\ec_payment_free\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_payment\Plugin\Commerce\CheckoutPane\PaymentInformation;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ec_payment_free\Helper;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the payment information pane with free payment support.
 *
 * @CommerceCheckoutPane(
 *   id = "payment_information",
 *   label = @Translation("Payment information"),
 *   default_step = "order_information",
 *   wrapper_element = "fieldset",
 * )
 */
class FreePaymentInformation extends PaymentInformation {

  /**
   * The helper service.
   *
   * @var Helper
   */
  protected Helper $helper;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ?CheckoutFlowInterface $checkout_flow = NULL
  ) {
    /** @var static $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->helper = $container->get('ec_payment_free.helper');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form): array {
    $pane_form = parent::buildPaneForm($pane_form, $form_state, $complete_form);
    if (isset($pane_form['billing_information'])) {
      // Get all payment method options to build states conditions.
      $states_conditions = [];

      // We need to hide when ANY free payment option is selected
      if (isset($pane_form['payment_method']['#options'])) {
        foreach (array_keys($pane_form['payment_method']['#options']) as $payment_method_value) {
          $payment_plugin_id = $this->helper->getGatewayPaymentPluginId($payment_method_value);
          if ($this->helper->isFreePayment($payment_plugin_id)) {
            $states_conditions[] = ['value' => $payment_method_value];
          }
        }
      }

      // Apply #states only if we found free payment methods.
      if (!empty($states_conditions)) {
        // Build OR condition for multiple free payment methods.
        $or_conditions = ['or'];
        foreach ($states_conditions as $condition) {
          $or_conditions[] = [':input[name="payment_information[payment_method]"]' => $condition];
        }

        $pane_form['billing_information']['#states'] = [
          'invisible' => $or_conditions,
        ];
      }

      // Get current payment method and set access FALSE for payment free.
      $payment_plugin_id = $this->helper->getCurrentPaymentPluginId($this->order, $form_state);
      if ($this->helper->isFreePayment($payment_plugin_id)) {
        $pane_form['billing_information']['#access'] = FALSE;
      }
    }

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form): void {
    $payment_method_value = $form_state->getValue(['payment_information', 'payment_method']);

    // Skip validation for free payment.
    $payment_plugin_id = $this->helper->getGatewayPaymentPluginId($payment_method_value);
    if ($this->helper->isFreePayment($payment_plugin_id)) {
      return;
    }

    parent::validatePaneForm($pane_form, $form_state, $complete_form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form): void {
    $payment_method_value = $form_state->getValue(['payment_information', 'payment_method']);

    // For free payment: set payment gateway.
    $payment_plugin_id = $this->helper->getGatewayPaymentPluginId($payment_method_value);
    if ($this->helper->isFreePayment($payment_plugin_id)) {
      $payment_gateway = $this->helper->getPaymentGatewayForFreePayment();
      if ($payment_gateway) {
        $this->order->set('payment_gateway', $payment_gateway)->save();
      }

      // IMPORTANT: Remove any existing billing_profile from order.
      if (!$this->order->get('billing_profile')->isEmpty()) {
        $this->order->set('billing_profile', NULL);
        $this->order->save();
      }

      // Clear billing data from form_state.
      $form_state->unsetValue(['payment_information', 'billing_information']);
      return;
    }

    parent::submitPaneForm($pane_form, $form_state, $complete_form);
  }

}
