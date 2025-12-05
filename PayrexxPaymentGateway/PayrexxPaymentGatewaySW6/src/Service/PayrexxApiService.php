<?php

namespace PayrexxPaymentGateway\Service;

use Payrexx\Communicator;
use Payrexx\Models\Response\Gateway;
use Payrexx\Models\Response\Transaction;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class PayrexxApiService
{
    protected EntityRepository $customerRepository;
    protected LoggerInterface $logger;
    protected ConfigService $configService;

    public function __construct(EntityRepository $customerRepository, LoggerInterface $logger, ConfigService $configService)
    {
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
        $this->configService = $configService;
    }

    private function getInterface(string $salesChannelId): \Payrexx\Payrexx
    {
        $config = $this->configService->getPluginConfiguration($salesChannelId);
        $platform = !empty($config['platform']) ? $config['platform'] : Communicator::API_URL_BASE_DOMAIN;
        return new \Payrexx\Payrexx($config['instanceName'], $config['apiKey'], '', $platform);
    }

    /**
     * Create a checkout page in Payrexx (Payrexx Gateway)
     */
    public function createPayrexxGateway(
        string $orderNumber,
        float $amount,
        float $averageVatRate,
        string $currency,
        string $paymentMean,
        array $billingAndShippingDetails,
        array $redirectUrl,
        array $basket,
        string $salesChannelId,
        string $purpose,
        array $metaData
    ): ?Gateway  {
        $config = $this->configService->getPluginConfiguration($salesChannelId);
        $lookAndFeelId = !empty($config['lookFeelID']) ? $config['lookFeelID'] : null;

        $payrexx = $this->getInterface($salesChannelId);
        $gateway = new \Payrexx\Models\Request\Gateway();
        $amount = number_format($amount, 2, '.', '');
        $amountInCents = (int) str_replace('.', '', $amount);
        $gateway->setAmount($amountInCents);
        $gateway->setVatRate($averageVatRate);
        $gateway->setCurrency($currency);
        $gateway->setSuccessRedirectUrl($redirectUrl['success']);
        $gateway->setFailedRedirectUrl($redirectUrl['cancel']);
        $gateway->setCancelRedirectUrl($redirectUrl['cancel']);
        $gateway->setSkipResultPage(true);
        $gateway->setLookAndFeelProfile($lookAndFeelId);

        $gateway->setPsp([]);
        $gateway->setPm([$paymentMean]);
        $gateway->setReferenceId($orderNumber);
        $gateway->setValidity(15);

        foreach ($billingAndShippingDetails as $fieldKey => $fieldValue) {
            $gateway->addField($fieldKey, $fieldValue);
        }

        if (!empty($basket)) {
            $gateway->setBasket($basket);
        } else {
            $gateway->setPurpose($purpose);
        }

        if (!empty($metaData)) {
            $payrexx->setHttpHeaders($metaData);
        }
        try {
            return $payrexx->create($gateway);
        } catch (\Payrexx\PayrexxException $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }
        return null;
    }

    public function getPayrexxGateway(int $gatewayId, string $salesChannelId): Gateway|bool
    {
        if (!$gatewayId) {
            return false;
        }
        $payrexx = $this->getInterface($salesChannelId);
        $gateway = new \Payrexx\Models\Request\Gateway();
        $gateway->setId($gatewayId);
        try {
            $payrexxGateway = $payrexx->getOne($gateway);
            return $payrexxGateway;
        } catch (\Payrexx\PayrexxException $e) {
        }
        return false;
    }

    /**
     * capture a Transaction
     */
    public function captureTransaction(int $transactionId, string $salesChannelId): void
    {
        if (!$transactionId) {
            return;
        }
        $payrexx = $this->getInterface($salesChannelId);

        $transaction = new \Payrexx\Models\Request\Transaction();
        $transaction->setId($transactionId);

        try {
            $payrexx->capture($transaction);
        } catch (\Payrexx\PayrexxException $e) {
            return;
        }
    }

    public function getTransactionByGateway(Gateway $payrexxGateway, string $salesChannelId): ?\Payrexx\Models\Response\Transaction
    {
        if (!in_array($payrexxGateway->getStatus(), [Transaction::CONFIRMED, Transaction::WAITING])) {
            return null;
        }
        $invoices = $payrexxGateway->getInvoices();

        if (!$invoices || !$invoice = end($invoices)) {
            return null;
        }

        if (!$transactions = $invoice['transactions']) {
            return null;
        }

        return $this->getPayrexxTransaction(end($transactions)['id'], $salesChannelId);
    }

    public function getPayrexxTransaction(int $payrexxTransactionId, string $salesChannelId): ?\Payrexx\Models\Response\Transaction
    {
        $payrexx = $this->getInterface($salesChannelId);

        $payrexxTransaction = new \Payrexx\Models\Request\Transaction();
        $payrexxTransaction->setId($payrexxTransactionId);

        try {
            $response = $payrexx->getOne($payrexxTransaction);
            return $response;
        } catch(\Payrexx\PayrexxException $e) {
            return null;
        }
    }

    /**
     * Delete the payrexx gateway
     */
    public function deletePayrexxGateway(string $salesChannelId, int $gatewayId): bool
    {
        if (empty($gatewayId)) {
            return true;
        }

        $payrexx = $this->getInterface($salesChannelId);
        $gateway = $this->getPayrexxGateway($gatewayId, $salesChannelId);

        if (!$gateway) {
            return true; // Already deleted.
        }
        if ($payrexx && $gateway) {
            $transaction = $this->getTransactionByGateway($gateway, $salesChannelId);
            if (!$transaction) {
                try {
                    $payrexx->delete($gateway);
                    return true;
                } catch (\Payrexx\PayrexxException $e) {
                    // no action.
                }
            }

        }
        return false;
    }
}
