<?php

namespace Drupal\ec_payment_free\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_payment\Plugin\Commerce\CheckoutPane\PaymentInformation;
use Drupal\Core\Form\FormStateInterface;

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
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form): array {
    // Build parent form
    $pane_form = parent::buildPaneForm($pane_form, $form_state, $complete_form);

    // Get current payment method
    $payment_method = $this->getCurrentPaymentMethod($form_state);

    // Use #states to hide billing_information when free payment is selected
    if (isset($pane_form['billing_information'])) {

      // Get all payment method options to build states conditions
      $states_conditions = [];

      // We need to hide when ANY free payment option is selected
      if (isset($pane_form['payment_method']['#options'])) {
        foreach ($pane_form['payment_method']['#options'] as $method_id => $label) {
          if ($this->isFreePayment($method_id)) {
            $states_conditions[] = ['value' => $method_id];
          }
        }
      }

      // Apply #states only if we found free payment methods
      if (!empty($states_conditions)) {
        // Build OR condition for multiple free payment methods
        $or_conditions = ['or'];
        foreach ($states_conditions as $condition) {
          $or_conditions[] = [':input[name="payment_information[payment_method]"]' => $condition];
        }

        $pane_form['billing_information']['#states'] = [
          'invisible' => $or_conditions,
        ];
      }

      // Also hide initially if free payment is already selected
      if ($this->isFreePayment($payment_method)) {
        $pane_form['billing_information']['#access'] = FALSE;
      }
    }

    return $pane_form;
  }

  /**
   * Get current payment method from various sources.
   *
   * @param FormStateInterface $form_state
   *   Form state.
   *
   * @return string|null
   *   Payment method ID or NULL.
   */
  protected function getCurrentPaymentMethod(FormStateInterface $form_state): ?string {
    // Try form_state value
    $payment_method = $form_state->getValue(['payment_information', 'payment_method']);

    if ($payment_method) {
      return $payment_method;
    }

    // Try user input
    $user_input = $form_state->getUserInput();
    if (isset($user_input['payment_information']['payment_method'])) {
      return $user_input['payment_information']['payment_method'];
    }

    // Try order payment_gateway
    if (!$this->order->get('payment_gateway')->isEmpty()) {
      $payment_gateway = $this->order->get('payment_gateway')->entity;
      if ($payment_gateway) {
        return $payment_gateway->getPluginId();
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form): void {
    $payment_method = $form_state->getValue(['payment_information', 'payment_method']);

    // Skip validation for free payment
    if ($this->isFreePayment($payment_method)) {
      return;
    }

    // For other payment methods, use standard validation
    parent::validatePaneForm($pane_form, $form_state, $complete_form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form): void {
    $payment_method = $form_state->getValue(['payment_information', 'payment_method']);

    if ($this->isFreePayment($payment_method)) {
      // For free payment: set payment gateway
      $this->setPaymentGatewayForFreePayment();

      // IMPORTANT: Remove any existing billing_profile from order
      if (!$this->order->get('billing_profile')->isEmpty()) {
        \Drupal::logger('ec_payment_free')->notice('Removing existing billing profile for free payment order @order_id', [
          '@order_id' => $this->order->id(),
        ]);

        $this->order->set('billing_profile', NULL);
        $this->order->save();
      }

      // Clear billing data from form_state
      $form_state->unsetValue(['payment_information', 'billing_information']);

      return;
    }

    // For other payment methods, use standard submit
    parent::submitPaneForm($pane_form, $form_state, $complete_form);
  }

  /**
   * Set payment gateway for free payment orders.
   */
  protected function setPaymentGatewayForFreePayment(): void {
    try {
      $payment_gateway_storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');
      $payment_gateways = $payment_gateway_storage->loadByProperties([
        'plugin' => 'ec_payment_free',
        'status' => TRUE,
      ]);

      if (empty($payment_gateways)) {
        \Drupal::logger('ec_payment_free')->error('No active free payment gateway found');
        return;
      }

      $payment_gateway = reset($payment_gateways);
      $this->order->set('payment_gateway', $payment_gateway);
      $this->order->save();
    }
    catch (\Exception $e) {
      \Drupal::logger('ec_payment_free')->error('Error setting payment gateway: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Check if payment method is free payment.
   *
   * @param string|null $payment_method
   *   Payment method ID.
   *
   * @return bool
   *   TRUE if free payment.
   */
  protected function isFreePayment(?string $payment_method): bool {
    if (!$payment_method) {
      return FALSE;
    }

    return ($payment_method === 'free_payment');
  }

}
