<?php

if (basename(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '') === 'common.php') {
    http_response_code(403);
    exit;
}

use WHMCS\Database\Capsule;

/**
 * Boot WHMCS from a standalone gateway script and load payment helpers.
 *
 * WHMCS 8 and 9 no longer want callback scripts to include
 * includes/gatewayfunctions.php directly. App::load_function() is the
 * supported loader; the include files remain as a fallback for WHMCS 7.
 */
function ssentezo_bootstrap()
{
    if (!defined('WHMCS')) {
        $init = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'init.php';
        if (!is_file($init)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'WHMCS could not be loaded.';
            exit;
        }
        require_once $init;
    }

    if (!function_exists('getGatewayVariables') || !function_exists('checkCbInvoiceID') || !function_exists('logTransaction')) {
        if (class_exists('App') && method_exists('App', 'load_function')) {
            App::load_function('gateway');
        }
    }
    if (!function_exists('getGatewayVariables') && defined('ROOTDIR') && is_file(ROOTDIR . '/includes/gatewayfunctions.php')) {
        require_once ROOTDIR . '/includes/gatewayfunctions.php';
    }

    if (!function_exists('addInvoicePayment') || !function_exists('checkCbTransID')) {
        if (class_exists('App') && method_exists('App', 'load_function')) {
            App::load_function('invoice');
        }
    }
    if (!function_exists('addInvoicePayment') && defined('ROOTDIR') && is_file(ROOTDIR . '/includes/invoicefunctions.php')) {
        require_once ROOTDIR . '/includes/invoicefunctions.php';
    }
}

function ssentezo_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function ssentezo_systemUrl(array $params)
{
    $url = isset($params['systemurl']) ? (string) $params['systemurl'] : '';
    return rtrim($url, '/') . '/';
}

function ssentezo_isTestMode(array $params)
{
    if (!isset($params['testMode'])) {
        return false;
    }
    $value = $params['testMode'];
    return $value === 'on' || $value === '1' || $value === 1 || $value === true;
}

function ssentezo_apiBaseUrl(array $params)
{
    if (ssentezo_isTestMode($params)) {
        return 'https://devwallet.ssentezo.com/api';
    }
    return 'https://wallet.ssentezo.com/api';
}

function ssentezo_callbackUrl(array $params)
{
    $configured = isset($params['callbackUrl']) ? trim((string) $params['callbackUrl']) : '';
    if ($configured !== '') {
        return $configured;
    }
    return ssentezo_systemUrl($params) . 'modules/gateways/callback/ssentezo.php';
}

function ssentezo_generateReference($invoiceId)
{
    return (int) $invoiceId . '-' . time() . '-' . substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
}

function ssentezo_invoiceIdFromReference($reference)
{
    $parts = explode('-', (string) $reference, 2);
    if (!isset($parts[0]) || !ctype_digit($parts[0])) {
        return 0;
    }
    return (int) $parts[0];
}

function ssentezo_paymentSignature($apiKey, $invoiceId, $amount, $currency)
{
    $payload = (int) $invoiceId . '|' . number_format((float) $amount, 2, '.', '') . '|' . strtoupper((string) $currency);
    return hash_hmac('sha256', $payload, (string) $apiKey);
}

function ssentezo_pollToken($apiKey, $invoiceId, $reference = '')
{
    return hash_hmac('sha256', 'poll|' . (int) $invoiceId . '|' . (string) $reference, (string) $apiKey);
}

function ssentezo_cardAllowed($currency)
{
    $currency = strtoupper(trim((string) $currency));
    return in_array($currency, array('UGX', 'USH', 'UGS', 'SHS'), true);
}

function ssentezo_csrfField()
{
    if (function_exists('generate_token')) {
        return generate_token('form');
    }
    return '';
}

function ssentezo_isSuccessStatus($status)
{
    $status = strtoupper(trim((string) $status));
    return in_array($status, array('SUCCEEDED', 'SUCCESS', 'SUCCESSFUL', 'COMPLETED', 'PAID'), true);
}

function ssentezo_isCheckoutUrl($url)
{
    if (!is_string($url) || stripos($url, 'https://') !== 0) {
        return false;
    }
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return false;
    }
    $host = strtolower($host);
    return $host === 'wallet.ssentezo.com'
        || $host === 'devwallet.ssentezo.com'
        || substr($host, -13) === '.ssentezo.com';
}

