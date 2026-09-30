<?php

namespace App\Form;

use App\Entity\Contacto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContactoType extends AbstractType {
    public function __construct(private TranslatorInterface $translator) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $builder
            ->add('nombre', TextType::class, [
                'attr' => ['class' => 'mb-5']
            ])
            ->add('email', EmailType::class, [
                'constraints' => [
                    new Email()
                ],
                'attr' => ['class' => 'mb-5']

            ])
            ->add('texto')
            ->add('submit', SubmitType::class, [
                'label' => $this->translator->trans('Enviar'),
                'attr' => ['class' => 'btn mx-auto my-5 ']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void {
        $resolver->setDefaults([
            'data_class' => Contacto::class,
        ]);
    }
}
