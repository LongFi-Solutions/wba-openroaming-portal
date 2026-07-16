<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\MapSettingsDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

/**
 * @extends AbstractType<MapSettingsDTO>
 */
class MapSettingsType extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('latitude', NumberType::class, [
                'label' => 'Latitude',
                'scale' => 7,
                'attr' => [
                    'placeholder' => 'Ex: 41.149612',
                    'class' => 'form-input block w-full rounded-md border-gray-300 shadow-sm'
                ]
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'Longitude',
                'scale' => 7,
                'attr' => [
                    'placeholder' => 'Ex: -8.611014',
                    'class' => 'form-input block w-full rounded-md border-gray-300 shadow-sm'
                ]
            ])
            ->add('zoom', IntegerType::class, [
                'label' => 'Zoom',
                'attr' => [
                    'min' => 0,
                    'max' => 19,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MapSettingsDTO::class,
        ]);
    }
}
