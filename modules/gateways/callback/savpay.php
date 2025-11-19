<?php

define("DEFINE_MY_ACCESS", true);
define("DEFINE_DHRU_FILE", true);

if (!isset($_SESSION)) { $_SESSION = []; }

require_once __DIR__ . '/../../../comm.php';
require_once __DIR__ . '/../../../includes/fun.inc.php';
require_once __DIR__ . '/../../../includes/gateway.fun.php';
require_once __DIR__ . '/../../../includes/invoice.fun.php';

class SavPayGateway
{
    public $isActive;
    public $systemUrl;
    public $request;

    private static $instance;
    private $gatewayModuleName;
    private $gatewayParams;

    private $client;
    private $invoice;
    private $amount; // invoice total (converted if needed)

    private $apiKey;

    private function __construct()
    {
        $this->setRequest();
        $this->setGateway();
        $this->setInvoice();
    }

    public static function init()
    {
        if (self::$instance === null) {
            self::$instance = new SavPayGateway();
        }
        return self::$instance;
    }

    private function setRequest()
    {
        $this->request = $_REQUEST;
    }

    private function setGateway()
    {
        $this->gatewayModuleName = basename(__FILE__, '.php'); // savpay
        $this->gatewayParams     = loadGatewayModule($this->gatewayModuleName);
        $this->systemUrl         = $this->gatewayParams['systemurl'];
        $this->isActive          = !empty($this->gatewayParams['active']);

        $this->apiKey = isset($this->gatewayParams['api_key']) ? trim($this->gatewayParams['api_key']) : '';

        if (empty($this->apiKey)) {
            throw new \Exception("SavPay API Key is not configured. Please contact support.");
        }
    }

    private function setInvoice()
    {
        if (empty($this->request['id'])) {
            throw new \Exception("Invalid invoice ID.");
        }

        $invoiceId = (int) $this->request['id'];

        $result        = select_query("tbl_invoices", "", ["id" => $invoiceId]);
        $this->invoice = mysqli_fetch_assoc($result);

        if (!$this->invoice) {
            throw new \Exception("Invoice not found.");
        }

        $this->setClient();
        $this->setAmount();
    }

    private function setClient()
    {
        $result       = select_query("tblUsers", "", ["id" => $this->invoice['userid']]);
        $this->client = mysqli_fetch_assoc($result);
    }

    private function setAmount()
    {
        // Basic: use invoice total directly. If you want currency conversion later, you can extend here.
        $this->amount = (float) $this->invoice['total'];
    }

    private function logTransaction($payload, $message = 'Transaction Successful')
    {
        return logTransaction(
            $this->gatewayModuleName,
            json_encode($payload),
            $message,
            "invoice",
            $this->invoice['id']
        );
    }

    private function addTransaction($trxId, $paidAmount = null)
    {
        // Uses invoice total as the amount recorded
        $amount = $paidAmount !== null ? (float)$paidAmount : (float)$this->amount;

        return addPayment(
            $this->invoice['id'],
            $trxId,
            $amount,
            0,
            $this->gatewayModuleName
        );
    }

    /**
     * Create payment on SavPay and redirect user to payment page
     */
    public function createPayment()
    {
        $invoiceId = (int) $this->invoice['id'];

        $systemUrl = rtrim($this->systemUrl, '/');

        // User return endpoints
        $successUrl = $systemUrl . '/modules/gateways/callback/' . $this->gatewayModuleName . '.php?action=return&id=' . $invoiceId;
        $cancelUrl  = $systemUrl . '/modules/gateways/callback/' . $this->gatewayModuleName . '.php?action=cancel&id=' . $invoiceId;

        $cusName  = trim($this->client['first_name'] . ' ' . $this->client['last_name']);
        $cusEmail = isset($this->client['email']) ? $this->client['email'] : '';

        $payload = [
            "cus_name"    => $cusName,
            "cus_email"   => $cusEmail,
            "amount"      => $this->amount,
            "success_url" => $successUrl,
            "cancel_url"  => $cancelUrl,
            "metadata"    => [
                "invoice_id" => $invoiceId,
                "client_id"  => $this->invoice['userid'],
            ],
        ];

        $headers = [
            'Content-Type: application/json',
            'API-KEY: ' . $this->apiKey,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://pay.sav.com.bd/api/payment/create',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new \Exception("Could not connect to SavPay. Please try again later.");
        }

        $json = json_decode($response, true);

        if (!$json || !isset($json['status'])) {
            throw new \Exception("Invalid response from SavPay. Please contact support.");
        }

        if (!$json['status'] || empty($json['payment_url'])) {
            $msg = isset($json['message']) ? $json['message'] : "Unable to create payment session.";
            throw new \Exception($msg);
        }

        // Log creation
        $this->logTransaction($json, "SavPay payment created");

        // Redirect to SavPay payment URL
        header("Location: " . $json['payment_url']);
        exit;
    }

