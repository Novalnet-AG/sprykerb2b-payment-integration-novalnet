<?php

namespace Novalnet\Yves\NovalnetPayment\Form;

use Generated\Shared\Transfer\NovalnetTransfer;
use Novalnet\Shared\NovalnetPayment\NovalnetConfig;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class SepaGuaranteeSubForm extends AbstractSubForm
{
    protected const PAYMENT_METHOD = 'sepa-guarantee';

    protected const NOVALNET_PAYMENT = 'NovalnetPayment';

    public const OPTION_COMPANY = 'company';

    protected const FIELD_IBAN = 'iban';

    protected const IBAN_LABEL = 'IBAN';

    protected const VALIDATION_IBAN_MESSAGE = 'please enter valid iban';

    /**
     * @return string
     */
    public function getName()
    {
        return NovalnetConfig::NOVALNET_PAYMENT_METHOD_SEPA_GUARANTEE;
    }

    /**
     * @return string
     */
    public function getPropertyPath()
    {
        return NovalnetConfig::NOVALNET_PAYMENT_METHOD_SEPA_GUARANTEE;
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
        $this->addIBAN($builder);

        // Add custom data (language)
        $this->addCompanyData($builder, $options);
    }

    /**
     * @param \Symfony\Component\Form\FormView $view The view
     * @param \Symfony\Component\Form\FormInterface $form The form
     * @param array $options The options
     *
     * @return void
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $selectedOptions = $options[static::OPTIONS_FIELD_NAME];
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

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     *
     * @return $this
     */
    protected function addIBAN(FormBuilderInterface $builder)
    {
        $builder->add(
            self::FIELD_IBAN,
            TextType::class,
            [
                'label' => static::IBAN_LABEL,
                'required' => true,
                'constraints' => [
                    $this->createNotBlankConstraint(),
                    $this->createIbanValidationConstraint(),
                ],
            ]
        );

        return $this;
    }

    /**
     * @return \Symfony\Component\Validator\Constraint
     */
    protected function createNotBlankConstraint()
    {
        return new NotBlank(['groups' => $this->getPropertyPath()]);
    }

    /**
     * @return \Symfony\Component\Validator\Constraint
     */
    protected function createIbanValidationConstraint()
    {
        return new Regex([
            'pattern' => '/^(?:[a-z]{2}[0-9]+$)/i',
            'message' => static::VALIDATION_IBAN_MESSAGE,
            'groups' => $this->getPropertyPath(),
        ]);
    }
}
