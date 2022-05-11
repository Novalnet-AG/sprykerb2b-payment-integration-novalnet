<?php

namespace Novalnet\Zed\NovalnetPayment\Business\Payment;

use Generated\Shared\Transfer\CheckoutErrorTransfer;
use Generated\Shared\Transfer\CheckoutResponseTransfer;
use Generated\Shared\Transfer\NovalnetRedirectResponseTransfer;
use Generated\Shared\Transfer\NovalnetRefundTransfer;
use Generated\Shared\Transfer\NovalnetStandardParameterTransfer;
use Generated\Shared\Transfer\NovalnetTransfer;
use Generated\Shared\Transfer\OrderTransfer;
use Generated\Shared\Transfer\QuoteTransfer;
use Generated\Shared\Transfer\SaveOrderTransfer;
use Novalnet\Shared\NovalnetPayment\NovalnetConfig;
use Novalnet\Zed\NovalnetPayment\Business\Api\Adapter\AdapterInterface;
use Novalnet\Zed\NovalnetPayment\Dependency\Facade\NovalnetPaymentToGlossaryInterface;
use Novalnet\Zed\NovalnetPayment\Persistence\NovalnetPaymentQueryContainerInterface;
use Orm\Zed\Novalnet\Persistence\SpyPaymentNovalnetDetail;
use Orm\Zed\Novalnet\Persistence\SpyPaymentNovalnetTransactionLog;
use Spryker\Shared\Kernel\Store;

class PaymentManager implements PaymentManagerInterface
{
    /**
     * @var \Novalnet\Zed\NovalnetPayment\Business\Api\Adapter\AdapterInterface
     */
    protected $executionAdapter;

    /**
     * @var \Novalnet\Zed\NovalnetPayment\Persistence\NovalnetPaymentQueryContainerInterface
     */
    protected $queryContainer;

    /**
     * @var \Generated\Shared\Transfer\NovalnetStandardParameterTransfer
     */
    protected $standardParameter;

    /**
     * @var \Novalnet\Zed\NovalnetPayment\Dependency\Facade\NovalnetPaymentToGlossaryInterface
     */
    protected $glossaryFacade;

    /**
     * @var array
     */
    protected $successStatus = ['CONFIRMED', 'PENDING', 'ON_HOLD'];

    /**
     * @var array
     */
    protected $pendingStatus = ['PENDING', 'ON_HOLD'];

    /**
     * @var string
     */
    protected $transactionType = 'payment';

