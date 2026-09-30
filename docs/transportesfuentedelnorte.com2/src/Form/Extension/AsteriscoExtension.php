<?php

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class AsteriscoExtension extends AbstractTypeExtension {
    /**
     * Returns an array of extended types.
     */
    public static function getExtendedTypes(): iterable {
        return [TextType::class, TextareaType::class, ChoiceType::class, EmailType::class];
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void {
        if (isset($view->vars['required']) && $view->vars['required']) {
            $view->vars['label_attr'] = array_merge($view->vars['label_attr'], ['class' => 'asteric']);
        }
    }
}
