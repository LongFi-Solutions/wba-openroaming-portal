<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\AccessPointDTO;
use App\Entity\Network;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<AccessPointDTO>
 */

class CreateAccessPointType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: AP-Piso1-SalaA',
                    'class' => 'form-input block w-full rounded-md border-gray-300 shadow-sm'
                ]
            ])
            ->add('ssid', TextType::class, [
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: Empresa_Guest_WiFI',
                    'class' => 'form-input block w-full rounded-md border-gray-300 shadow-sm'
                ]
            ])
            ->add('macAddress', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: AA:BB:CC:DD:EE:FF',
                    'class' => 'form-input block w-full rounded-md border-gray-300 shadow-sm'
                ]
            ])
            ->add('vendor', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: Ubiquiti, Cisco, TP-Link',
                    'class' => 'form-input block w-full rounded-md border-gray-300 shadow-sm'
                ]
            ])
            ->add('model', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: UniFi AP AC Pro',
                    'class' => 'form-input block w-full rounded-md border-gray-300 shadow-sm'
                ]
            ])
            ->add('standard', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: 802.11ax (Wi-Fi 6)',
                    'class' => 'form-input block w-full rounded-md border-gray-300 shadow-sm'
                ]
            ])
            ->add('serialNumber', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: SN1234567890',
                    'class' => 'form-input block w-full rounded-md border-gray-300 shadow-sm'
                ]
            ])

            ->add('latitude', NumberType::class, [
                'label' => 'Latitude',
                'scale' => 7,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: 41.149612',
                    'class' => 'form-input block w-full rounded-md border-gray-300 shadow-sm'
                ]
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'Longitude',
                'scale' => 7,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: -8.611014',
                    'class' => 'form-input block w-full rounded-md border-gray-300 shadow-sm'
                ]
            ])
            ->add('altitudeMsl', NumberType::class, [
                'scale' => 2,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: 120.5',
                    'class' => 'form-input block w-full rounded-md border-gray-300 shadow-sm'
                ]
            ])
            ->add('altitudeAgl', NumberType::class, [
                'scale' => 2,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: 3.5',
                    'class' => 'form-input block w-full rounded-md border-gray-300 shadow-sm'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AccessPointDTO::class,
        ]);
    }
}