    /**
     * @param \Novalnet\Zed\NovalnetPayment\Business\Api\Adapter\AdapterInterface $executionAdapter
     * @param \Novalnet\Zed\NovalnetPayment\Persistence\NovalnetPaymentQueryContainerInterface $queryContainer
     * @param \Generated\Shared\Transfer\NovalnetStandardParameterTransfer $standardParameter
     * @param \Novalnet\Zed\NovalnetPayment\Dependency\Facade\NovalnetPaymentToGlossaryInterface $glossaryFacade
     */
    public function __construct(
        AdapterInterface $executionAdapter,
        NovalnetPaymentQueryContainerInterface $queryContainer,
        NovalnetStandardParameterTransfer $standardParameter,
        NovalnetPaymentToGlossaryInterface $glossaryFacade
    ) {
        $this->executionAdapter = $executionAdapter;
        $this->queryContainer = $queryContainer;
        $this->standardParameter = $standardParameter;
        $this->glossaryFacade = $glossaryFacade;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return array
     */
    public function authorize(OrderTransfer $orderTransfer)
    {
        $requestData = $this->buildRequestData($orderTransfer);
        $response = $this->performAuthorizationRequest($orderTransfer, $requestData);

        return $response;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return array
     */
    protected function buildRequestData(OrderTransfer $orderTransfer)
    {
        $requestData = [];

        // Build Merchant Data
        $this->setMerchantData($requestData);

        // Build Customer Data
        $this->setCustomerData($orderTransfer, $requestData);

        // Build Custom Data
        $this->setCustomData($requestData);

        // Build Transaction Data
        $this->setTransactionData($orderTransfer, $requestData);

        // Filter request Data
        $requestData = $this->filterStandardParameter($requestData);

        return $requestData;
    }

    /**
     * @param array $requestData
     *
     * @return void
     */
    protected function setMerchantData(&$requestData)
    {
        $requestData['merchant'] = [
            'signature' => $this->standardParameter->getSignature(),
            'tariff' => $this->standardParameter->getTariffId(),
        ];
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     * @param array $requestData
     *
     * @return void
     */
    protected function setCustomerData(OrderTransfer $orderTransfer, &$requestData)
    {
        $customerData = [
            'first_name' => $orderTransfer->getBillingAddress()->getFirstName(),
            'last_name' => $orderTransfer->getBillingAddress()->getLastName(),
            'email' => $orderTransfer->getCustomer()->getEmail(),
            'tel' => $orderTransfer->getBillingAddress()->getPhone(),
            'customer_no' => !empty($orderTransfer->getCustomer()->getIdCustomer())
                ? $orderTransfer->getCustomer()->getIdCustomer() : 'guest',
            'customer_ip' => $this->getIpAddress(),
            'billing' => [
                'street' => $orderTransfer->getBillingAddress()->getAddress1(),
                'house_no' => $orderTransfer->getBillingAddress()->getAddress2(),
                'city' => $orderTransfer->getBillingAddress()->getCity(),
                'zip' => $orderTransfer->getBillingAddress()->getZipCode(),
                'country_code' => $orderTransfer->getBillingAddress()->getIso2Code(),
                'company' => !empty($orderTransfer->getBillingAddress()->getCompany())
                ? $orderTransfer->getBillingAddress()->getCompany() : '',
            ],
        ];

        if (!empty($orderTransfer->getShippingAddress())) {
            if (
                $orderTransfer->getBillingAddress()->getFirstName() == $orderTransfer->getShippingAddress()->getFirstName()
                && $orderTransfer->getBillingAddress()->getLastName() == $orderTransfer->getShippingAddress()->getLastName()
                && $orderTransfer->getBillingAddress()->getAddress1() == $orderTransfer->getShippingAddress()->getAddress1()
                && $orderTransfer->getBillingAddress()->getAddress2() == $orderTransfer->getShippingAddress()->getAddress2()
                && $orderTransfer->getBillingAddress()->getCity() == $orderTransfer->getShippingAddress()->getCity()
                && $orderTransfer->getBillingAddress()->getZipCode() == $orderTransfer->getShippingAddress()->getZipCode()
                && $orderTransfer->getBillingAddress()->getIso2Code() == $orderTransfer->getShippingAddress()->getIso2Code()
                && $orderTransfer->getBillingAddress()->getCompany() == $orderTransfer->getShippingAddress()->getCompany()
                && $orderTransfer->getBillingAddress()->getPhone() == $orderTransfer->getShippingAddress()->getPhone()
            ) {
                $customerData['shipping'] = ['same_as_billing' => 1];
            } else {
                $customerData['shipping'] = [
                    'first_name' => $orderTransfer->getShippingAddress()->getFirstName(),
                    'last_name' => $orderTransfer->getShippingAddress()->getLastName(),
                    'email' => $orderTransfer->getCustomer()->getEmail(),
                    'street' => $orderTransfer->getShippingAddress()->getAddress1(),
                    'house_no' => $orderTransfer->getShippingAddress()->getAddress2(),
                    'city' => $orderTransfer->getShippingAddress()->getCity(),
                    'zip' => $orderTransfer->getShippingAddress()->getZipCode(),
                    'country_code' => $orderTransfer->getShippingAddress()->getIso2Code(),
                    'company' => $orderTransfer->getShippingAddress()->getCompany(),
                    'tel' => $orderTransfer->getShippingAddress()->getPhone(),
                ];
            }
        }

        $requestData['customer'] = $customerData;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     * @param array $requestData
     *
     * @return void
     */
    protected function setTransactionData(OrderTransfer $orderTransfer, &$requestData)
    {
        $paymentType = $this->getPaymentType($orderTransfer);

        $transactionData = [
            'payment_type' => $paymentType,
            'amount' => $orderTransfer->getTotals()->getGrandTotal(),
            'currency' => $this->standardParameter->getCurrency(),
            'test_mode' => ($this->standardParameter->getTestMode() === true ? 1 : 0),
            'order_no' => $orderTransfer->getOrderReference(),
            'system_ip' => $this->getIpAddress('SERVER_ADDR'),
            'system_name' => 'Spryker-B2B',
            'system_version' => '202108.0-NN1.2.0',
        ];

        $paymentData = $this->getPaymentData($orderTransfer, $paymentType);
        if (!empty($paymentData)) {
            $transactionData = array_merge($transactionData, $paymentData);
        }

        $requestData['transaction'] = $transactionData;
    }

    /**
     * @param array $requestData
     *
     * @return void
     */
    protected function setCustomData(&$requestData)
    {
        $requestData['custom'] = [
            'lang' => $this->standardParameter->getLanguage(),
            'input1' => 'locale',
            'inputval1' => Store::getInstance()->getCurrentLocale(),
        ];
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return string|null
     */
    protected function getPaymentType(OrderTransfer $orderTransfer)
    {
        $paymentMethod = '';
        foreach ($orderTransfer->getPayments() as $payments) {
            if ($payments->getPaymentProvider() == 'Novalnet') {
                $paymentMethod = $payments->getPaymentMethod();
            }
        }

        $paymentMethods = [
            'novalnetCreditCard' => 'CREDITCARD',
            'novalnetSepa' => 'DIRECT_DEBIT_SEPA',
            'novalnetSepaGuarantee' => 'GUARANTEED_DIRECT_DEBIT_SEPA',
            'novalnetInvoice' => 'INVOICE',
            'novalnetInvoiceGuarantee' => 'GUARANTEED_INVOICE',
            'novalnetPrepayment' => 'PREPAYMENT',
            'novalnetIdeal' => 'IDEAL',
            'novalnetSofort' => 'ONLINE_TRANSFER',
            'novalnetGiropay' => 'GIROPAY',
            'novalnetBarzahlen' => 'CASHPAYMENT',
            'novalnetPrzelewy' => 'PRZELEWY24',
            'novalnetEps' => 'EPS',
            'novalnetPaypal' => 'PAYPAL',
            'novalnetPostfinanceCard' => 'POSTFINANCE_CARD',
            'novalnetPostfinance' => 'POSTFINANCE',
            'novalnetBancontact' => 'BANCONTACT',
            'novalnetMultibanco' => 'MULTIBANCO',
        ];

        return $paymentMethods[$paymentMethod];
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     * @param string $paymentType
     *
     * @return array
     */
    protected function getPaymentData(OrderTransfer $orderTransfer, $paymentType)
    {
        $paymentData = [];

        switch ($paymentType) {
            case 'CREDITCARD':
                $getPaymentDetail = $this->getPaymentDetails($orderTransfer);
                $paymentData['payment_data']['pan_hash'] = !empty($getPaymentDetail['pan_hash']) ? $getPaymentDetail['pan_hash'] : '';
                $paymentData['payment_data']['unique_id'] = !empty($getPaymentDetail['unique_id']) ? $getPaymentDetail['unique_id'] : '';

                if (!empty($getPaymentDetail['do_redirect'])) {
                    $this->getRedirectPaymentDetails($paymentData);
                }

                if (
                    is_numeric($this->standardParameter->getCreditCardOnHoldLimit()) && $orderTransfer->getTotals()->getGrandTotal()
                    && (string)$this->standardParameter->getCreditCardOnHoldLimit() <= (string)$orderTransfer->getTotals()->getGrandTotal()
                ) {
                    $this->transactionType = 'authorize';
                }

                break;
            case 'DIRECT_DEBIT_SEPA':
                $getPaymentDetail = $this->getPaymentDetails($orderTransfer);
                $paymentData['payment_data']['iban'] = !empty($getPaymentDetail['iban']) ? $getPaymentDetail['iban'] : '';
                $paymentData['payment_data']['account_holder'] = $orderTransfer->getBillingAddress()->getFirstName() . ' ' . $orderTransfer->getBillingAddress()->getLastName();

                if (
                    is_numeric($this->standardParameter->getSepaDueDate())
                    && $this->standardParameter->getSepaDueDate() >= 2 && $this->standardParameter->getSepaDueDate() <= 14
                ) {
                    $paymentData['due_date'] = date('Y-m-d', strtotime('+ ' . $this->standardParameter->getSepaDueDate() . ' day'));
                }

                if (
                    is_numeric($this->standardParameter->getSepaOnHoldLimit()) && $orderTransfer->getTotals()->getGrandTotal()
                    && (string)$this->standardParameter->getSepaOnHoldLimit() <= (string)$orderTransfer->getTotals()->getGrandTotal()
                ) {
                    $this->transactionType = 'authorize';
                }

                break;
            case 'GUARANTEED_DIRECT_DEBIT_SEPA':
                $getPaymentDetail = $this->getPaymentDetails($orderTransfer);
                $paymentData['payment_data']['iban'] = !empty($getPaymentDetail['iban']) ? $getPaymentDetail['iban'] : '';
                $paymentData['payment_data']['account_holder'] = $orderTransfer->getBillingAddress()->getFirstName() . ' ' . $orderTransfer->getBillingAddress()->getLastName();

                if (
                    is_numeric($this->standardParameter->getSepaDueDate())
                    && $this->standardParameter->getSepaGuaranteeDueDate() >= 2 && $this->standardParameter->getSepaGuaranteeDueDate() <= 14
                ) {
                    $paymentData['due_date'] = date('Y-m-d', strtotime('+ ' . $this->standardParameter->getSepaGuaranteeDueDate() . ' day'));
                }

                if (
                    is_numeric($this->standardParameter->getSepaGuaranteeOnHoldLimit()) && $orderTransfer->getTotals()->getGrandTotal()
                    && (string)$this->standardParameter->getSepaGuaranteeOnHoldLimit() <= (string)$orderTransfer->getTotals()->getGrandTotal()
                ) {
                    $this->transactionType = 'authorize';
                }

                break;
            case 'PREPAYMENT':
                if (
                    is_numeric($this->standardParameter->getPrepaymentDueDate())
                    && $this->standardParameter->getPrepaymentDueDate() >= 7
                ) {
                    $paymentData['due_date'] = date('Y-m-d', strtotime('+ ' . $this->standardParameter->getPrepaymentDueDate() . ' day'));
                }

                break;
            case 'INVOICE':
                if (
                    is_numeric($this->standardParameter->getInvoiceDueDate())
                    && $this->standardParameter->getInvoiceDueDate() >= 7
                ) {
                    $paymentData['due_date'] = date('Y-m-d', strtotime('+ ' . $this->standardParameter->getInvoiceDueDate() . ' day'));
                }

                if (
                    is_numeric($this->standardParameter->getInvoiceOnHoldLimit()) && $orderTransfer->getTotals()->getGrandTotal()
                    && (string)$this->standardParameter->getInvoiceOnHoldLimit() <= (string)$orderTransfer->getTotals()->getGrandTotal()
                ) {
                    $this->transactionType = 'authorize';
                }

                break;
            case 'GUARANTEED_INVOICE':
                if (
                    is_numeric($this->standardParameter->getInvoiceGuaranteeOnHoldLimit()) && $orderTransfer->getTotals()->getGrandTotal()
                    && (string)$this->standardParameter->getInvoiceGuaranteeOnHoldLimit() <= (string)$orderTransfer->getTotals()->getGrandTotal()
                ) {
                    $this->transactionType = 'authorize';
                }

                break;
            case 'CASHPAYMENT':
                if (is_numeric($this->standardParameter->getCpDueDate())) {
                    $paymentData['due_date'] = date('Y-m-d', strtotime('+ ' . $this->standardParameter->getCpDueDate() . ' day'));
                }

                break;
            case 'PAYPAL':
                $this->getRedirectPaymentDetails($paymentData);
                if (
                    is_numeric($this->standardParameter->getPaypalOnHoldLimit()) && $orderTransfer->getTotals()->getGrandTotal()
                    && (string)$this->standardParameter->getPaypalOnHoldLimit() <= (string)$orderTransfer->getTotals()->getGrandTotal()
                ) {
                    $this->transactionType = 'authorize';
                }

                break;
            case 'IDEAL':
                $this->getRedirectPaymentDetails($paymentData);

                break;
            case 'ONLINE_TRANSFER':
                $this->getRedirectPaymentDetails($paymentData);

                break;
            case 'GIROPAY':
                $this->getRedirectPaymentDetails($paymentData);

                break;
            case 'PRZELEWY24':
                $this->getRedirectPaymentDetails($paymentData);

                break;
            case 'EPS':
                $this->getRedirectPaymentDetails($paymentData);

                break;
            case 'POSTFINANCE_CARD':
                $this->getRedirectPaymentDetails($paymentData);

                break;
            case 'POSTFINANCE':
                $this->getRedirectPaymentDetails($paymentData);

                break;
            case 'BANCONTACT':
                $this->getRedirectPaymentDetails($paymentData);

                break;
            case 'PREPAYMENT':
            case 'MULTIBANCO':
                break;
        }

        return $paymentData;
    }

    /**
     * @param array $paymentData
     *
     * @return void
     */
    protected function getRedirectPaymentDetails(&$paymentData)
    {
        $paymentData['return_url'] = $this->standardParameter->getReturnUrl();
        $paymentData['error_return_url'] = $this->standardParameter->getErrorReturnUrl();
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return array
     */
    protected function getPaymentDetails(OrderTransfer $orderTransfer)
    {
        $getPaymentDetail = [];

        $paymentDetailQuery = $this->queryContainer->createLastDetailByOrderId($orderTransfer->getIdSalesOrder());
        $paymentDetailLog = $paymentDetailQuery->findOne();

        if ($paymentDetailLog && !empty($paymentDetailLog->getPaymentDetails())) {
            $getPaymentDetail = json_decode($paymentDetailLog->getPaymentDetails(), true);
        }

        return $getPaymentDetail;
    }

    /**
     * @param object $responseData
     *
     * @return void
     */
    protected function updatePaymentDetails($responseData)
    {
        if (!empty($responseData->transaction->order_no)) {
            $paymentDetailQuery = $this->queryContainer->createLastDetailByOrderReference($responseData->transaction->order_no);
            $paymentDetailLog = $paymentDetailQuery->findOne();

            if ($paymentDetailLog && !empty($responseData->transaction->payment_data)) {
                $paymentData = json_decode(json_encode($responseData->transaction->payment_data), true);
                $paymentDetailLog->setPaymentDetails(json_encode($paymentData));
                $paymentDetailLog->setTransactionId($responseData->transaction->tid);
                $paymentDetailLog->setCustomerId($responseData->customer->customer_no);
                $paymentDetailLog->save();
            }
        }
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     * @param array $requestData
     *
     * @return object|null
     */
    protected function performAuthorizationRequest(OrderTransfer $orderTransfer, $requestData)
    {
        $apiLogEntity = $this->initializeApiLog($orderTransfer, $requestData);
        $response = $this->executionAdapter->sendRequest($requestData, $this->transactionType);

        $this->updateApiLogAfterAuthorization($apiLogEntity, $response);

        return $response;
    }

    /**
     * @param array $requestData
     *
     * @return array
     */
    protected function filterStandardParameter($requestData)
    {
        $excludedParams = ['test_mode'];

        foreach ($requestData as $key => $value) {
            if (is_array($value)) {
                $requestData[$key] = $this->filterStandardParameter($requestData[$key]);
            }

            if (!in_array($key, $excludedParams) && empty($requestData[$key])) {
                unset($requestData[$key]);
            }
        }

        return $requestData;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     * @param array $requestData
     *
     * @return \Orm\Zed\Novalnet\Persistence\SpyPaymentNovalnetTransactionLogQuery
     */
    protected function initializeApiLog(OrderTransfer $orderTransfer, $requestData)
    {
        $paymentMethod = '';
        foreach ($orderTransfer->getPayments() as $payments) {
            if ($payments->getPaymentProvider() == 'Novalnet') {
                $paymentMethod = $payments->getPaymentMethod();
            }
        }
        $idSalesOrder = $orderTransfer->getIdSalesOrder();

        $entity = new SpyPaymentNovalnetTransactionLog();
        $entity->setFkSalesOrder($idSalesOrder);
        $entity->setOrderReference($requestData['transaction']['order_no']);
        $entity->setTransactionType($this->transactionType);
        $entity->setPaymentMethod($paymentMethod);

        // Logging request data for debug
        $entity->setRawRequest(json_encode($requestData));
        $entity->save();

        return $entity;
    }

    /**
     * @param \Orm\Zed\Novalnet\Persistence\SpyPaymentNovalnetTransactionLogQuery $apiLogEntity
     * @param object $response
     *
     * @return void
     */
    protected function updateApiLogAfterAuthorization(SpyPaymentNovalnetTransactionLog $apiLogEntity, $response)
    {
        $apiLogEntity->setStatus($response->result->status_code);
        $apiLogEntity->setStatusMessage($response->result->status_text);

        if (!empty($response->result->redirect_url)) {
            $additionalData = ['redirect_url' => $response->result->redirect_url];
            $apiLogEntity->setAdditionalData(json_encode($additionalData));
            $apiLogEntity->setRawResponse(json_encode($response));
            $apiLogEntity->save();
        } elseif (
            !empty($response->transaction->tid) && !empty($response->transaction->status)
            && empty($apiLogEntity->getTransactionId())
        ) {
            $apiLogEntity->setTransactionId($response->transaction->tid);
            $apiLogEntity->setTransactionStatus($response->transaction->status);
            $additionalData = $this->getAdditionalData($apiLogEntity, $response);
            $apiLogEntity->setAdditionalData(json_encode($additionalData));
            $apiLogEntity->setPaymentResponse(json_encode($response));
            $apiLogEntity->save();

            if (!empty($response->transaction->payment_data)) {
                $this->updatePaymentDetails($response);
            }
        }
    }

    /**
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     * @param \Generated\Shared\Transfer\CheckoutResponseTransfer $checkoutResponse
     *
     * @return \Generated\Shared\Transfer\CheckoutResponseTransfer
     */
    public function postSaveHook(QuoteTransfer $quoteTransfer, CheckoutResponseTransfer $checkoutResponse)
    {
        $paymentSelection = $quoteTransfer->getPayment()->getPaymentSelection();
        $method = 'get' . ucfirst($paymentSelection);

        $transactionLogsQuery = $this->queryContainer->createLastApiLogsByOrderId($quoteTransfer->getPayment()->$method()->getFkSalesOrder());
        $transactionLog = $transactionLogsQuery->findOne();

        if ($transactionLog) {
            $additionalData = $transactionLog->getAdditionalData() ? json_decode($transactionLog->getAdditionalData()) : '';
            $redirectUrl = !empty($additionalData->redirect_url) ? $additionalData->redirect_url : '';

            if ($redirectUrl !== null) {
                $checkoutResponse->setIsExternalRedirect(true);
                $checkoutResponse->setRedirectUrl($redirectUrl);
            }

            $status = $transactionLog->getStatus();

            if (!empty($transactionLog->getTransactionId()) && $status == 100) {
                $checkoutResponse->setIsSuccess(true);
            } elseif ($status != 100) {
                $error = new CheckoutErrorTransfer();
                $error->setMessage($transactionLog->getStatusMessage());
                $error->setErrorCode($status);
                $checkoutResponse->addError($error);
                $checkoutResponse->setIsSuccess(false);
            }
        }

        return $checkoutResponse;
    }

    /**
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     * @param \Generated\Shared\Transfer\SaveOrderTransfer $saveOrderTransfer
     *
     * @return \Generated\Shared\Transfer\SaveOrderTransfer
     */
    public function saveOrderHook(QuoteTransfer $quoteTransfer, SaveOrderTransfer $saveOrderTransfer)
    {
        $idSalesOrder = $saveOrderTransfer->getIdSalesOrder();

        $paymentSelection = $quoteTransfer->getPayment()->getPaymentSelection();
        $method = 'get' . ucfirst($paymentSelection);
        $paymentTransfer = $quoteTransfer->getPayment()->$method();
        $paymentTransfer->setFkSalesOrder($idSalesOrder);

        $paymentDetail = [];
        if (
            ($paymentSelection == NovalnetConfig::NOVALNET_PAYMENT_METHOD_SEPA || $paymentSelection == NovalnetConfig::NOVALNET_PAYMENT_METHOD_SEPA_GUARANTEE)
            && !empty($paymentTransfer->getIban())
        ) {
            $paymentDetail['iban'] = $paymentTransfer->getIban();
        }

        if (
            $paymentSelection == NovalnetConfig::NOVALNET_PAYMENT_METHOD_CC
            && !empty($paymentTransfer->getPanHash())
            && !empty($paymentTransfer->getUniqueId())
        ) {
            $paymentDetail['pan_hash'] = $paymentTransfer->getPanHash();
            $paymentDetail['unique_id'] = $paymentTransfer->getUniqueId();
            $paymentDetail['do_redirect'] = $paymentTransfer->getDoRedirect();
        }

        $paymentDetailQuery = $this->queryContainer->createLastDetailByOrderId($idSalesOrder);
        $paymentDetailLog = $paymentDetailQuery->findOne();

        if ($paymentDetailLog) {
            $paymentDetailLog->setPaymentDetails(json_encode($paymentDetail));
            $paymentDetailLog->setPaymentMethod($paymentSelection);
            $paymentDetailLog->save();
        } else {
            $orderReference = $saveOrderTransfer->getOrderReference();
            $entity = new SpyPaymentNovalnetDetail();
            $entity->setFkSalesOrder($idSalesOrder);
            $entity->setOrderReference($orderReference);
            $entity->setPaymentDetails(json_encode($paymentDetail));
            $entity->save();
        }

        return $saveOrderTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\NovalnetRedirectResponseTransfer $redirectResponseTransfer
     *
     * @return \Generated\Shared\Transfer\NovalnetRedirectResponseTransfer
     */
    public function processRedirectPaymentResponse(NovalnetRedirectResponseTransfer $redirectResponseTransfer)
    {
        $paymentResponse = $redirectResponseTransfer->getPaymentResponse();

        $transactionData = [];
        if (
            !empty($paymentResponse['checksum']) && !empty($paymentResponse['tid'])
            && !empty($paymentResponse['tid'])
        ) {
            $requestData['transaction'] = [
                'tid' => $paymentResponse['tid'],
            ];
            $transactionData = $this->executionAdapter->sendRequest($requestData, 'transaction');
        }

        $transactionLog = '';

        if (!empty($transactionData->transaction->order_no)) {
            $transactionLogsQuery = $this->queryContainer->createLastApiLogsByOrderReference($transactionData->transaction->order_no);
            $transactionLog = $transactionLogsQuery->findOne();
        }

        if ($transactionLog) {
            $transactionLog->setPaymentResponse(json_encode($transactionData));
            $transactionLog->setStatusMessage($transactionData->result->status_text);
            $transactionLog->setStatus($transactionData->result->status_code);

            $redirectResponseTransfer->setOrderNo($transactionData->transaction->order_no);
            $redirectResponseTransfer->setPaymentMethod($transactionLog->getPaymentMethod());

            if (!empty($transactionData->transaction->tid) && !empty($transactionData->transaction->status)) {
                if (empty($transactionLog->getTransactionId())) {
                    $transactionLog->setTransactionId($transactionData->transaction->tid);
                    $transactionLog->setTransactionStatus($transactionData->transaction->status);
                    $additionalData = $this->getAdditionalData($transactionLog, $transactionData);
                    $transactionLog->setAdditionalData(json_encode($additionalData));
                }

                if (in_array($transactionData->transaction->status, ['CONFIRMED', 'PENDING', 'ON_HOLD'])) {
                        $redirectResponseTransfer->setIsSuccess(true);
                } else {
                    $redirectResponseTransfer->setIsSuccess(false);
                }
            } else {
                $redirectResponseTransfer->setIsSuccess(false);
            }

            $transactionLog->save();
        }

        return $redirectResponseTransfer;
    }

    /**
     * @param \Orm\Zed\Novalnet\Persistence\SpyPaymentNovalnetTransactionLogQuery $transactionLog
     * @param object $transactionData
     *
     * @return array
     */
    protected function getAdditionalData(SpyPaymentNovalnetTransactionLog $transactionLog, $transactionData)
    {
        $paymentMode = (int)($this->standardParameter->getTestMode() === true || $transactionData->transaction->test_mode == 1);
        $comments = ['test_mode' => $paymentMode];
        $paymentComments = $this->getPaymentComments($transactionLog, $transactionData);
        $comments = array_merge($paymentComments, $comments);

        if (!empty($transactionLog->getAdditionalData())) {
            $additionalData = json_decode($transactionLog->getAdditionalData(), true);
            $comments = array_merge($additionalData, $comments);
        }

        return $comments;
    }

    /**
     * @param int $idSalesOrder
     *
     * @return bool
     */
    public function isAuthorized(int $idSalesOrder)
    {
        $transactionLogsQuery = $this->queryContainer->createLastApiLogsByOrderId($idSalesOrder);
        $transactionLog = $transactionLogsQuery->findOne();

        $status = false;

        if ($transactionLog) {
            $status = $transactionLog->getStatus() == 100 ? true : false;
        }

        return $status;
    }

    /**
     * @param int $idSalesOrder
     *
     * @return bool
     */
    public function isPayment3DSecure(int $idSalesOrder)
    {
        $transactionLogsQuery = $this->queryContainer->createLastApiLogsByOrderId($idSalesOrder);
        $transactionLog = $transactionLogsQuery->findOne();

        $status = false;

        if ($transactionLog) {
            $additionalData = !empty($transactionLog->getAdditionalData()) ? json_decode($transactionLog->getAdditionalData(), true) : '';
            $status = !empty($additionalData['redirect_url']) ? true : false;
        }

        return $status;
    }

    /**
     * @param int $idSalesOrder
     *
     * @return bool
     */
    public function isAuthorizeError(int $idSalesOrder)
    {
        $transactionLogsQuery = $this->queryContainer->createLastApiLogsByOrderId($idSalesOrder);
        $transactionLog = $transactionLogsQuery->findOne();

        $status = true;

        if ($transactionLog) {
            $status = $transactionLog->getStatus() != 100 ? true : false;
        }

        return $status;
    }

    /**
     * @param int $idSalesOrder
     *
     * @return bool
     */
    public function isPaymentAuthorized(int $idSalesOrder)
    {
        $transactionLogsQuery = $this->queryContainer->createLastApiLogsByOrderId($idSalesOrder);
        $transactionLog = $transactionLogsQuery->findOne();

        $status = false;

        if ($transactionLog) {
            $status = (in_array($transactionLog->getTransactionStatus(), $this->pendingStatus)) ? true : false;
        }

        return $status;
    }

    /**
     * @param int $idSalesOrder
     *
     * @return bool
     */
    public function isWaitingForPayment(int $idSalesOrder)
    {
        $transactionLogsQuery = $this->queryContainer->createLastApiLogsByOrderId($idSalesOrder);
        $transactionLog = $transactionLogsQuery->findOne();

        $status = false;

        if ($transactionLog) {
            $status = ($transactionLog->getTransactionStatus() == 'PENDING') ? true : false;
        }

        return $status;
    }

    /**
     * @param int $idSalesOrder
     *
     * @return bool
     */
    public function isPaymentCanceled(int $idSalesOrder)
    {
        $transactionLogsQuery = $this->queryContainer->createLastApiLogsByOrderId($idSalesOrder);
        $transactionLog = $transactionLogsQuery->findOne();

        $status = false;

        if ($transactionLog) {
            $status = (!empty($transactionLog->getTransactionStatus()) && !in_array($transactionLog->getTransactionStatus(), $this->successStatus)) ? true : false;
        }

        return $status;
    }

    /**
     * @param int $idSalesOrder
     *
     * @return bool
     */
    public function isPaymentPaid(int $idSalesOrder)
    {
        $transactionLogsQuery = $this->queryContainer->createLastApiLogsByOrderId($idSalesOrder);
        $transactionLog = $transactionLogsQuery->findOne();

        $status = false;

        if ($transactionLog) {
            $status = $transactionLog->getTransactionStatus() == 'CONFIRMED' ? true : false;
        }

        return $status;
    }

    /**
     * @param int $idSalesOrder
     *
     * @return bool
     */
    public function isCallbackPaid(int $idSalesOrder)
    {
        $transactionLogsQuery = $this->queryContainer->createLastApiLogsByOrderId($idSalesOrder);
        $transactionLog = $transactionLogsQuery->findOne();

        $status = false;

        if ($transactionLog) {
            $status = $transactionLog->getTransactionStatus() == 'CONFIRMED' ? true : false;
        }

        return $status;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return bool|null
     */
    public function capture(OrderTransfer $orderTransfer)
    {
        $idSalesOrder = $orderTransfer->getIdSalesOrder();

        $transactionLogsQuery = $this->queryContainer->createLastApiLogsByOrderId($idSalesOrder);
        $transactionLog = $transactionLogsQuery->findOne();

        if ($transactionLog) {
            $response = $this->performCaptureRequest($transactionLog);

            if ($response->result->status) {
                $transactionLog->setStatus($response->result->status);

                if (
                    $response->transaction->status == 'CONFIRMED'
                    || (in_array($response->transaction->payment_type, ['INVOICE', 'PREPAYMENT', 'MULTIBANCO', 'CASHPAYMENT'])
                        && $response->transaction->status == 'PENDING')
                ) {
                    $transactionLog->setTransactionStatus($response->transaction->status);
                    $comments = $this->getTransactionComments('capture', $transactionLog, $response);
                    $transactionLog->setAdditionalData(json_encode($comments));
                }

                $transactionLog->save();
            }

            return true;
        }
    }

    /**
     * @param \Orm\Zed\Novalnet\Persistence\SpyPaymentNovalnetTransactionLogQuery $transactionLog
     *
     * @return object|null
     */
    protected function performCaptureRequest(SpyPaymentNovalnetTransactionLog $transactionLog)
    {
        $captureParams = [
            'transaction' => [
                'tid' => $transactionLog->getTransactionId(),
            ],
            'custom' => [
                'lang' => $this->standardParameter->getLanguage(),
            ],
        ];

        $response = $this->executionAdapter->sendRequest($captureParams, 'capture');

        return $response;
    }

    /**
     * @param int $idSalesOrder
     *
     * @return bool
     */
    public function isPaymentCaptured(int $idSalesOrder)
    {
        $transactionLogsQuery = $this->queryContainer->createLastApiLogsByOrderId($idSalesOrder);
        $transactionLog = $transactionLogsQuery->findOne();

        $status = false;

        if ($transactionLog) {
            $status = ($transactionLog->getTransactionStatus() == 'CONFIRMED'
                || (in_array($transactionLog->getPaymentMethod(), ['novalnetInvoice', 'novalnetPrepayment', 'novalnetBarzahlen', 'novalnetMultibanco'])
                        && $transactionLog->getTransactionStatus() == 'PENDING')) ? true : false;
        }

        return $status;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return bool|null
     */
    public function cancel(OrderTransfer $orderTransfer)
    {
        $idSalesOrder = $orderTransfer->getIdSalesOrder();

        $transactionLogsQuery = $this->queryContainer->createLastApiLogsByOrderId($idSalesOrder);
        $transactionLog = $transactionLogsQuery->findOne();

        if ($transactionLog) {
            $response = $this->performCancelRequest($transactionLog);

            if ($response->result->status) {
                $transactionLog->setStatus($response->result->status);

                if ($response->transaction->status == 'DEACTIVATED') {
                    $transactionLog->setTransactionStatus($response->transaction->status);
                    $comments = $this->getTransactionComments('cancel', $transactionLog);
                    $transactionLog->setAdditionalData(json_encode($comments));
                }

                $transactionLog->save();
            }

            return true;
        }
    }

    /**
     * @param \Orm\Zed\Novalnet\Persistence\SpyPaymentNovalnetTransactionLogQuery $transactionLog
     *
     * @return object
     */
    protected function performCancelRequest(SpyPaymentNovalnetTransactionLog $transactionLog)
    {
        $cancelParams = [
            'transaction' => [
                'tid' => $transactionLog->getTransactionId(),
            ],
            'custom' => [
                'lang' => $this->standardParameter->getLanguage(),
            ],
        ];

        $response = $this->executionAdapter->sendRequest($cancelParams, 'cancel');

        return $response;
    }

    /**
     * @param int $idSalesOrder
     *
     * @return bool
     */
    public function isPaymentVoided(int $idSalesOrder)
    {
        $transactionLogsQuery = $this->queryContainer->createLastApiLogsByOrderId($idSalesOrder);
        $transactionLog = $transactionLogsQuery->findOne();

        $status = false;

        if ($transactionLog) {
            $status = ($transactionLog->getTransactionStatus() == 'DEACTIVATED') ? true : false;
        }

        return $status;
    }

    /**
     * @param \Generated\Shared\Transfer\NovalnetRefundTransfer $refundTransfer
     *
     * @return object|null
     */
    public function refund(NovalnetRefundTransfer $refundTransfer)
    {
        $orderTransfer = $refundTransfer->getOrder();
        $idSalesOrder = $orderTransfer->getIdSalesOrder();

        $transactionLogsQuery = $this->queryContainer->createLastApiLogsByOrderId($idSalesOrder);
        $transactionLog = $transactionLogsQuery->findOne();

        if ($transactionLog) {
            $refundAmount = $refundTransfer->getAmount();
            $response = $this->performRefundRequest($transactionLog, $refundAmount);

            if ($response->result->status) {
                $transactionLog->setStatus($response->result->status);

                if ($response->transaction->status) {
                    $transactionLog->setTransactionStatus($response->transaction->status);
                    $comments = $this->getTransactionComments('', $transactionLog, $response, $refundTransfer);
                    $transactionLog->setAdditionalData(json_encode($comments));
                }

                $transactionLog->save();
            }

            return $response;
        }
    }

    /**
     * @param \Orm\Zed\Novalnet\Persistence\SpyPaymentNovalnetTransactionLogQuery $transactionLog
     * @param int $refundAmount
     *
     * @return object
     */
    protected function performRefundRequest(SpyPaymentNovalnetTransactionLog $transactionLog, int $refundAmount)
    {
        $refundParams = [
            'transaction' => [
                'tid' => $transactionLog->getTransactionId(),
                'amount' => $refundAmount,
            ],
            'custom' => [
                'lang' => $this->standardParameter->getLanguage(),
            ],
        ];

        $response = $this->executionAdapter->sendRequest($refundParams, 'refund');

        return $response;
    }

    /**
     * @param int $idSalesOrder
     *
     * @return bool
     */
    public function isPaymentRefunded(int $idSalesOrder)
    {
        $transactionLogsQuery = $this->queryContainer->createLastApiLogsByOrderId($idSalesOrder);
        $transactionLog = $transactionLogsQuery->findOne();

        $status = false;

        if ($transactionLog) {
            $status = (in_array($transactionLog->getTransactionStatus(), ['CONFIRMED', 'DEACTIVATED'])) ? true : false;
        }

        return $status;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\OrderTransfer
     */
    public function getTransactionDetails(OrderTransfer $orderTransfer)
    {
        $idSalesOrder = $orderTransfer->getIdSalesOrder();

        $transactionLogsQuery = $this->queryContainer->createLastApiLogsByOrderId($idSalesOrder);
        $transactionLog = $transactionLogsQuery->findOne();

        if ($transactionLog) {
            $novalnetTransfer = new NovalnetTransfer();

            $novalnetTransfer->setFkSalesOrder($idSalesOrder);
            $novalnetTransfer->setTransactionId($transactionLog->getTransactionId());
            $novalnetTransfer->setTransactionStatus($transactionLog->getTransactionStatus());

            $additionalData = json_decode($transactionLog->getAdditionalData(), true);

            if (
                $transactionLog->getStatusMessage()
                && !in_array($transactionLog->getTransactionStatus(), $this->successStatus)
            ) {
                $novalnetTransfer->setTransactionComments($transactionLog->getStatusMessage());
            }

            if (!empty($additionalData['test_mode'])) {
                $novalnetTransfer->setPaymentMode($additionalData['test_mode']);
            }

            if (!empty($additionalData['trans_comments'])) {
                $novalnetTransfer->setTransactionComments($additionalData['trans_comments']);
            }

            if (!empty($additionalData['callback_comments'])) {
                $novalnetTransfer->setCallbackComments($additionalData['callback_comments']);
            }

            if (
                !empty($additionalData['invoice_comments'])
                && ((in_array($transactionLog->getPaymentMethod(), ['novalnetInvoice', 'novalnetPrepayment']) && !in_array($transactionLog->getTransactionStatus(), ['CONFIRMED', 'DEACTIVATED']))
                || ($transactionLog->getPaymentMethod() == 'novalnetInvoiceGuarantee' && !in_array($transactionLog->getTransactionStatus(), ['PENDING', 'DEACTIVATED'])))
            ) {
                $novalnetTransfer->setInvoiceComments($additionalData['invoice_comments']);
            }

            if (!empty($additionalData['guarantee_pending_comments']) && $transactionLog->getTransactionStatus() == 'PENDING') {
                $novalnetTransfer->setguaranteePendingComments($additionalData['guarantee_pending_comments']);
            }

            $orderTransfer->setNovalnet($novalnetTransfer);
        }

        return $orderTransfer;
    }

    /**
     * @param string|null $transactionType
     * @param \Orm\Zed\Novalnet\Persistence\SpyPaymentNovalnetTransactionLogQuery $transactionLog
     * @param object|null $response
     * @param \Generated\Shared\Transfer\NovalnetRefundTransfer|null $refundTransfer
     *
     * @return array
     */
    protected function getTransactionComments(
        $transactionType,
        SpyPaymentNovalnetTransactionLog $transactionLog,
        $response = null,
        ?NovalnetRefundTransfer $refundTransfer = null
    ) {
        $additionalData = json_decode($transactionLog->getAdditionalData(), true);
        $transactionText = '';

        if (in_array($transactionType, ['capture', 'cancel'])) {
            $transactionText = ($transactionType == 'capture')
                ? $this->translate('novalnet.payment-confirmed', ['%confirmed%' => date('Y-m-d H:i:s')])
                : $this->translate('novalnet.payment-void', ['%cancelled%' => date('Y-m-d H:i:s')]);
            if (!empty($response->transaction->due_date) && in_array($response->transaction->payment_type, ['PREPAYMENT', 'INVOICE', 'GUARANTEED_INVOICE'])) {
                $invoiceComments = explode('|', $additionalData['invoice_comments']);
                $invoiceComments[1] = $this->translate('novalnet.invoice-bank-note-transfer-text-with-due-date', ['%amount%' => number_format($response->transaction->amount / 100, 2, '.') . ' ' . $response->transaction->currency, '%due_date%' => $response->transaction->due_date]);
                $additionalData['invoice_comments'] = implode('|', $invoiceComments);
            }
        } else {
            $amount = number_format($refundTransfer->getAmount() / 100, 2, '.') . ' ' . $refundTransfer->getOrder()->getCurrencyIsoCode();
            $transactionText = $this->translate('novalnet.payment-full-refund', ['%parentTid%' => $transactionLog->getTransactionId(), '%amount%' => $amount]);

            if (!empty($response->transaction->refund->tid)) {
                $transactionText = $this->translate('novalnet.payment-partial-refund', ['%parentTid%' => $transactionLog->getTransactionId(), '%amount%' => $amount, '%referenceTid%' => $response->transaction->refund->tid]);
            }
        }

        $transactionComments = !empty($additionalData['trans_comments'])
            ? $additionalData['trans_comments'] . $transactionText : $transactionText;

        $additionalData['trans_comments'] = $transactionComments . '|';

        return $additionalData;
    }

    /**
     * @param \Orm\Zed\Novalnet\Persistence\SpyPaymentNovalnetTransactionLogQuery $transactionLog
     * @param object $paymentResponse
     *
     * @return array
     */
    protected function getPaymentComments(SpyPaymentNovalnetTransactionLog $transactionLog, $paymentResponse)
    {
        $additionalData = [];
        $transactionText = '';

        if (!empty($transactionLog->getAdditionalData())) {
            $additionalData = json_decode($transactionLog->getAdditionalData(), true);
        }

        if (is_array($paymentResponse)) {
            $paymentResponse = json_decode(json_encode($paymentResponse), false);
        }

        if (in_array($paymentResponse->transaction->payment_type, ['PREPAYMENT', 'INVOICE', 'GUARANTEED_INVOICE'])) {
            $invoiceComments = $this->getInvoicePaymentNote($paymentResponse);
            $additionalData['invoice_comments'] = $invoiceComments . '|';
        }

        if (
            in_array($paymentResponse->transaction->payment_type, ['GUARANTEED_INVOICE', 'GUARANTEED_DIRECT_DEBIT_SEPA'])
            && $paymentResponse->transaction->status == 'PENDING'
        ) {
            $additionalData['guarantee_pending_comments'] = $this->translate('novalnet.guarantee-pending-text');
        }

        if ($paymentResponse->transaction->payment_type == 'CASHPAYMENT') {
            $transactionText = $this->getCashPaymentComments($paymentResponse);
        }

        if ($paymentResponse->transaction->payment_type == 'MULTIBANCO') {
            $transactionText = $this->getMultibancoComments($paymentResponse);
        }

        if ($transactionText) {
            $transactionComments = !empty($additionalData['trans_comments'])
                ? $additionalData['trans_comments'] . $transactionText : $transactionText;

            $additionalData['trans_comments'] = $transactionComments . '|';
        }

        return $additionalData;
    }

    /**
     * @param object $response
     *
     * @return string
     */
    protected function getInvoicePaymentNote($response)
    {
        $note = null;
        $amount = number_format($response->transaction->amount / 100, 2, '.');
        $note = '|' . $this->translate('novalnet.invoice-bank-note-transfer-text', ['%amount%' => $amount, '%currency%' => $response->transaction->currency]);

        if (!empty($response->transaction->due_date) && $response->transaction->status != 'ON_HOLD') {
            $note = '|' . $this->translate('novalnet.invoice-bank-note-transfer-text-with-due-date', ['%amount%' => $amount . ' ' . $response->transaction->currency, '%due_date%' => $response->transaction->due_date]);
        }

        $note .= '|' . $this->translate('novalnet.invoice-bank-note-account-holder', ['%holder%' => $response->transaction->bank_details->account_holder]);
        $note .= '|' . $this->translate('novalnet.invoice-bank-note-iban', ['%iban%' => $response->transaction->bank_details->iban]);
        $note .= '|' . $this->translate('novalnet.invoice-bank-note-bic', ['%bic%' => $response->transaction->bank_details->bic]);
        $note .= '|' . $this->translate('novalnet.invoice-bank-note-bank', ['%bank%' => $response->transaction->bank_details->bank_name, '%place%' => $response->transaction->bank_details->bank_place]);
        $note .= '|' . $this->translate('novalnet.invoice-bank-note-reference-text', []);
        if (!empty($response->transaction->invoice_ref)) {
            $note .= '|' . $this->translate('novalnet.invoice-bank-note-reference-one', ['%ref1%' => $response->transaction->tid]);
        }
        $note .= '|' . $this->translate('novalnet.invoice-bank-note-reference-two', ['%ref2%' => $response->transaction->invoice_ref]);

        return $note;
    }

    /**
     * @param array $response
     *
     * @return string
     */
    protected function getMultibancoComments($response)
    {
        $amount = number_format($response->transaction->amount / 100, 2, '.');
        $note = null;
        $note .= '|' . $this->translate('novalnet.multibanco-reference-note', ['%amount%' => $amount, '%currency%' => $response->transaction->currency]);
        $note .= '|' . $this->translate('novalnet.multibanco-reference-comment', ['%ref%' => $response->transaction->partner_payment_reference]);

        return $note;
    }

    /**
     * @param array $response
     *
     * @return string
     */
    protected function getCashPaymentComments($response)
    {
        $note = $this->translate('novalnet.cashpayment-slip-expiry-date', ['%date%' => $response->transaction->due_date]);
        $note .= '|' . $this->translate('novalnet.cashpayment-stores-near-you', []);

        foreach ($response->transaction->nearest_stores as $count => $storeInfo) {
            $note .= "| Store($count):";
            $note .= '|' . $storeInfo->store_name;
            $note .= '|' . $storeInfo->country_code;
            $note .= '|' . $storeInfo->street;
            $note .= '|' . $storeInfo->city;
            $note .= '|' . $storeInfo->zip;
        }

        return $note;
    }

    /**
     * @param string $keyName
     * @param array $data
     *
     * @return string
     */
    public function translate($keyName, array $data = [])
    {
        $keyName = $this->glossaryFacade->translate($keyName, $data);

        return $keyName;
    }

    /**
     * @param string $type
     *
     * @return string
     */
    public function getIpAddress($type = 'REMOTE_ADDR')
    {
        // Check to determine the IP address type
        if ($type == 'SERVER_ADDR') {
            if (empty($_SERVER['SERVER_ADDR'])) {
                // Handled for IIS server
                $ip_address = gethostbyname($_SERVER['SERVER_NAME']);
            } else {
                $ip_address = $_SERVER['SERVER_ADDR'];
            }
        } else { // For remote address
            $ip_address = $this->getRemoteAddress();
        }

        return (filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) ? '127.0.0.1' : $ip_address;
    }

    /**
     * @return string
     */
    public function getRemoteAddress()
    {
        $ip_keys = ['HTTP_X_FORWARDED_HOST', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    // trim for safety measures
                    return trim($ip);
                }
            }
        }
    }
}
