<?php

namespace Novalnet\Shared\NovalnetPayment;

/**
 * Declares global environment configuration keys. Do not use it for other class constants.
 */
interface NovalnetConstants
{
    public const PROVIDER_NAME = 'Novalnet';
    public const LAST_NAME_FOR_INVALID_TEST = 'Invalid';

    public const NOVALNET = 'NOVALNET';
    public const NOVALNET_CREDENTIALS_SIGNATURE = 'NOVALNET_CREDENTIALS_SIGNATURE';
    public const NOVALNET_CREDENTIALS_TARIFF = 'NOVALNET_CREDENTIALS_TARIFF';
    public const NOVALNET_CREDENTIALS_ACCESS_KEY = 'NOVALNET_CREDENTIALS_ACCESS_KEY';
    public const NOVALNET_SANDBOX_MODE = 'NOVALNET_SANDBOX_MODE';
    public const NOVALNET_REDIRECT_SUCCESS_URL = 'NOVALNET_REDIRECT_SUCCESS_URL';
    public const NOVALNET_REDIRECT_SUCCESS_METHOD = 'NOVALNET_REDIRECT_SUCCESS_METHOD';
    public const NOVALNET_REDIRECT_ERROR_URL = 'NOVALNET_REDIRECT_ERROR_URL';
    public const NOVALNET_REDIRECT_ERROR_METHOD = 'NOVALNET_REDIRECT_ERROR_METHOD';   

    public const NOVALNET_CREDIT_CARD_FORM_CLIENT_KEY = 'NOVALNET_CREDIT_CARD_FORM_CLIENT_KEY';
    public const NOVALNET_CREDIT_CARD_FORM_INLINE = 'NOVALNET_CREDIT_CARD_FORM_INLINE';
    public const NOVALNET_CREDIT_CARD_FORM_STYLE_CONTAINER = 'NOVALNET_CREDIT_CARD_FORM_STYLE_CONTAINER';
    public const NOVALNET_CREDIT_CARD_FORM_STYLE_INPUT = 'NOVALNET_CREDIT_CARD_FORM_STYLE_INPUT';
    public const NOVALNET_CREDIT_CARD_FORM_STYLE_LABEL = 'NOVALNET_CREDIT_CARD_FORM_STYLE_LABEL';

    public const NOVALNET_INVOICE_DUE_DATE = 'NOVALNET_INVOICE_DUE_DATE';
    public const NOVALNET_SEPA_DUE_DATE = 'NOVALNET_SEPA_DUE_DATE';
    public const NOVALNET_BARZAHLEN_SLIP_EXPIRY_DATE = 'NOVALNET_BARZAHLEN_SLIP_EXPIRY_DATE';

    public const NOVALNET_CREDIT_CARD_ONHOLD_AMOUNT_LIMIT = 'NOVALNET_CREDIT_CARD_ONHOLD_AMOUNT_LIMIT';
    public const NOVALNET_INVOICE_ONHOLD_AMOUNT_LIMIT = 'NOVALNET_INVOICE_ONHOLD_AMOUNT_LIMIT';
    public const NOVALNET_SEPA_ONHOLD_AMOUNT_LIMIT = 'NOVALNET_SEPA_ONHOLD_AMOUNT_LIMIT';
    public const NOVALNET_PAYPAL_ONHOLD_AMOUNT_LIMIT = 'NOVALNET_PAYPAL_ONHOLD_AMOUNT_LIMIT';

    public const NOVALNET_CALLBACK = 'NOVALNET_CALLBACK';
    public const NOVALNET_CALLBACK_DEBUG_MODE = 'NOVALNET_CALLBACK_DEBUG_MODE';
    public const NOVALNET_CALLBACK_EMAIL_TO_ADDRESS = 'NOVALNET_CALLBACK_EMAIL_TO_ADDRESS';
}