    /**
     * Verify payment status with SavPay using transaction ID
     */
    public function verifyPayment($transactionId, $paymentAmount = null)
    {
        if (empty($transactionId)) {
            throw new \Exception("Invalid transaction ID.");
        }

        if (checkTransID($transactionId)) {
            throw new \Exception("This transaction ID has already been used.");
        }

        $payload = [
            "transaction_id" => $transactionId,
        ];

        $headers = [
            'Content-Type: application/json',
            'API-KEY: ' . $this->apiKey,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://pay.sav.com.bd/api/payment/verify',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new \Exception("Could not connect to SavPay verify API.");
        }

        $json = json_decode($response, true);

        if (!$json || !isset($json['status'])) {
            throw new \Exception("Invalid response from SavPay verify API.");
        }

        // SavPay may return status true or "COMPLETED"
        $status = $json['status'];

        $isCompleted = false;
        if ($status === true) {
            $isCompleted = true;
        } elseif (is_string($status) && strtoupper($status) === 'COMPLETED') {
            $isCompleted = true;
        }

        if (!$isCompleted) {
            $msg = isset($json['message']) ? $json['message'] : "Payment is not completed.";
            throw new \Exception($msg);
        }

        // All good, log and add payment
        $this->logTransaction($json, "SavPay payment verified");
        $this->addTransaction($transactionId, $paymentAmount);

        return [
            'status'  => 'success',
            'message' => 'Payment verified successfully.',
        ];
    }

    /**
     * Redirect helper to DHRU invoice page
     */
    public function redirectToInvoice()
    {
        $invoiceId = (int) $this->invoice['id'];
        $url       = rtrim($this->systemUrl, '/') . '/' . _url("viewinvoice/id/" . md5($invoiceId));
        header("Location: " . $url);
        exit;
    }
}

// ------- Controller / Router -------

try {
    $savpay = SavPayGateway::init();

    if (!$savpay->isActive) {
        die("The SavPay gateway is unavailable.");
    }

    $action    = isset($savpay->request['action']) ? $savpay->request['action'] : 'init';
    $invoiceId = isset($savpay->request['id']) ? (int) $savpay->request['id'] : 0;

    if ($action === 'init') {
        // User clicked Pay Now -> Create payment session & redirect to SavPay
        $savpay->createPayment();
        exit;
    }

    if ($action === 'return') {
        // Return from SavPay
        $txid          = isset($savpay->request['transactionId']) ? trim($savpay->request['transactionId']) : '';
        $status        = isset($savpay->request['status']) ? strtolower(trim($savpay->request['status'])) : '';
        $paymentAmount = isset($savpay->request['paymentAmount']) ? (float)$savpay->request['paymentAmount'] : null;

        if (empty($txid)) {
            $_SESSION['savpay_error'] = "Transaction ID not provided by SavPay.";
            $savpay->redirectToInvoice();
        }

        if ($status !== 'completed') {
            $_SESSION['savpay_error'] = "Payment not completed. Status: " . htmlspecialchars($status);
            $savpay->redirectToInvoice();
        }

        try {
            $resp = $savpay->verifyPayment($txid, $paymentAmount);

            if ($resp['status'] === 'success') {
                $savpay->redirectToInvoice();
            } else {
                $_SESSION['savpay_error'] = $resp['message'];
                $savpay->redirectToInvoice();
            }
        } catch (\Exception $e) {
            $_SESSION['savpay_error'] = $e->getMessage();
            $savpay->redirectToInvoice();
        }
    }

    if ($action === 'cancel') {
        $_SESSION['savpay_error'] = "Payment was cancelled on SavPay.";
        $savpay->redirectToInvoice();
    }

    // Fallback
    header("Location: " . rtrim($savpay->systemUrl, '/') . '/' . _url("viewinvoice/id/" . md5($invoiceId)) . "?errorCode=sww");
    exit;

} catch (\Exception $e) {

    $_SESSION['savpay_error'] = $e->getMessage();
    $id  = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
    sleep(1);
    header("Location: " . rtrim($GLOBALS['CONFIG']['SystemURL'], '/') . '/' . _url("viewinvoice/id/" . md5($id)));
    exit;
}

