<?php

namespace App\Form;

use App\DTO\SMSProviderDTO;
use App\Enum\SettingName;
use App\Enum\SMSProviderType as SMSProviderTypeEnum;
use App\Repository\SettingRepository;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SMSProviderDTO>
 */
class SMSProviderType extends AbstractType
{
    public function __construct(
        private readonly SettingRepository $settingRepository
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Fetch the setting from the database
        $regionsSetting = $this->settingRepository->findOneBy(
            ['name' => SettingName::DEFAULT_REGION_PHONE_INPUTS->value]
        );

        // If the setting exists, explode and trim; otherwise use a default
        $regionInputs = $regionsSetting && $regionsSetting->getValue()
            ? array_map(trim(...), explode(',', $regionsSetting->getValue()))
            : ['PT', 'US', 'GB']; // fallback default

        $builder
            ->add('name', TextType::class, ['required' => true])
            ->add('smsProviderType', EnumType::class, [
                'class' => SMSProviderTypeEnum::class,
                'choice_label' => static fn (SMSProviderTypeEnum $type): string => ucfirst(strtolower($type->name)),
                'placeholder' => false,
                'required' => true,
            ])
            ->add('testMode', CheckboxType::class, [
                'required' => false,
                'label' => 'testMode',
            ])
            // BudgetSMS fields — always present in the form tree (so submission/validation
            // works regardless of which type is visually selected), shown/hidden client-side
            // by the sms-provider-type Stimulus controller based on smsProviderType's value.
            // A future provider type adds its own fields here the same way.
            ->add('username', TextType::class, ['required' => false])
            ->add('userid', TextType::class, ['required' => false])
            ->add('handle', TextType::class, ['required' => false])
            ->add('from', TextType::class, ['required' => false])
            ->add('testPhoneNumber', PhoneNumberType::class, [
                'label' => 'Phone Number',
                'default_region' => $regionInputs[0],
                'format' => PhoneNumberFormat::INTERNATIONAL,
                'widget' => PhoneNumberType::WIDGET_COUNTRY_CHOICE,
                'preferred_country_choices' => $regionInputs,
                'country_display_emoji_flag' => true,
                'required' => false,
                'mapped' => false,
                'attr' => ['autocomplete' => 'tel'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SMSProviderDTO::class,
        ]);
    }
}
