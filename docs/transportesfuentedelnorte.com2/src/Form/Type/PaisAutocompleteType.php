<?php

// src/Form/Type/ShippingType.php

namespace App\Form\Type;

use App\Entity\Countries;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class PaisAutocompleteType extends AbstractType {
    public function __construct(private TranslatorInterface $translator, private EntityManagerInterface $entityManagerInterface) {
    }

    public function configureOptions(OptionsResolver $resolver): void {
        $resolver->setDefaults([]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $builder->add('pais', EntityType::class, [
            'class' => Countries::class,
            'inherit_data' => true,
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
            'preferred_choices' => ['', ...$this->entityManagerInterface->getRepository(Countries::class)->createQueryBuilder('t')->setCacheable(true)->where("t.iso3 = 'GTM' or t.iso3 = 'USA'")->orderBy('t.id', 'es' == $this->translator->getLocale() ? 'asc' : 'desc')->getQuery()->getResult()],
            'placeholder' => $this->translator->trans('Seleccione país'),
            'attr' => [
                'data-action' => 'autocomplete#provincia',
                'data-controller' => 'autocomplete',
            ],
            'required' => false,
        ]);
    }
}
