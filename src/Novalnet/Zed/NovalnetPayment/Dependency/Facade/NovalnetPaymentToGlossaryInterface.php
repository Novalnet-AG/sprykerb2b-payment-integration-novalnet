<?php

namespace Novalnet\Zed\NovalnetPayment\Dependency\Facade;

use Generated\Shared\Transfer\LocaleTransfer;

interface NovalnetPaymentToGlossaryInterface
{
    /**
     * @param string $keyName
     * @param array $data
     * @param \Generated\Shared\Transfer\LocaleTransfer|null $localeTransfer
     *
     * @return string
     */
    public function translate($keyName, array $data = [], ?LocaleTransfer $localeTransfer = null);
}
