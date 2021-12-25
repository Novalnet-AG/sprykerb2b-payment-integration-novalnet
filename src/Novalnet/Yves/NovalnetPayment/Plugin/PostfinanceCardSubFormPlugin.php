<?php

namespace Novalnet\Yves\NovalnetPayment\Plugin;

use Spryker\Yves\Kernel\AbstractPlugin;
use Spryker\Yves\StepEngine\Dependency\Plugin\Form\SubFormPluginInterface;

/**
 * @method \Novalnet\Yves\NovalnetPayment\NovalnetPaymentFactory getFactory()
 */
class PostfinanceCardSubFormPlugin extends AbstractPlugin implements SubFormPluginInterface
{
    /**
     * @return \Novalnet\Yves\NovalnetPayment\Form\PostfinanceCardSubForm
     */
    public function createSubForm()
    {
        return $this->getFactory()->createPostfinanceCardForm();
    }

    /**
     * @return \Spryker\Yves\StepEngine\Dependency\Form\StepEngineFormDataProviderInterface
     */
    public function createSubFormDataProvider()
    {
        return $this->getFactory()->createPostfinanceCardFormDataProvider();
    }
}
