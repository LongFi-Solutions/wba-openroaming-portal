<?php

namespace App\Form;

use App\DTO\SMSProviderParamDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SMSProviderParamDTO>
 */
class SMSProviderParamType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', HiddenType::class, ['required' => false])
            ->add('paramType', TextType::class, [
                'required' => true,
                'attr' => ['placeholder' => 'e.g. username, userid, handle, from'],
            ])
            ->add('value', TextType::class, [
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SMSProviderParamDTO::class,
        ]);
    }
}