function ssentezo_apiErrorMessage(array $json)
{
    if (isset($json['error']['message']) && is_string($json['error']['message']) && $json['error']['message'] !== '') {
        return $json['error']['message'];
    }
    if (isset($json['message']) && is_string($json['message']) && $json['message'] !== '') {
        return $json['message'];
    }
    return 'The payment request was not accepted. Please try again.';
}

function ssentezo_apiPost(array $params, $path, array $body, $timeout = 20)
{
    $url = rtrim(ssentezo_apiBaseUrl($params), '/') . '/' . ltrim((string) $path, '/');
    $username = isset($params['apiUsername']) ? (string) $params['apiUsername'] : '';
    $apiKey = isset($params['apiKey']) ? (string) $params['apiKey'] : '';

    $ch = curl_init($url);
    if ($ch === false) {
        return array(
            'ok' => false,
            'httpCode' => 0,
            'error' => 'Could not start a request to Ssentezo.',
            'body' => '',
            'json' => array(),
        );
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $encodedBody = json_encode($body);
    if ($body === array() || $encodedBody === false) {
        $encodedBody = '{}';
    }

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedBody);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($username . ':' . $apiKey),
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, (int) $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, (int) $timeout));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = array();
    if (is_string($response) && $response !== '') {
        $parsed = json_decode($response, true);
        if (is_array($parsed)) {
            $decoded = $parsed;
        }
    }

    return array(
        'ok' => $curlError === '' && $httpCode >= 200 && $httpCode < 300,
        'httpCode' => $httpCode,
        'error' => $curlError,
        'body' => is_string($response) ? $response : '',
        'json' => $decoded,
    );
}

function ssentezo_log($gatewayName, $data, $status)
{
    if (!function_exists('logTransaction')) {
        return;
    }
    try {
        logTransaction($gatewayName, $data, $status);
    } catch (Throwable $e) {
        // A logging failure must not stop a payment notification.
    }
}

function ssentezo_callbackPayload()
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw)) {
        $raw = '';
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = is_array($_POST) ? $_POST : array();
    }

    if (isset($payload['data']) && is_string($payload['data'])) {
        $nested = json_decode($payload['data'], true);
        if (is_array($nested)) {
            return $nested;
        }
    }
    if (isset($payload['data']) && is_array($payload['data'])) {
        return $payload['data'];
    }

    return $payload;
}

function ssentezo_statusFrom(array $data)
{
    $status = ssentezo_field($data, 'transactionStatus');
    if ($status === '') {
        $status = ssentezo_field($data, 'status');
    }
    return $status;
}

function ssentezo_transactionExists($transactionId)
{
    $transactionId = (string) $transactionId;
    if ($transactionId === '') {
        return false;
    }
    return Capsule::table('tblaccounts')->where('transid', $transactionId)->count() > 0;
}

function ssentezo_field(array $data, $key)
{
    if (!isset($data[$key]) || is_array($data[$key])) {
        return '';
    }
    return trim((string) $data[$key]);
}

function ssentezo_invoiceSnapshot($invoiceId)
{
    $invoiceId = (int) $invoiceId;
    if ($invoiceId <= 0 || !class_exists('WHMCS\\Billing\\Invoice')) {
        return null;
    }

    $invoice = WHMCS\Billing\Invoice::find($invoiceId);
    if (!$invoice) {
        return null;
    }

    $client = Capsule::table('tblclients')->where('id', (int) $invoice->userid)->first();
    $currencyCode = '';
    if ($client && isset($client->currency)) {
        $currency = Capsule::table('tblcurrencies')->where('id', $client->currency)->first();
        if ($currency && isset($currency->code)) {
            $currencyCode = strtoupper((string) $currency->code);
        }
    }

    $firstName = $client && isset($client->firstname) ? (string) $client->firstname : '';
    $lastName = $client && isset($client->lastname) ? (string) $client->lastname : '';

    $balance = null;
    try {
        $balance = $invoice->balance;
    } catch (Throwable $e) {
        $balance = null;
    }
    if (!is_numeric($balance)) {
        $sums = Capsule::table('tblaccounts')
            ->where('invoiceid', (int) $invoice->id)
            ->selectRaw('COALESCE(SUM(amountin),0) as paid_in, COALESCE(SUM(amountout),0) as paid_out')
            ->first();
        $netPaid = $sums ? ((float) $sums->paid_in - (float) $sums->paid_out) : 0.0;
        $balance = (float) $invoice->total - $netPaid;
    }
    if ((float) $balance < 0) {
        $balance = 0;
    }

    return array(
        'id' => (int) $invoice->id,
        'userid' => (int) $invoice->userid,
        'status' => (string) $invoice->status,
        'balance' => round((float) $balance, 2),
        'currency' => $currencyCode,
        'name' => trim($firstName . ' ' . $lastName),
        'email' => $client && isset($client->email) ? (string) $client->email : '',
    );
}

