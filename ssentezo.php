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
        
        // Return a loading page that polls for payment status
        $systemUrl = $params['systemurl'];
        
        $loadingPage = '
        <div id="ssentezo-payment-loader">
            <style>
                #ssentezo-payment-loader {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    z-index: 999999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .ssentezo-loader-container {
                    max-width: 500px;
                    width: 100%;
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    padding: 40px;
                    text-align: center;
                    animation: slideUp 0.5s ease-out;
                }
                @keyframes slideUp {
                    from {
                        opacity: 0;
                        transform: translateY(30px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                .ssentezo-spinner {
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #3498db;
                    border-radius: 50%;
                    width: 60px;
                    height: 60px;
                    animation: spin 1s linear infinite;
                    margin: 0 auto 20px;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                .ssentezo-status {
                    font-size: 18px;
                    color: #2c3e50;
                    margin-bottom: 10px;
                    font-weight: 500;
                }
                .ssentezo-status-text {
                    color: #7f8c8d;
                    font-size: 14px;
                    margin-bottom: 20px;
                }
                .ssentezo-reference {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 8px;
                    font-family: monospace;
                    font-size: 12px;
                    color: #495057;
                    margin-top: 20px;
                }
                .ssentezo-success {
                    display: none;
                }
                .ssentezo-success .ssentezo-checkmark {
                    width: 80px;
                    height: 80px;
                    border-radius: 50%;
                    background: #28a745;
                    color: white;
                    font-size: 48px;
                    line-height: 80px;
                    margin: 0 auto 20px;
                    animation: scaleIn 0.5s ease-out;
                }
                @keyframes scaleIn {
                    from { transform: scale(0); }
                    to { transform: scale(1); }
                }
                .ssentezo-success-title {
                    font-size: 24px;
                    color: #28a745;
                    font-weight: bold;
                    margin-bottom: 10px;
                }
                .ssentezo-button {
                    background: #28a745;
                    color: white;
                    padding: 12px 24px;
                    border-radius: 6px;
                    text-decoration: none;
                    display: inline-block;
                    margin-top: 20px;
                    font-weight: 500;
                }
                .ssentezo-button:hover {
                    background: #218838;
                }
            </style>
            
            <div class="ssentezo-loader-container">
                <div id="ssentezo-loading">
                    <div class="ssentezo-spinner"></div>
                    <div class="ssentezo-status">Waiting for payment approval...</div>
                    <div class="ssentezo-status-text">Please approve the payment request on your mobile device</div>
                    <div class="ssentezo-reference">Reference: ' . htmlspecialchars($uniqueReference) . '</div>
                </div>
                
                <div id="ssentezo-success" class="ssentezo-success">
                    <div class="ssentezo-checkmark">✓</div>
                    <div class="ssentezo-success-title">Payment Successful!</div>
                    <p style="color: #7f8c8d; margin-bottom: 20px;">Your payment has been processed successfully.</p>
                    <a href="viewinvoice.php?id=' . $invoiceId . '" class="ssentezo-button">View Invoice</a>
                </div>
            </div>
        </div>
        
        <script>
        var checkAttempts = 0;
        var maxAttempts = 60; // Check for 60 seconds (60 attempts x 1 second)
        var invoiceId = ' . $invoiceId . ';
        
        function checkPaymentStatus() {
            checkAttempts++;
            
            if (checkAttempts > maxAttempts) {
                document.getElementById("ssentezo-loading").innerHTML = 
                    \'<div style="color: #dc3545; font-size: 18px; margin-bottom: 10px;">Timeout</div>\' +
                    \'<p style="color: #7f8c8d;">Payment approval is taking longer than expected. Please refresh this page to check the invoice status.</p>\' +
                    \'<a href="viewinvoice.php?id=' . $invoiceId . '" style="background: #007bff; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block; margin-top: 15px;">Check Invoice Status</a>\';
                return;
            }
            
            // Use relative URL to check payment status
            fetch("' . $systemUrl . 'modules/gateways/callback/check_payment.php?invoiceid=" + invoiceId + "&r=" + Date.now())
                .then(response => response.json())
                .then(data => {
                    if (data.status === "paid") {
                        // Payment successful!
                        document.getElementById("ssentezo-loading").style.display = "none";
                        document.getElementById("ssentezo-success").style.display = "block";
                    } else {
                        // Still waiting, check again in 1 second
                        setTimeout(checkPaymentStatus, 1000);
                    }
                })
                .catch(error => {
                    console.error("Error checking payment status:", error);
                    // Continue checking despite errors
                    setTimeout(checkPaymentStatus, 1000);
                });
        }
        
        // Start checking after 2 seconds
        setTimeout(checkPaymentStatus, 2000);
        </script>';
        
        return $loadingPage;
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
