<?php

// src/Form/Type/ShippingType.php

namespace App\Form\Type;

use App\Entity\ClienteReservacion;
use App\Entity\Reservacion;
use App\Entity\Tarjeta;
use App\Form\ClienteReservacionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class PagoDatosType extends AbstractType {
    use ValidationTrail;

    public function __construct(private TranslatorInterface $translator, private EntityManagerInterface $entityManagerInterface) {
    }

    public function configureOptions(OptionsResolver $resolver): void {
        $resolver->setDefaults([
            'reservacion' => null,
            'data_class' => Reservacion::class,
            'allow_extra_fields' => true,
            // 'required' => false,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $años = [];
        for ($i = intval(date('Y')); $i < intval(date('Y')) + 20; ++$i) {
            $años[$i] = $i;
        }

        $builder
            ->add('precio_visual', TextType::class, [
                'mapped' => false,
                'label' => $this->translator->trans('Total a pagar'),
                'data' => number_format($options['reservacion']->getPrecioCobrar(), 2, '.', ','),
                // ...$this->getNotBlanckContrain(),
                'row_attr' => ['class' => 'hidden'],
                'disabled' => true,
                'required' => false,
            ])
            ->add('tarjeta', EntityType::class, [
                'class' => Tarjeta::class,
                'choices' => $this->entityManagerInterface->getRepository(Tarjeta::class)->createQueryBuilder('t')->setCacheable(true)->where('t.activo = :true')->orderBy('t.prioridad', 'desc')->setParameter('true', true)->getQuery()->getResult(),
                'expanded' => true,
                'multiple' => false,
                'data' => $options['reservacion']->getTarjeta() ?? $this->entityManagerInterface->getRepository(Tarjeta::class)->findOneBy(['codigo' => '001']),
                'label' => false, //$this->translator->trans('Tipo de tarjeta'),
                'choice_label' => 'nombre',
                ...$this->getNotBlanckContrain(),
            ])
            ->add('moneda', ChoiceType::class, [
                'choices' => 'es' == $this->translator->getLocale() ? [
                    Reservacion::MONEDA_GTQ => Reservacion::MONEDA_GTQ,
                    Reservacion::MONEDA_USD => Reservacion::MONEDA_USD,
                ] : [
                    Reservacion::MONEDA_USD => Reservacion::MONEDA_USD,
                    Reservacion::MONEDA_GTQ => Reservacion::MONEDA_GTQ,
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => $options['reservacion']->getMoneda(),
                'choice_attr' => 'es' == $this->translator->getLocale() ? [
                    Reservacion::MONEDA_GTQ => ['data-action' => 'click->pagar#moneda', 'data-precio' => number_format($options['reservacion']->getPrecio(), 2, '.', ',')],
                    Reservacion::MONEDA_USD => ['data-action' => 'click->pagar#moneda', 'data-precio' => number_format($options['reservacion']->getPrecioDolar(), 2, '.', ',')],
                ] : [
                    Reservacion::MONEDA_USD => ['data-action' => 'click->pagar#moneda', 'data-precio' => number_format($options['reservacion']->getPrecioDolar(), 2, '.', ',')],
                    Reservacion::MONEDA_GTQ => ['data-action' => 'click->pagar#moneda', 'data-precio' => number_format($options['reservacion']->getPrecio(), 2, '.', ',')],
                ],
                ...$this->getNotBlanckContrain(),
            ])
            ->add('numero', TextType::class, [
                'label' => $this->translator->trans('Número de la tarjeta'),
                'mapped' => false,
                ...$this->getNotBlanckContrain(),
                'attr' => [
                    'id' => 'pago_datos_numero',
                ],
            ])
            ->add('expira_mes', ChoiceType::class, [
                'label' => $this->translator->trans('Mes que expira'),
                'mapped' => false,
                'choices' => ['01' => '01', '02' => '02', '03' => '03', '04' => '04', '05' => '05', '06' => '06', '07' => '07', '08' => '08', '09' => '09', '10' => '10', '11' => '11', '12' => '12'],
                'data' => '01',
                ...$this->getNotBlanckContrain(),
            ])
            ->add('expira_year', ChoiceType::class, [
                'label' => $this->translator->trans('Año que expira'),
                'mapped' => false,
                'choices' => $años,
                ...$this->getNotBlanckContrain(),
            ])
            ->add('codigo_seguridad', TextType::class, [
                'label' => 'CSC/CVV',
                'mapped' => false,
                ...$this->getNotBlanckContrain(),
                'attr' => [
                    'id' => 'pago_datos_codigo_seguridad',
                ]
            ])
            ->add('cliente', ClienteReservacionType::class, ['data_class' => ClienteReservacion::class])
            ->add('notaClienteSalida', TextareaType::class, [
                'mapped' => false,
                'label' => false, //$this->translator->trans('Total a pagar'),
                'data' => $options['reservacion']?->getSalida()?->getClienteNota(),
                'required' => false,
            ]);
        if ($regreso = $options['reservacion']?->getRegreso()) {
            $builder->add('notaClienteRegreso', TextareaType::class, [
                'mapped' => false,
                'label' => false, //$this->translator->trans('Total a pagar'),
                'data' => $regreso->getClienteNota(),
                'required' => false,
            ]);
        }
    }
}