function ssentezo_applyCollection(array $params, array $invoice, array $verifiedData, $externalReference)
{
    $gatewayName = isset($params['name']) && $params['name'] !== '' ? (string) $params['name'] : 'Ssentezo Wallet';
    $transactionId = ssentezo_field($verifiedData, 'financialTransactionId');
    if ($transactionId === '') {
        $transactionId = ssentezo_field($verifiedData, 'ssentezoWalletReference');
    }
    if ($transactionId === '') {
        $transactionId = (string) $externalReference;
    }

    if (ssentezo_transactionExists($transactionId)) {
        return array('status' => 'paid');
    }
    if (!function_exists('addInvoicePayment')) {
        return array('status' => 'pending');
    }

    $applyAmount = $invoice['balance'];
    $verifiedCurrency = strtoupper(ssentezo_field($verifiedData, 'currency'));
    if (isset($verifiedData['amount']) && is_numeric($verifiedData['amount'])) {
        $verifiedAmount = round((float) $verifiedData['amount'], 2);
        $sameCurrency = $verifiedCurrency === '' || $invoice['currency'] === '' || $verifiedCurrency === $invoice['currency'];
        if ($sameCurrency && $verifiedAmount > 0 && ($verifiedAmount + 0.01) < $invoice['balance']) {
            $applyAmount = $verifiedAmount;
        }
    }

    try {
        addInvoicePayment(
            $invoice['id'],
            $transactionId,
            $applyAmount,
            0,
            'ssentezo'
        );
    } catch (Throwable $e) {
        ssentezo_log($gatewayName, array(
            'invoice' => $invoice['id'],
            'transaction' => $transactionId,
            'error' => $e->getMessage(),
        ), 'Payment Apply Failed');
        return array('status' => 'pending');
    }

    ssentezo_log($gatewayName, array(
        'invoice' => $invoice['id'],
        'reference' => $externalReference,
        'transaction' => $transactionId,
        'amount' => $applyAmount,
    ), 'Success');

    return array('status' => 'paid');
}

function ssentezo_settleFromReference(array $params, $invoiceId, $externalReference)
{
    $invoiceId = (int) $invoiceId;
    $externalReference = trim((string) $externalReference);
    if ($invoiceId <= 0 || ssentezo_invoiceIdFromReference($externalReference) !== $invoiceId) {
        return array('status' => 'error');
    }

    $invoice = ssentezo_invoiceSnapshot($invoiceId);
    if (!$invoice) {
        return array('status' => 'error');
    }
    if (strtolower($invoice['status']) === 'paid' || $invoice['balance'] <= 0) {
        return array('status' => 'paid');
    }

    $verified = ssentezo_apiPost($params, 'get_status/' . rawurlencode($externalReference), array(), 8);
    $verifiedData = array();
    if (isset($verified['json']['data']) && is_array($verified['json']['data'])) {
        $verifiedData = $verified['json']['data'];
    }
    if (!$verified['ok']) {
        return array('status' => 'pending');
    }

    $status = strtoupper(ssentezo_statusFrom($verifiedData));
    if (ssentezo_isSuccessStatus($status)) {
        return ssentezo_applyCollection($params, $invoice, $verifiedData, $externalReference);
    }
    if (in_array($status, array('FAILED', 'FAILURE', 'CANCELLED', 'CANCELED', 'REJECTED'), true)) {
        return array('status' => 'failed');
    }

    return array('status' => 'pending');
}

function ssentezo_invoiceUrl(array $params, $invoiceId)
{
    return ssentezo_systemUrl($params) . 'viewinvoice.php?id=' . (int) $invoiceId;
}

function ssentezo_pollUrl(array $params, $invoiceId, $reference)
{
    $apiKey = isset($params['apiKey']) ? $params['apiKey'] : '';
    $token = ssentezo_pollToken($apiKey, $invoiceId, $reference);
    return ssentezo_systemUrl($params)
        . 'modules/gateways/callback/check_payment.php?invoiceid=' . (int) $invoiceId
        . '&reference=' . rawurlencode((string) $reference)
        . '&token=' . rawurlencode($token);
}

