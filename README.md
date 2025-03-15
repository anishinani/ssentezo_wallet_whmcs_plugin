```markdown
# Ssentezo Wallet Payment Gateway for WHMCS

This document provides step-by-step instructions for installing and configuring the Ssentezo Wallet payment gateway module for your WHMCS installation.

## Overview

The Ssentezo Wallet payment gateway allows your clients to pay invoices directly using their Ssentezo mobile wallet. This integration provides a seamless payment experience and supports automatic payment confirmation.

## Requirements

- WHMCS 7.0 or higher
- PHP 7.2 or higher
- Ssentezo Wallet API credentials (username and API key)
- SSL certificate installed on your domain (recommended for security)

## Installation

### Step 1: Download and Upload Files

1. Download the Ssentezo payment gateway module files
2. Extract the downloaded ZIP file
3. Upload the following files to your WHMCS installation:
   - Upload `ssentezo.php` to `/path/to/whmcs/modules/gateways/`
   - Upload `callback/ssentezo.php` to `/path/to/whmcs/modules/gateways/callback/`

### Step 2: Install the Payment Gateway

1. Log in to your WHMCS admin panel
2. Navigate to **Setup** > **Payment Gateways** 
3. Click on the **All Payment Gateways** tab
4. Find "Ssentezo Wallet Payment Gateway" in the list and click **Activate**

### Step 3: Configure the Gateway

1. After activation, click on the **Manage Existing Gateways** tab
2. Find "Ssentezo Wallet Payment Gateway" in the list of active payment gateways
3. Enter the following required information:
   - **API Username**: Your Ssentezo API username
   - **API Key**: Your Ssentezo API key
   - **Callback URL**: The URL to receive payment notifications (default should work in most cases)
4. Click **Save Changes**

## Testing the Gateway

Before going live, we recommend testing the gateway to ensure everything is working correctly:

1. Create a test invoice in WHMCS
2. View the invoice and select "Pay with Ssentezo Wallet" as the payment method
3. Enter a test mobile number
4. Confirm that the payment request is sent to Ssentezo
5. Verify that the callback properly marks the invoice as paid

## Troubleshooting

### Payment Requests Not Sending

- Verify your API credentials are correct
- Check your server's outgoing connections (may require allowing outbound connections to wallet.ssentezo.com)
- Enable WHMCS gateway logs for more detailed error information

### Callbacks Not Working

- Ensure your callback URL is publicly accessible
- Check that your server allows incoming connections to the callback script
- Verify that the callback URL is correctly configured in your Ssentezo account

### Invoice Not Marked as Paid

- Check WHMCS transaction logs for errors
- Verify that the callback script is receiving the correct parameters
- Ensure the script has permission to update invoice status

## Support

For additional support with this module, please contact:
- Email: support@example.com
- Phone: +1-234-567-8910

## Version History

- **1.0.0** - Initial release
- **1.1.0** - Added unique reference generation for payment retries
- **1.2.0** - Improved user interface and error handling

## License

This payment gateway module is released under the MIT License.

```

I've converted the README to a properly formatted markdown file. This format will render nicely on platforms like GitHub, GitLab, or any system that supports markdown. The file includes all the same information but is now saved with the `.md` extension and uses proper markdown syntax for headings, lists, and formatting.

You can save this file as `README.md` in the root directory of your plugin to provide installation and configuration instructions to users.
