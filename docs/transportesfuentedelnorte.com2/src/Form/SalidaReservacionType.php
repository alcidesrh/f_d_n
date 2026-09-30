<?php

namespace App\Form;

use App\Entity\Empresa;
use App\Entity\SalidaReservacion;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class SalidaReservacionType extends AbstractType {
    public function __construct(private TranslatorInterface $translator) {
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void {


        $builder
            ->add('salida_fecha', DateType::class, [
                'widget' => 'single_text',
                'html5' => false,
                'format' => 'en' == $this->translator->getLocale() ? 'yyyy-MM-dd' : 'dd/MM/yyyy',
                'label' => false,
                'constraints' => [
                    new NotBlank(['message' => $this->translator->trans('La fecha no puede estar en vacía')])
                ]
            ])
            ->add(
                'empresa',
                EntityType::class,
                [
                    'class' => Empresa::class,
                    'choice_value' => 'empresa_id',
                    'attr' => [
                        'data-buscar-salida-target' => 'empresa',
                    ],
                    'row_attr' => ['class' => 'hidden']
                ]
            )

            ->add('hora', HiddenType::class, [
                'attr' => [
                    'data-buscar-salida-target' => 'hora',
                ],
            ])
            ->add('bus_clase', HiddenType::class, [
                'attr' => [
                    'data-buscar-salida-target' => 'bus_clase',
                ]
            ])
            ->add('minutos', HiddenType::class, [
                'attr' => [
                    'data-buscar-salida-target' => 'minutos',
                ]
            ])
            ->add('salida_id', HiddenType::class, [
                'attr' => [
                    'data-buscar-salida-target' => 'salida_id',
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => false,
                'attr' => ['data-buscar-salida-target' => 'buscar_salidas_submit', 'class' => 'hidden'],
            ]);

        if (!empty($options['ida_vuelta'])) {
            $builder->add('ida_vuelta', HiddenType::class, [
                'mapped' => false,
                'data' => 1,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void {
        $resolver->setDefaults([
            'data_class' => SalidaReservacion::class,
            'ida_vuelta' => null,
        ]);
    }
}
