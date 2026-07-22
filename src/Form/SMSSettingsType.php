<?php

namespace App\Form;

use App\DTO\SMSSettingsDTO;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SMSSettingsDTO>
 */
class SMSSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $disabled = $options['disabled'];

        // Use libphonenumber to fetch all supported regions
        $phoneUtil = PhoneNumberUtil::getInstance();
        $regions = $phoneUtil->getSupportedRegions();

        // Build a choices array: e.g. ['Portugal (+351)' => 'PT']
        $choices = [];
        foreach ($regions as $regionCode) {
            $countryCode = $phoneUtil->getCountryCodeForRegion($regionCode);
            $choices[sprintf('%s (+%d)', $regionCode, $countryCode)] = $regionCode;
        }

        $builder
            ->add('defaultRegionPhoneInputs', ChoiceType::class, [
                'choices' => $choices,
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'autocomplete' => true,
                'disabled' => $disabled,
            ])
            ->add('timeIntervalBetweenRequests', IntegerType::class, [
                'required' => false,
                'disabled' => $disabled,
            ])
            ->add('timeIntervalToResetAttempts', IntegerType::class, [
                'required' => false,
                'disabled' => $disabled,
            ])
            ->add('attemptsNumber', IntegerType::class, [
                'required' => false,
                'disabled' => $disabled,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SMSSettingsDTO::class,
            'disabled' => true,
        ]);
    }
}
