<?php

// src/Components/ButtonLinkComponent.php
namespace App\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('button_link')]
class ButtonLinkComponent
{

    public string $text;

    public string $link;

    public array $icon;

    public string $attr = '';

    public string $class = '';
}
