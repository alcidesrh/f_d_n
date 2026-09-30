<?php

namespace App\Form\Type;

use Symfony\Component\Validator\Constraints\NotBlank;

trait ValidationTrail
{
    public function getNotBlanckContrain()
    {
        return [
            'constraints' => [
                new NotBlank(['message' => $this->translator->trans('Este campo es requerido')]),
            ],
        ];
    }
}
