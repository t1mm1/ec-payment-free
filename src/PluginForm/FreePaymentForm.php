<?php

namespace Drupal\ec_payment_free\PluginForm;

use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the payment offsite form for Free Payment.
 */
class FreePaymentForm extends PaymentOffsiteForm {

  /**
   * {@inheritdoc}
   * @throws NeedsRedirectException
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $data = [
      'return' => $form['#return_url'],
      'cancel' => $form['#cancel_url'],
    ];

    return $this->buildRedirectForm($form, $form_state, $form['#return_url'], $data, 'get');
  }

}
