<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function ssentezo_MetaData() {
    return [
        'DisplayName' => 'Ssentezo Wallet Payment Gateway',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    ];
}

function ssentezo_config() {
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Ssentezo Wallet Payment Gateway',
        ],
        'apiUsername' => [
            'FriendlyName' => 'API Username',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter your Ssentezo Wallet API Username.',
        ],
        'apiKey' => [
            'FriendlyName' => 'API Key',
            'Type' => 'password',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter your Ssentezo Wallet API Key.',
        ],
        'callbackUrl' => [
            'FriendlyName' => 'Callback URL',
            'Type' => 'text',
            'Size' => '100',
            'Default' => '',
            'Description' => 'Enter your callback URL to receive payment notifications.',
        ],
    ];
}

/**
 * Generate a unique reference for each payment attempt
 * @param int $invoiceId The invoice ID
 * @return string Unique reference combining invoice ID and timestamp
 */
function ssentezo_generateUniqueReference($invoiceId) {
    // Combine invoice ID with current timestamp and a random string for uniqueness
    return $invoiceId . '-' . time() . '-' . substr(md5(mt_rand()), 0, 6);
}

function ssentezo_link($params) {
    $invoiceId = $params['invoiceid'];
    $amount = number_format((float)$params['amount'], 2, '.', '');
    $currency = strtoupper($params['currency']);
    $clientFullName = trim($params['clientdetails']['firstname'] . ' ' . $params['clientdetails']['lastname']);
    $apiUsername = $params['apiUsername'];
    $apiKey = $params['apiKey'];
    $callbackUrl = $params['callbackUrl'];

    // Processing payment request
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['phone_number'])) {
        $phoneNumber = trim($_POST['phone_number']);
        $paymentUrl = "https://wallet.ssentezo.com/api/deposit";
        
        // Generate a unique reference for this payment attempt
        $uniqueReference = ssentezo_generateUniqueReference($invoiceId);
        
        $data = [
            'msisdn' => $phoneNumber,
            'amount' => $amount,
            'currency' => $currency,
            'externalReference' => $uniqueReference, // Use the unique reference instead of just invoice ID
            'originalInvoiceId' => (string) $invoiceId, // Keep original invoice ID for your records
            'reason' => "Invoice Payment #$invoiceId by $clientFullName",
            'name' => $clientFullName,
            'success_callback' => $callbackUrl,
            'failure_callback' => $callbackUrl,
        ];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode("$apiUsername:$apiKey"),
        ];
        
        $ch = curl_init($paymentUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Log the payment attempt for debugging (optional)
        logTransaction("ssentezo", [
            'reference' => $uniqueReference,
            'invoice' => $invoiceId,
            'response' => $response,
            'status_code' => $httpCode
        ], "Payment Request");
        
        // Return a nicer message with styling
        $successMessage = '<div class="alert alert-info text-center">
            <i class="fas fa-info-circle fa-2x mb-3"></i>
            <h4>Payment Request Sent</h4>
            <p>Please check your mobile device and approve the payment request.</p>
            <p class="mb-0"><small>Reference: ' . $uniqueReference . '</small></p>
        </div>';
        
        return $successMessage;
    }
    
    // CSS styles for the form - this will be applied inline to avoid conflicts
    $styles = '
        .ssentezo-payment-container {
            max-width: 450px;
            margin: 0 auto;
            padding: 20px;
            border-radius: 8px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .ssentezo-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .ssentezo-logo {
            max-width: 180px;
            margin-bottom: 15px;
        }
        .ssentezo-title {
            color: #2c3e50;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .ssentezo-subtitle {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .ssentezo-payment-form {
            display: flex;
            flex-direction: column;
        }
        .ssentezo-form-group {
            margin-bottom: 15px;
        }
        .ssentezo-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        .ssentezo-input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .ssentezo-input:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        .ssentezo-submit {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
        }
        .ssentezo-submit:hover {
            background-color: #2980b9;
        }
        .ssentezo-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #95a5a6;
        }
        .ssentezo-amount {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #2c3e50;
            padding: 10px;
            background-color: #ecf0f1;
            border-radius: 4px;
        }
    ';
    
    // Build the form HTML with better styling
    $formHtml = '
    <div class="ssentezo-payment-container">
        <style>' . $styles . '</style>
        <div class="ssentezo-header">
            <div class="ssentezo-title">Ssentezo Wallet Payment</div>
            <div class="ssentezo-subtitle">Secure mobile payment gateway</div>
        </div>
        
        <div class="ssentezo-amount">
            Amount: ' . $currency . ' ' . $amount . '
        </div>
        
        <form method="POST" class="ssentezo-payment-form">
            <div class="ssentezo-form-group">
                <label class="ssentezo-label" for="phone_number">Mobile Number:</label>
                <input type="text" id="phone_number" name="phone_number" class="ssentezo-input" required placeholder="e.g., +256XXXXXXXXX">
                <small style="color: #7f8c8d; margin-top: 5px; display: block;">Enter the mobile number linked to your Ssentezo Wallet account.</small>
            </div>
            
            <button type="submit" class="ssentezo-submit">
                Pay with Ssentezo Wallet
            </button>
        </form>
        
        <div class="ssentezo-footer">
            <p>Paying for Invoice #' . $invoiceId . '</p>
            <p>Secure payment powered by Ssentezo Wallet</p>
        </div>
    </div>';
    
    return $formHtml;
}
