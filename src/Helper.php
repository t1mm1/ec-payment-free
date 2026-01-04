<?php

namespace Drupal\ec_payment_free;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Helper service for EC Payment Free.
 */
class Helper {

  /**
   * The entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The plugin id value.
   *
   * @var string
   */
  protected string $plugin_id = 'ec_payment_free';

  /**
   * The state.
   *
   * @var string
   */
  protected string $state = 'completed';

  /**
   * Constructs a Helper object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('logger.channel.ec_payment_free');
  }

  /**
   * Creates a payment for an order.
   *
   * @param OrderInterface $order
   *   The order entity.
   * @param string $gateway_id
   *   The payment gateway ID.
   *
   * @return EntityInterface|null
   *   The created payment entity or NULL on failure.
   */
  public function paymentCreate(OrderInterface $order, string $gateway_id): ?EntityInterface {
    try {
      $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');

      $payment = $payment_storage->create([
        'state' => $this->state,
        'amount' => $order->getTotalPrice(),
        'payment_gateway' => $gateway_id,
        'order_id' => $order->id(),
        'remote_id' => $gateway_id. '_' . time() . '_' . uniqid(),
        'remote_state' => $this->state,
      ]);

      $payment->save();
      return $payment;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger->error('Payment storage error: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
    catch (EntityStorageException $e) {
      $this->logger->error('Failed to save payment for order @order_id: @message', [
        '@order_id' => $order->id(),
        '@message' => $e->getMessage(),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Unexpected error creating payment: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

  /**
   * Check and get payment gateway for free payment method.
   *
   * @return PaymentGateway
   */
  public function getPaymentGatewayForFreePayment(): ?PaymentGateway {
    try {
      $payment_gateway_storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');
      $payment_gateways = $payment_gateway_storage->loadByProperties([
        'plugin' => $this->plugin_id,
        'status' => TRUE,
      ]);

      if (empty($payment_gateways)) {
        $this->logger->error('No active free payment gateway found');
        return NULL;
      }

      return reset($payment_gateways);
    }
    catch (\Exception $e) {
      $this->logger->error('Error setting payment gateway: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

  /**
   * Get current payment method from various sources.
   *
   * @param OrderInterface $order
   *   The order entity.
   * @param FormStateInterface $form_state
   *   Form state.
   *
   * @return string|null
   *   Payment plugin ID or NULL.
   */
  public function getCurrentPaymentPluginId(OrderInterface $order, FormStateInterface $form_state): ?string {
    try {
      // Try form_state value.
      $payment_method_value = $form_state->getValue(['payment_information', 'payment_method']);
      if ($payment_method_value) {
        return $this->getGatewayPaymentPluginId($payment_method_value);
      }

      // Try user input.
      $user_input = $form_state->getUserInput();
      if (isset($user_input['payment_information']['payment_method'])) {
        $payment_method_value = $user_input['payment_information']['payment_method'];
        return $this->getGatewayPaymentPluginId($payment_method_value);
      }

      // Try order payment_gateway.
      return $order->get('payment_gateway')->entity?->getPluginId();
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting current payment gateway plugin ID: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

  /**
   * Get payment gateway plugin ID from payment method value.
   *
   * @param string $payment_method_value
   *   Payment method value (payment gateway ID).
   *
   * @return string|null
   *   Payment gateway plugin ID or NULL.
   */
  public function getGatewayPaymentPluginId(string $payment_method_value): ?string {
    try {
      return $this->entityTypeManager
        ->getStorage('commerce_payment_gateway')
        ->load($payment_method_value)
        ?->getPluginId();
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting payment gateway plugin ID: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

  /**
   * Check if payment method is free payment.
   *
   * @param string|null $payment_plugin_id
   *   Payment method ID.
   *
   * @return bool
   *   TRUE if free payment.
   */
  public function isFreePayment(?string $payment_plugin_id): bool {
    return $payment_plugin_id === $this->plugin_id;
  }

}
