<?php

namespace App\Form\Type;

use App\Entity\Cities;
use App\Entity\Reservacion;
use App\Repository\CitiesRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class CiudadAutocompleteType extends AbstractType {
    public function __construct(private TranslatorInterface $translator, private CitiesRepository $citiesRepository) {
    }

    public function configureOptions(OptionsResolver $resolver): void {
        $resolver->setDefaults([
            'provincia' => null,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        if (isset($options['provincia'])) {
            if (!\is_array($options['provincia'])) {
                $id = $options['provincia'];
            }
            if (!empty($options['provincia']['provincia'])) {
                $id = $options['provincia']['provincia'];
            }
        }
        $builder->add('ciudad', EntityType::class, [
            'class' => Cities::class,
            'label' => $this->translator->trans('Ciudad/Municipio'),
            'placeholder' => '',
            'choice_label' => 'name',
            'autocomplete' => false,

            'choices' => isset($id) ? $this->citiesRepository->createQueryBuilder('e')->setCacheable(true)->where('e.state = :state')->setParameter('state', $id)
                ->orderBy('e.name', 'ASC')->getQuery()->getResult() : [],

            'attr' => [
                'data-controller' => 'autocomplete',
            ],
            'constraints' => [
                new NotBlank(['message' => $this->translator->trans('Este campo es requerido')]),
            ],
            'getter' => function ($value) {
                return $value; // ?->getId();
            },
            'setter' => function (&$parent, $child): void {
                $parent = $child;
            },
        ]);
    }
}
