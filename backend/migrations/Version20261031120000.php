<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Venta\CifradoCredenciales;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Configuración de Forcon y Cybersource en la base (ADR-047): crea
 * `configuracion_forcon` y `configuracion_cybersource` (una fila cada una) y
 * las puebla con lo que hoy sale de los archivos de configuración: las
 * variables `FEL_CERTIFICADOR`, `FEL_FORCON_URL`, `FEL_FORCON_USUARIO`/
 * `FEL_FORCON_CLAVE`, `PAGO_PASARELA`, `CYBERSOURCE_URL` y `CYBERSOURCE_ORG_ID`
 * del entorno donde corre (o sus valores por defecto) y el parámetro
 * `fel_emisor_en_linea` de `config/services.yaml`. La clave global se cifra
 * como las de `CredencialFel` (llave derivada de `APP_SECRET`). Idempotente:
 * si las filas ya existen no las toca.
 */
final class Version20261031120000 extends AbstractMigration
{
    /** El `fel_emisor_en_linea` que tenía `config/services.yaml`. */
    private const EMISORES_EN_LINEA = [
        "2033683-7" => "6907303-1", // La Pionera → Transportes Rosita
        "4397700-6" => "6907303-1", // Maya de Oro → Transportes Rosita
    ];

    public function getDescription(): string
    {
        return 'configuracion_forcon y configuracion_cybersource, pobladas desde el entorno y services.yaml';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS configuracion_forcon (id INT NOT NULL, activo BOOLEAN DEFAULT false NOT NULL, url VARCHAR(255) DEFAULT \'https://pruebasfel.eforcon.com\' NOT NULL, usuario VARCHAR(100) DEFAULT NULL, clave_cifrada TEXT DEFAULT NULL, emisores_en_linea JSON DEFAULT \'{}\' NOT NULL, actualizada_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, actualizada_por_id INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_C62F7DF4E39C682E ON configuracion_forcon (actualizada_por_id)');
        $this->addSql('ALTER TABLE configuracion_forcon DROP CONSTRAINT IF EXISTS FK_C62F7DF4E39C682E');
        $this->addSql('ALTER TABLE configuracion_forcon ADD CONSTRAINT FK_C62F7DF4E39C682E FOREIGN KEY (actualizada_por_id) REFERENCES usuario (id) ON DELETE SET NULL NOT DEFERRABLE');

        $this->addSql('CREATE TABLE IF NOT EXISTS configuracion_cybersource (id INT NOT NULL, activo BOOLEAN DEFAULT false NOT NULL, url VARCHAR(255) DEFAULT \'https://apitest.cybersource.com\' NOT NULL, org_id VARCHAR(100) DEFAULT NULL, actualizada_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, actualizada_por_id INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_A785533BE39C682E ON configuracion_cybersource (actualizada_por_id)');
        $this->addSql('ALTER TABLE configuracion_cybersource DROP CONSTRAINT IF EXISTS FK_A785533BE39C682E');
        $this->addSql('ALTER TABLE configuracion_cybersource ADD CONSTRAINT FK_A785533BE39C682E FOREIGN KEY (actualizada_por_id) REFERENCES usuario (id) ON DELETE SET NULL NOT DEFERRABLE');

        $usuario = self::entorno('FEL_FORCON_USUARIO');
        $clave = self::entorno('FEL_FORCON_CLAVE');
        $secreto = self::entorno('APP_SECRET');
        $global = $usuario !== null && $clave !== null;
        if ($global && $secreto === null) {
            $this->warnIf(true, 'FEL_FORCON_USUARIO/FEL_FORCON_CLAVE no se copian: falta APP_SECRET para cifrar la clave (cárguelas con app:fel:configuracion --usuario).');
            $global = false;
        }

        $this->addSql(
            'INSERT INTO configuracion_forcon (id, activo, url, usuario, clave_cifrada, emisores_en_linea)
             VALUES (1, :activo, :url, :usuario, :clave, :emisores) ON CONFLICT (id) DO NOTHING',
            [
                'activo' => strtolower(self::entorno('FEL_CERTIFICADOR') ?? 'simulado') === 'forcon',
                'url' => rtrim(self::entorno('FEL_FORCON_URL') ?? 'https://pruebasfel.eforcon.com', '/'),
                'usuario' => $global ? $usuario : null,
                'clave' => $global ? (new CifradoCredenciales((string) $secreto))->cifrar('fdn-credencial-fel', (string) $clave) : null,
                'emisores' => json_encode(self::EMISORES_EN_LINEA, JSON_THROW_ON_ERROR),
            ],
            ['activo' => 'boolean'],
        );
        $this->addSql(
            'INSERT INTO configuracion_cybersource (id, activo, url, org_id)
             VALUES (1, :activo, :url, :org) ON CONFLICT (id) DO NOTHING',
            [
                'activo' => strtolower(self::entorno('PAGO_PASARELA') ?? 'simulada') === 'cybersource',
                'url' => rtrim(self::entorno('CYBERSOURCE_URL') ?? 'https://apitest.cybersource.com', '/'),
                'org' => self::entorno('CYBERSOURCE_ORG_ID'),
            ],
            ['activo' => 'boolean'],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS configuracion_cybersource');
        $this->addSql('DROP TABLE IF EXISTS configuracion_forcon');
    }

    /** Variable de entorno (la carga Dotenv o la da el contenedor); null si falta o está vacía. */
    private static function entorno(string $nombre): ?string
    {
        $valor = $_SERVER[$nombre] ?? $_ENV[$nombre] ?? getenv($nombre);
        $valor = is_string($valor) ? trim($valor) : '';

        return $valor !== '' ? $valor : null;
    }
}
