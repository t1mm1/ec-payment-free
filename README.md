# EC Payment Free

A free payment gateway for Drupal Commerce that automatically approves all payments without collecting billing information. Perfect for testing, demonstrations, or scenarios where payment collection is not required.

## Features

- Automatic payment approval
- No billing address collection required
- Seamless integration with Commerce checkout flow
- Customizable payment instructions
- Automatic billing form hiding on method selection
- Test and Live modes support
- Comprehensive error logging

## Requirements

- Drupal: ^10 || ^11
- Commerce Payment: 3.x

## Installation

1. Download and extract the module to your `modules/custom` directory
2. Enable the module:
   ```bash
   drush en ec_payment_free
   ```
   Or via the UI: Administration → Extend

## Configuration

1. Navigate to **Commerce → Configuration → Payment gateways**
2. Click **Add payment gateway**
3. Select **Free Payment** from the plugin dropdown
4. Configure the gateway:
  - **Label**: Enter a display name (e.g., "Test Payment")
  - **Mode**: Choose Test or Live
  - **Payment instructions**: Customize the message shown to customers during checkout
5. Save the configuration

## Usage

### For Administrators

Once configured, the Free Payment gateway will appear as a payment option during checkout. When selected:
- The billing address form is automatically hidden
- Payment is processed immediately without external validation
- Orders are automatically marked as paid with status "Completed"

### For Developers

The module provides a helper service for programmatic payment creation:

```php
// Get the helper service
$helper = \Drupal::service('ec_payment_free.helper');

// Create a payment for an order
$payment = $helper->paymentCreate($order, $gateway_id);

// Check if payment method is free payment
$is_free = $helper->isFreePayment($payment_plugin_id);
```

## Technical Details

### Key Components

- **FreePayment.php**: Main payment gateway plugin
- **FreePaymentInformation.php**: Custom checkout pane that hides billing fields
- **FreePaymentForm.php**: Offsite payment form handler
- **Helper.php**: Service for payment operations and gateway detection

## Security Note

**This module is intended for testing and development purposes only.** Do not use it in production environments where actual payment collection is required. All payments are automatically approved without any validation.

## Support and Contribution

This is a custom module. For issues or feature requests, please contact your development team or module maintainer.

## License

This module follows the same license as Drupal core (GPL v2 or later).

## Credits

Developed for Drupal Commerce 3.x and Drupal 10/11.

## Author

Pavel Kasianov.

Linkedin: https://www.linkedin.com/in/pkasianov/</br>
Drupal org: https://www.drupal.org/u/pkasianov
