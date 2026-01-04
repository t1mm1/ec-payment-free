<?php

namespace Drupal\ec_payment_free\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ec_payment_free\Helper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Free Payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "ec_payment_free",
 *   label = @Translation("Free Payment"),
 *   display_label = @Translation("Free Payment"),
 *   modes = {
 *     "test" = @Translation("Test"),
 *     "live" = @Translation("Live"),
 *   },
 *   payment_type = "payment_default",
 *   forms = {
 *     "offsite-payment" = "Drupal\ec_payment_free\PluginForm\FreePaymentForm",
 *   },
 *   collect_billing_information = FALSE,
 * )
 */
class FreePayment extends OffsitePaymentGatewayBase {

  /**
   * The helper service.
   *
   * @var Helper
   */
  protected Helper $helper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->helper = $container->get('ec_payment_free.helper');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'instructions' => [
        'value' => 'This is a test payment method. No actual payment will be processed. Your order will be automatically approved.',
        'format' => 'basic_html',
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['instructions'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Payment instructions'),
      '#description' => $this->t('Instructions shown to customers during checkout.'),
      '#default_value' => $this->configuration['instructions']['value'],
      '#format' => $this->configuration['instructions']['format'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['instructions'] = $values['instructions'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request): void {
    $this->helper->paymentCreate($order, $this->parentEntity->id());
    $this->messenger()->addStatus($this->t('Your payment has been processed successfully.'));
  }

}
