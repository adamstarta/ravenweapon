<?php


namespace PayrexxPaymentGateway\Installer\Modules;

use PayrexxPaymentGateway\Installer\InstallerInterface;
use Shopware\Core\Framework\Context;
use PayrexxPaymentGateway\Handler\PaymentHandler;
use PayrexxPaymentGateway\PayrexxPaymentGatewaySW6;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class PaymentMethodInstaller implements InstallerInterface
{

    public const PAYREXX_MASTERPASS         = 'masterpass';
    public const PAYREXX_MASTERCARD         = 'mastercard';
    public const PAYREXX_VISA               = 'visa';
    public const PAYREXX_APPLE_PAY          = 'apple_pay';
    public const PAYREXX_MAESTRO            = 'maestro';
    public const PAYREXX_JCB                = 'jcb';
    public const PAYREXX_AMEX               = 'american_express';
    public const PAYREXX_WIR                = 'wirpay';
    public const PAYREXX_PAYPAL             = 'paypal';
    public const PAYREXX_BITCOIN            = 'bitcoin';
    public const PAYREXX_AIRPLUS            = 'airplus';
    public const PAYREXX_BILLPAY            = 'billpay';
    public const PAYREXX_BONUSCARD          = 'bonuscard';
    public const PAYREXX_CASHU              = 'cashu';
    public const PAYREXX_CB                 = 'cb';
    public const PAYREXX_DINERS             = 'diners_club';
    public const PAYREXX_DIRECT_DEBIT       = 'direct_debit';
    public const PAYREXX_DISCOVER           = 'discover';
    public const PAYREXX_ELV                = 'elv';
    public const PAYREXX_IDEAL              = 'ideal';
    public const PAYREXX_INVOICE            = 'invoice';
    public const PAYREXX_MYONE              = 'myone';
    public const PAYREXX_PAYSAFECARD        = 'paysafecard';
    public const PAYREXX_SWISSBILLING       = 'swissbilling';
    public const PAYREXX_TWINT              = 'twint';
    public const PAYREXX_VIACASH            = 'barzahlen';
    public const PAYREXX_BANCONTANCT        = 'bancontact';
    public const PAYREXX_GIROPAY            = 'giropay';
    public const PAYREXX_EPS                = 'eps';
    public const PAYREXX_GOOGLE_PAY         = 'google_pay';
    public const PAYREXX_KLARNA_PAYNOW      = 'klarna_paynow';
    public const PAYREXX_KLARNA_PAYLATER    = 'klarna_paylater';
    public const PAYREXX_ONEY               = 'oney';
    public const PAYREXX_BOB_INVOICE        = 'bob-invoice';
    public const PAYREXX_CENTI              = 'centi';
    public const PAYREXX_HEIDIPAY           = 'heidipay';
    public const PAYREXX_REKA               = 'reka';
    public const PAYREXX_BANK_TRANSFER      = 'bank-transfer';
    public const PAYREXX_KLARNA             = 'klarna';
    public const PAYREXX_POSTFINANCE_PAY    = 'post-finance-pay';
    public const PAYREXX_PRE_PAYMENT        = 'pre-payment';
    public const PAYREXX_PAY_BY_BANK        = 'pay-by-bank';
    public const PAYREXX_POWERPAY           = 'powerpay';
    public const PAYREXX_CEMBRAPAY          = 'cembrapay';
    public const PAYREXX_CRYPTO             = 'crypto';
    public const PAYREXX_VERD_CASH          = 'verd-cash';
    public const PAYREXX_NO_PM              = '';

    const PAYMENT_METHODS = [
        self::PAYREXX_MASTERPASS => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Masterpass',
                ],
                'en-GB' => [
                    'name' => 'Masterpass',
                ],
            ],
        ],
        self::PAYREXX_MASTERCARD => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Mastercard',
                ],
                'en-GB' => [
                    'name' => 'Mastercard',
                ],
            ],
        ],
        self::PAYREXX_VISA => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Visa',
                ],
                'en-GB' => [
                    'name' => 'Visa',
                ],
            ],
        ],
        self::PAYREXX_APPLE_PAY => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Apple Pay',
                ],
                'en-GB' => [
                    'name' => 'Apple Pay',
                ],
            ],
        ],
        self::PAYREXX_MAESTRO => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Maestro',
                ],
                'en-GB' => [
                    'name' => 'Maestro',
                ],
            ],
        ],
        self::PAYREXX_JCB => [
            'translations' => [
                'de-DE' => [
                    'name' => 'JCB',
                ],
                'en-GB' => [
                    'name' => 'JCB',
                ],
            ],
        ],
        self::PAYREXX_AMEX => [
            'translations' => [
                'de-DE' => [
                    'name' => 'American Express',
                ],
                'en-GB' => [
                    'name' => 'American Express',
                ],
            ],
        ],
        self::PAYREXX_WIR => [
            'translations' => [
                'de-DE' => [
                    'name' => 'WIRpay',
                ],
                'en-GB' => [
                    'name' => 'WIRpay',
                ],
            ],
        ],
        self::PAYREXX_PAYPAL => [
            'translations' => [
                'de-DE' => [
                    'name' => 'PayPal',
                ],
                'en-GB' => [
                    'name' => 'PayPal',
                ],
            ],
        ],
        self::PAYREXX_BITCOIN => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Bitcoin',
                ],
                'en-GB' => [
                    'name' => 'Bitcoin',
                ],
            ],
        ],
        self::PAYREXX_AIRPLUS => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Airplus',
                ],
                'en-GB' => [
                    'name' => 'Airplus',
                ],
            ],
        ],
        self::PAYREXX_BILLPAY => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Billpay',
                ],
                'en-GB' => [
                    'name' => 'Billpay',
                ],
            ],
        ],
        self::PAYREXX_BONUSCARD => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Bonus card',
                ],
                'en-GB' => [
                    'name' => 'Bonus card',
                ],
            ],
        ],
        self::PAYREXX_CASHU => [
            'translations' => [
                'de-DE' => [
                    'name' => 'CashU',
                ],
                'en-GB' => [
                    'name' => 'CashU',
                ],
            ],
        ],
        self::PAYREXX_CB => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Carte Bleue',
                ],
                'en-GB' => [
                    'name' => 'Carte Bleue',
                ],
            ],
        ],
        self::PAYREXX_DINERS => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Diners Club',
                ],
                'en-GB' => [
                    'name' => 'Diners Club',
                ],
            ],
        ],
        self::PAYREXX_DIRECT_DEBIT => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Direct Debit',
                ],
                'en-GB' => [
                    'name' => 'Direct Debit',
                ],
            ],
        ],
        self::PAYREXX_DISCOVER => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Discover',
                ],
                'en-GB' => [
                    'name' => 'Discover',
                ],
            ],
        ],
        self::PAYREXX_ELV => [
            'translations' => [
                'de-DE' => [
                    'name' => 'ELV',
                ],
                'en-GB' => [
                    'name' => 'ELV',
                ],
            ],
        ],
        self::PAYREXX_IDEAL => [
            'translations' => [
                'de-DE' => [
                    'name' => 'iDEAL',
                ],
                'en-GB' => [
                    'name' => 'iDEAL',
                ],
            ],
        ],
        self::PAYREXX_INVOICE => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Invoice',
                ],
                'en-GB' => [
                    'name' => 'Invoice',
                ],
            ],
        ],
        self::PAYREXX_MYONE => [
            'translations' => [
                'de-DE' => [
                    'name' => 'My One',
                ],
                'en-GB' => [
                    'name' => 'My One',
                ],
            ],
        ],
        self::PAYREXX_PAYSAFECARD => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Paysafe Card',
                ],
                'en-GB' => [
                    'name' => 'Paysafe Card',
                ],
            ],
        ],
        self::PAYREXX_SWISSBILLING => [
            'translations' => [
                'de-DE' => [
                    'name' => 'SwissBilling',
                ],
                'en-GB' => [
                    'name' => 'SwissBilling',
                ],
            ],
        ],
        self::PAYREXX_TWINT => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Twint',
                ],
                'en-GB' => [
                    'name' => 'Twint',
                ],
            ],
        ],
        self::PAYREXX_VIACASH => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Barzahlen',
                ],
                'en-GB' => [
                    'name' => 'Viacash',
                ],
            ],
        ],
        self::PAYREXX_BANCONTANCT => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Bancontact',
                ],
                'en-GB' => [
                    'name' => 'Bancontact',
                ],
            ],
        ],
        self::PAYREXX_GIROPAY => [
            'translations' => [
                'de-DE' => [
                    'name' => 'GiroPay',
                ],
                'en-GB' => [
                    'name' => 'GiroPay',
                ],
            ],
        ],
        self::PAYREXX_EPS => [
            'translations' => [
                'de-DE' => [
                    'name' => 'EPS',
                ],
                'en-GB' => [
                    'name' => 'EPS',
                ],
            ],
        ],
        self::PAYREXX_GOOGLE_PAY => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Google Pay',
                ],
                'en-GB' => [
                    'name' => 'Google Pay',
                ],
            ],
        ],
        self::PAYREXX_KLARNA_PAYNOW => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Klarna Sofortüberweisung',
                ],
                'en-GB' => [
                    'name' => 'Klarna Pay Now',
                ],
            ],
        ],
        self::PAYREXX_KLARNA_PAYLATER => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Klarna Rechnung',
                ],
                'en-GB' => [
                    'name' => 'Klarna Pay Later',
                ],
            ],
        ],
        self::PAYREXX_ONEY => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Oney',
                ],
                'en-GB' => [
                    'name' => 'Oney',
                ],
            ],
        ],
        self::PAYREXX_BOB_INVOICE => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Bob Invoice',
                ],
                'en-GB' => [
                    'name' => 'Bob Invoice',
                ],
            ]
        ],
        self::PAYREXX_CENTI => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Centi',
                ],
                'en-GB' => [
                    'name' => 'Centi',
                ],
            ],
        ],
        self::PAYREXX_HEIDIPAY => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Heidipay',
                ],
                'en-GB' => [
                    'name' => 'Heidipay',
                ],
            ],
        ],
        self::PAYREXX_REKA => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Reka',
                ],
                'en-GB' => [
                    'name' => 'Reka',
                ],
            ],
        ],
        self::PAYREXX_BANK_TRANSFER => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Kauf auf Rechnung',
                ],
                'en-GB' => [
                    'name' => 'Purchase on invoice',
                ],
            ],
        ],
        self::PAYREXX_KLARNA => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Klarna',
                ],
                'en-GB' => [
                    'name' => 'Klarna',
                ],
            ],
        ],
        self::PAYREXX_POSTFINANCE_PAY => [
            'translations' => [
                'de-DE' => [
                    'name' => 'PostFinance Pay',
                ],
                'en-GB' => [
                    'name' => 'PostFinance Pay',
                ],
            ],
        ],
        self::PAYREXX_PRE_PAYMENT => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Vorkasse',
                ],
                'en-GB' => [
                    'name' => 'Pre-Payment',
                ],
            ],
        ],
        self::PAYREXX_PAY_BY_BANK => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Pay by Bank',
                ],
                'en-GB' => [
                    'name' => 'Pay by Bank',
                ],
            ],
        ],
        self::PAYREXX_POWERPAY => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Powerpay',
                ],
                'en-GB' => [
                    'name' => 'Powerpay',
                ],
            ],
        ],
        self::PAYREXX_CEMBRAPAY => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Kauf auf Rechnung (CembraPay)',
                ],
                'en-GB' => [
                    'name' => 'Purchase on Account (CembraPay)',
                ],
            ],
        ],
        self::PAYREXX_CRYPTO => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Crypto',
                ],
                'en-GB' => [
                    'name' => 'Crypto',
                ],
            ],
        ],
        self::PAYREXX_VERD_CASH => [
            'translations' => [
                'de-DE' => [
                    'name' => 'VERD.Cash',
                ],
                'en-GB' => [
                    'name' => 'VERD.Cash',
                ],
            ],
        ],
        self::PAYREXX_NO_PM => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Zahlungsmittelauswahl nach Weiterleitung zum Zahlungsterminal',
                ],
                'en-GB' => [
                    'name' => 'Payment method selection after forwarding to the payment terminal',
                ],
            ],
        ],
    ];

    private EntityRepository $paymentMethodRepository;
    private PluginIdProvider $pluginIdProvider;

    public function __construct(
        EntityRepository $paymentMethodRepository,
        PluginIdProvider $pluginIdProvider
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->pluginIdProvider        = $pluginIdProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function install(InstallContext $context): void
    {
        foreach (self::PAYMENT_METHODS as $payrexxPaymentMethodIdentifier => $payrexxPaymentMethod) {
            $this->upsertPaymentMethod($context->getContext(), $payrexxPaymentMethod, $payrexxPaymentMethodIdentifier);
        }
    }

    public function uninstall(UninstallContext $context): void
    {
        // Nothing to do, payment methods already deactivated
    }

    public function activate(ActivateContext $context): void
    {
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', PaymentHandler::class));
        $paymentMethods = $this->paymentMethodRepository->searchIds($paymentCriteria, Context::createDefaultContext());

        foreach ($paymentMethods->getIds() as $paymentMethodId) {
            $this->setPaymentMethodIsActive($context->getContext(), $paymentMethodId, true);
        }
    }

    public function deactivate(DeactivateContext $context): void
    {
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', PaymentHandler::class));
        $paymentMethods = $this->paymentMethodRepository->searchIds($paymentCriteria, Context::createDefaultContext());

        foreach ($paymentMethods->getIds() as $paymentMethodId) {
            $this->setPaymentMethodIsActive($context->getContext(), $paymentMethodId, false);
        }
    }

    public function update(UpdateContext $context): void {
        foreach (self::PAYMENT_METHODS as $payrexxPaymentMethodIdentifier => $payrexxPaymentMethod) {
            $this->upsertPaymentMethod($context->getContext(), $payrexxPaymentMethod, $payrexxPaymentMethodIdentifier);
        }
    }

    private function upsertPaymentMethod(Context $context, array $payrexxPaymentMethod, string $payrexxPaymentMethodIdentifier): void
    {
        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass(PayrexxPaymentGatewaySW6::class, $context);

        $technicalName = PaymentHandler::PAYMENT_METHOD_PREFIX . $payrexxPaymentMethodIdentifier;
        $criteria = (new Criteria())
            ->addFilter(
                new OrFilter([
                    new EqualsFilter('technicalName', $technicalName),
                    new AndFilter([
                        new EqualsFilter('handlerIdentifier', PaymentHandler::class),
                        new EqualsFilter('customFields.payrexx_payment_method_name', $technicalName)
                    ])
                ])
            )
            ->setLimit(1);
        $paymentMethods = $this->paymentMethodRepository->search($criteria, Context::createDefaultContext());

        $paymentMethodId = null;
        $paymentMethodActive = false;

        if ($paymentMethods->count()){
            $paymentMethod = $paymentMethods->getEntities()->first();
            $paymentMethodId = $paymentMethod->getId();
            $paymentMethodActive = $paymentMethod->getActive();
        }

        $options = [
            'id' => $paymentMethodId ?? Uuid::randomHex(),
            'handlerIdentifier' => PaymentHandler::class,
            'active' => $paymentMethodActive,
            'pluginId' => $pluginId,
            'technicalName' => $technicalName,
            'customFields' => [
                'payrexx_payment_method_name' => $technicalName,
            ]
        ];

        if (!$paymentMethodId) {
            $options['afterOrderEnabled'] = true;
            $options['name'] = $payrexxPaymentMethod['translations']['en-GB']['name'];
            $options['translations'] = $payrexxPaymentMethod['translations'];
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($options): void {
            $this->paymentMethodRepository->upsert([$options], $context);
        });
    }

    private function setPaymentMethodIsActive(Context $context, $paymentMethodId, bool $active): void
    {
        $paymentMethod = [
            'id' => $paymentMethodId,
            'active' => $active,
        ];
        $this->paymentMethodRepository->update([$paymentMethod], $context);
    }
}
