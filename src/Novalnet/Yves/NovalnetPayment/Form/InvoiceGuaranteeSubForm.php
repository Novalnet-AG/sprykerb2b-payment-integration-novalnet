<?php

namespace Novalnet\Yves\NovalnetPayment\Form;

use Generated\Shared\Transfer\NovalnetTransfer;
use Novalnet\Shared\NovalnetPayment\NovalnetConfig;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class InvoiceGuaranteeSubForm extends AbstractSubForm
{
    protected const PAYMENT_METHOD = 'invoice-guarantee';

    protected const NOVALNET_PAYMENT = 'NovalnetPayment';

    public const OPTION_COMPANY = 'company';

    /**
     * @return string
     */
    public function getName()
    {
        return NovalnetConfig::NOVALNET_PAYMENT_METHOD_INVOICE_GUARANTEE;
    }

    /**
     * @return string
     */
    public function getPropertyPath()
    {
        return NovalnetConfig::NOVALNET_PAYMENT_METHOD_INVOICE_GUARANTEE;
    }

    /**
     * @return string
     */
    public function getTemplatePath()
    {
        return static::NOVALNET_PAYMENT . DIRECTORY_SEPARATOR . static::PAYMENT_METHOD;
    }

    /**
     * @param \Symfony\Component\OptionsResolver\OptionsResolverInterface $resolver
     *
     * @return void
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $this->configureOptions($resolver);
    }

    /**
     * @param \Symfony\Component\OptionsResolver\OptionsResolver $resolver
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => NovalnetTransfer::class,
        ])->setRequired(self::OPTIONS_FIELD_NAME);
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array $options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        // Add custom data (language)
        $this->addCompanyData($builder, $options);
    }

    /**
     * @return void
     */
    protected function addCompanyData(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            self::OPTION_COMPANY,
            HiddenType::class,
            [
                'label' => false,
                'data' => $options[self::OPTIONS_FIELD_NAME][self::OPTION_COMPANY],
            ]
        );
    }
}
