<?php

namespace App\Form;

use App\Entity\Estacion;
use App\Entity\RutaReservacion;
use App\Repository\EstacionRepository;
use App\Repository\RutaReservacionRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RutaReservacionType extends AbstractType {
    public function __construct(private EstacionRepository $er, private RutaReservacionRepository $rutaReservacionRepository) {
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $select_options = [
            'class' => Estacion::class,
            'query_builder' => $this->er->getEstacionesSelect(),
            'choice_label' => 'nombre',
            'choice_value' => function (?Estacion $entity) {
                return $entity ? $entity->getEstacionId() : '';
            },
            'group_by' => function (?Estacion $entity) {
                return $entity->getDepartamento()->getNombre();
            },
            'label' => false,
        ];

        $builder
            ->add('estacion_salida', EntityType::class, [...$select_options, 'placeholder' => 'Desde'])
            ->add('estacion_llegada', EntityType::class, [...$select_options, 'placeholder' => 'Hacia'])
            ->add(
                'ida_vuelta',
                ChoiceType::class,
                [
                    'label' => false,
                    'choices' => [
                        'Solo ida' => 0,
                        'Ida y vuelta' => 1,
                    ],
                    'expanded' => true,
                    'data' => $options['reservacion']?->isIdaVuelta() ?? 0,
                    'mapped' => false
                ]
            )->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
                $ruta = $event->getData();
                if ($ruta = $this->rutaReservacionRepository->findOneBy([
                    'estacion_salida' => $ruta->getEstacionSalida(),
                    'estacion_llegada' => $ruta->getEstacionLlegada()
                ])) {
                    $event->setData($ruta);
                }
            });
    }

    public function configureOptions(OptionsResolver $resolver): void {
        $resolver->setDefaults([
            'data_class' => RutaReservacion::class,
            'reservacion' => null
        ]);
    }
}