function ssentezo_returnUrl(array $params, $invoiceId, $reference)
{
    $apiKey = isset($params['apiKey']) ? $params['apiKey'] : '';
    $token = ssentezo_pollToken($apiKey, $invoiceId, $reference);
    return ssentezo_systemUrl($params)
        . 'modules/gateways/ssentezo/return.php?invoiceid=' . (int) $invoiceId
        . '&reference=' . rawurlencode((string) $reference)
        . '&token=' . rawurlencode($token);
}

function ssentezo_page($title, $bodyHtml)
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . ssentezo_h($title) . '</title>';
    echo '<style>
        body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
        .card{max-width:480px;width:100%;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.25);padding:36px;text-align:center;}
        h1{font-size:22px;margin:0 0 10px;color:#2c3e50;}
        p{color:#5f6b76;font-size:14px;line-height:1.5;}
        a.btn{display:inline-block;margin-top:16px;background:#3498db;color:#fff;text-decoration:none;padding:12px 20px;border-radius:6px;font-weight:600;}
        .spinner{width:56px;height:56px;border:4px solid #f3f3f3;border-top-color:#3498db;border-radius:50%;margin:0 auto 18px;animation:spin 1s linear infinite;}
        @keyframes spin{to{transform:rotate(360deg);}}
        .ref{margin-top:18px;background:#f8f9fa;border-radius:8px;padding:12px;font-family:monospace;font-size:12px;color:#495057;word-break:break-all;}
    </style></head><body><div class="card">' . $bodyHtml . '</div></body></html>';
    exit;
}

function ssentezo_waitingPage(array $params, $invoiceId, $reference, $message)
{
    $pollUrl = ssentezo_pollUrl($params, $invoiceId, $reference);
    $invoiceUrl = ssentezo_invoiceUrl($params, $invoiceId);
    $body = '<div class="spinner" id="ssentezo-spinner"></div>'
        . '<h1 id="ssentezo-title">Waiting for payment</h1>'
        . '<p id="ssentezo-text">' . ssentezo_h($message) . '</p>'
        . ($reference !== '' ? '<div class="ref">Reference: ' . ssentezo_h($reference) . '</div>' : '')
        . '<p id="ssentezo-note" style="font-size:13px;">If the money has already left the phone or card, do not pay again. This page is confirming it with Ssentezo.</p>'
        . '<a class="btn" id="ssentezo-again" href="#" style="display:none;">Check again</a>'
        . '<a class="btn" href="' . ssentezo_h($invoiceUrl) . '" style="background:#6c757d;">Back to invoice</a>'
        . '<script>
        (function () {
            var attempts = 0;
            var pollUrl = ' . json_encode($pollUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';
            var invoiceUrl = ' . json_encode($invoiceUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';
            var again = document.getElementById("ssentezo-again");
            function showSlow() {
                document.getElementById("ssentezo-spinner").style.display = "none";
                document.getElementById("ssentezo-title").textContent = "Still confirming";
                document.getElementById("ssentezo-text").textContent = "Ssentezo has not confirmed this payment yet. If money was deducted, wait on this page or check again. Do not start a second payment.";
                again.style.display = "inline-block";
            }
            function tick() {
                attempts++;
                if (attempts > 40) {
                    showSlow();
                    return;
                }
                fetch(pollUrl, {credentials: "same-origin", cache: "no-store"})
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data && data.status === "paid") {
                            window.location.href = invoiceUrl;
                            return;
                        }
                        if (data && data.status === "failed") {
                            document.getElementById("ssentezo-spinner").style.display = "none";
                            document.getElementById("ssentezo-title").textContent = "Payment was not completed";
                            document.getElementById("ssentezo-text").textContent = "Ssentezo reported that this payment failed. You can return to the invoice and try again.";
                            return;
                        }
                        setTimeout(tick, 3000);
                    })
                    .catch(function () { setTimeout(tick, 3000); });
            }
            again.addEventListener("click", function (event) {
                event.preventDefault();
                attempts = 0;
                again.style.display = "none";
                document.getElementById("ssentezo-spinner").style.display = "block";
                document.getElementById("ssentezo-title").textContent = "Waiting for payment";
                document.getElementById("ssentezo-text").textContent = "Checking Ssentezo again.";
                tick();
            });
            setTimeout(tick, 3000);
        }());
        </script>';

    ssentezo_page('Ssentezo payment', $body);
}
