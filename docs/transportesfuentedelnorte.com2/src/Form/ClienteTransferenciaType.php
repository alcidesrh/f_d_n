<?php

namespace App\Form;

use App\Entity\ClienteReservacion;
use App\Entity\Countries;
use App\Form\Type\CiudadAutocompleteType;
use App\Form\Type\ProvinciaAutocompleteType;
use App\Form\Type\ValidationTrail;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ClienteTransferenciaType extends AbstractType {

    use ValidationTrail;

    public function __construct(private RequestStack $requestStack, private EntityManagerInterface $entityManagerInterface, private TranslatorInterface $translator, private Environment $twig) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $builder
            ->add('nombre', TextType::class, [
                'label' => $this->translator->trans('Nombre'),
                ...$this->getNotBlanckContrain(),

            ])
            ->add('apellido', TextType::class, [
                'label' => $this->translator->trans('Apellido'),
                ...$this->getNotBlanckContrain(),
            ])
            ->add('telefono', TelType::class, [
                'label' => 'Teléfono',
                'constraints' => [
                    new Assert\Callback(function ($value, ExecutionContextInterface $context, $payload) {
                    }),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                ...$this->getNotBlanckContrain(),
            ])
            // ->add('nit', TextType::class, [
            //     'label' => false,
            //     'required' => false,
            //     'attr' => [
            //         'data-pagar-target' => 'nit'
            //     ]
            // ])

            ->add('direccion', TextType::class, [
                'label' => $this->translator->trans('Dirección'),
                ...$this->getNotBlanckContrain(),
                'attr' => [
                    'minlength' => 5,
                ],
            ])
            // ->add('codigo_postal', TextType::class, [
            //     'label' => $this->translator->trans('Código postal'),
            //     'required' => false,
            // ])
            ->add('pais', EntityType::class, [
                'class' => Countries::class,
                'label' => $this->translator->trans('País'),
                'autocomplete' => false,
                'choice_label' => function (Countries $pais) {
                    $nombres = $pais->getTranslations();
                    if (isset($nombres[$this->translator->getLocale()])) {
                        $label = $nombres[$this->translator->getLocale()];
                    } else {
                        $label = $pais->getName();
                    }
                    if ($emoji = $pais->getEmoji()) {
                        return $emoji . ' ' . $label;
                    }

                    return $label;
                },
                'preferred_choices' => $this->entityManagerInterface->getRepository(Countries::class)->createQueryBuilder('t')->setCacheable(true)->where("t.iso3 = 'GTM' or t.iso3 = 'USA'")->orderBy('t.id', 'es' == $this->translator->getLocale() ? 'asc' : 'desc')->getQuery()->getResult(),
                'placeholder' => $this->translator->trans('Seleccione país'),
                'attr' => [
                    'data-action' => 'autocomplete#provincia',
                    'data-controller' => 'autocomplete',
                ],
                ...$this->getNotBlanckContrain(),
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $event->getData();
            if (isset($data['pais']) && !empty($data['pais'])) {
                $form->add('provincia', ProvinciaAutocompleteType::class, [
                    'label' => false,
                    'pais' => $data['pais'],
                ]);
            }
            if (isset($data['provincia'])) {
                $form->add('ciudad', CiudadAutocompleteType::class, [
                    'label' => false,
                    'provincia' => $data['provincia'],
                ]);
            }
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();
            if ($data) {
                $form
                    ->add('provincia', ProvinciaAutocompleteType::class, [
                        'label' => false,
                        'pais' => $data->getPais()?->getId(),
                    ])
                    ->add('ciudad', CiudadAutocompleteType::class, [
                        'label' => false,
                        'provincia' => $data->getProvincia()?->getId(),
                    ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void {
        $resolver->setDefaults([
            'data_class' => ClienteReservacion::class,
            'allow_extra_fields' => true,
            'pais' => null,
            'red' => false,
            'constraints' => [
                new NotBlank(['message' => $this->translator->trans('Este campo es requerido')]),
            ],
        ]);
    }
}
