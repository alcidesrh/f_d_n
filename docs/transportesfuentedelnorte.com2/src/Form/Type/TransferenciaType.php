<?php

// src/Form/Type/ShippingType.php

namespace App\Form\Type;

use App\Entity\ClienteReservacion;
use App\Entity\Reservacion;
use App\Entity\Tarjeta;
use App\Entity\Transferencia;
use App\Form\ClienteReservacionType;
use App\Form\ClienteTransferenciaType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class TransferenciaType extends AbstractType {
    use ValidationTrail;

    public function __construct(private TranslatorInterface $translator, private EntityManagerInterface $entityManagerInterface) {
    }

    public function configureOptions(OptionsResolver $resolver): void {
        $resolver->setDefaults([
            'data_class' => Transferencia::class,
            'allow_extra_fields' => true,
            'transferencia' => null
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
                'data' =>  isset($options['transferencia']) ? number_format($options['transferencia']->getPrecio()) : '',
                // ...$this->getNotBlanckContrain(),
                // 'row_attr' => ['class' => 'hidden'],
                'required' => false,
                // 'disabled' => true,

            ])
            ->add('tarjeta', EntityType::class, [
                'class' => Tarjeta::class,
                'choices' => $this->entityManagerInterface->getRepository(Tarjeta::class)->createQueryBuilder('t')->setCacheable(true)->where('t.activo = :true')->orderBy('t.prioridad', 'desc')->setParameter('true', true)->getQuery()->getResult(),
                'expanded' => true,
                'multiple' => false,
                'data' =>  $this->entityManagerInterface->getRepository(Tarjeta::class)->findOneBy(['codigo' => '001']),
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
                // 'data' => $options['reservacion']->getMoneda(),

                ...$this->getNotBlanckContrain(),
            ])
            ->add('numero', TextType::class, [
                'label' => $this->translator->trans('Número de la tarjeta'),
                'mapped' => false,
                ...$this->getNotBlanckContrain(),
                'attr' => [
                    'id' => 'pago_datos_numero',
                    'class' => 'pago_datos_numero'
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
                    'class' => 'pago_datos_codigo_seguridad'
                ],
            ])
            ->add('cliente', ClienteTransferenciaType::class, ['data_class' => ClienteReservacion::class]);
    }
}
