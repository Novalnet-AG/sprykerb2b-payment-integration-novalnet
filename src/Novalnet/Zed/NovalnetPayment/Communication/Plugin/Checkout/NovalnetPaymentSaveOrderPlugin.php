<?php

namespace Novalnet\Zed\NovalnetPayment\Communication\Plugin\Checkout;

use Generated\Shared\Transfer\CheckoutResponseTransfer;
use Generated\Shared\Transfer\QuoteTransfer;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;
use Spryker\Zed\Payment\Dependency\Plugin\Checkout\CheckoutSaveOrderPluginInterface;

/**
 * @method \Novalnet\Zed\NovalnetPayment\Communication\NovalnetPaymentCommunicationFactory getFactory()
 * @method \Novalnet\Zed\NovalnetPayment\Business\NovalnetPaymentFacadeInterface getFacade()
 * @method \Novalnet\Zed\NovalnetPayment\NovalnetPaymentConfig getConfig()
 * @method \Novalnet\Zed\NovalnetPayment\Persistence\NovalnetPaymentQueryContainerInterface getQueryContainer()
 */
class NovalnetPaymentSaveOrderPlugin extends AbstractPlugin implements CheckoutSaveOrderPluginInterface
{
    /**
     * @api
     *
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     * @param \Generated\Shared\Transfer\CheckoutResponseTransfer $checkoutResponse
     *
     * @return \Generated\Shared\Transfer\CheckoutResponseTransfer
     */
    public function execute(QuoteTransfer $quoteTransfer, CheckoutResponseTransfer $checkoutResponse)
    {
        return $this->getFacade()->saveOrderHook($quoteTransfer, $checkoutResponse);
    }
}
