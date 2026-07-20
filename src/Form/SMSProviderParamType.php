<?php

namespace App\Form;

use App\DTO\SMSProviderParamDTO;
use App\Enum\ParamType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
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
            ->add('type', EnumType::class, [
                'class' => ParamType::class,
                'label' => false,
                'placeholder' => 'selectType',
                'choice_label' => fn(ParamType $type) => $type->value,
            ])
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
