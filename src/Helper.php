<?php

namespace Drupal\ec_payment_free;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
        'state' => 'completed',
        'amount' => $order->getTotalPrice(),
        'payment_gateway' => $gateway_id,
        'order_id' => $order->id(),
        'remote_id' => $this->generateRemoteId(),
        'remote_state' => 'completed',
      ]);

      $payment->save();

      $this->logger->info('Payment @payment_id created for order @order_id', [
        '@payment_id' => $payment->id(),
        '@order_id' => $order->id(),
      ]);

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
   * Generates a unique remote payment ID.
   *
   * @return string
   *   The unique remote ID.
   */
  protected function generateRemoteId(): string {
    return 'free_' . time() . '_' . uniqid();
  }

}
