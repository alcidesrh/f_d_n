<?php

// src/Components/ButtonLinkComponent.php

namespace App\Components;

use App\Services\ReservacionService;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('asiento')]
class AsientoComponent {
    public string $color = 'gray';

    public string $coordX;

    public string $coordY;

    public bool $asiento = true;

    public bool $chofer = true;

    public int $numero;

    public int $id;

    public bool $ocupado = false;

    public bool $elegido = true;

    public bool|null $regreso = false;

    public function mount(array $data) {

        if (isset($data['conductor_puerta'])) {
            $this->asiento = false;
            if (1 == $data['conductor_puerta']) {
                $this->chofer = false;
            }
        } else {
            if (ReservacionService::validarAsientoSistemaOcupado($data)) {
                $this->color = '#EF4443';
                $this->ocupado = true;
            }
            $this->numero = $data['numero'];
            $this->id = $data['id'];
            $this->elegido = isset($data['elegido']);
        }

        $this->coordX = ($data['coordenadaX'] * 1.32) . 'px';

        $this->coordY = ($data['coordenadaY'] * 1.32) . 'px';
    }
}
