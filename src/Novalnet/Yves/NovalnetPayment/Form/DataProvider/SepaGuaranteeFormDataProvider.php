<?php

namespace Novalnet\Yves\NovalnetPayment\Form\DataProvider;

use Generated\Shared\Transfer\NovalnetTransfer;
use Generated\Shared\Transfer\PaymentTransfer;
use Novalnet\Yves\NovalnetPayment\Form\SepaGuaranteeSubForm;
use Spryker\Shared\Kernel\Transfer\AbstractTransfer;
use Spryker\Yves\StepEngine\Dependency\Form\StepEngineFormDataProviderInterface;

class SepaGuaranteeFormDataProvider implements StepEngineFormDataProviderInterface
{
    /**
     * @var \Novalnet\Zed\NovalnetPayment\NovalnetPaymentConfig
     */
    protected $config;

    /**
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     *
     * @return \Generated\Shared\Transfer\QuoteTransfer
     */
    public function getData(AbstractTransfer $quoteTransfer)
    {
        if ($quoteTransfer->getPayment() === null) {
            $paymentTransfer = new PaymentTransfer();
            $paymentTransfer->setNovalnetSepaGuarantee(new NovalnetTransfer());

            $quoteTransfer->setPayment($paymentTransfer);
        }

        return $quoteTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     *
     * @return array
     */
    public function getOptions(AbstractTransfer $quoteTransfer)
    {
        return [
            SepaGuaranteeSubForm::OPTION_COMPANY => $quoteTransfer->getBillingAddress()->getCompany(),
        ];
    }
}
