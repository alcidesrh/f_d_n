<?php

// src/Components/ButtonLinkComponent.php
namespace App\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('button')]
class ButtonComponent {

    public string $text;

    public array $icon;

    public array $attr;

    public string|null $class = null;
}
