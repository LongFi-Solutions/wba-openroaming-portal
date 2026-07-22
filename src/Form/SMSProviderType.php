<?php

namespace App\Form;

use App\DTO\SMSProviderDTO;
use App\Enum\SMSProviderType as SMSProviderTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SMSProviderDTO>
 */
class SMSProviderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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
            // Dynamic list of (paramType, value) rows — allow_add/allow_delete so a provider
            // isn't limited to a fixed set of credential fields. The prototype + JS wiring for
            // adding/removing rows in the UI is part of the twig/design pass, not this step.
            ->add('params', CollectionType::class, [
                'entry_type' => SMSProviderParamType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'label' => false,
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
