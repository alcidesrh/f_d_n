<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Venta\CifradoCredenciales;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `configuracion_correo` (una fila, ADR-048), poblada con lo que había en el
 * entorno donde corre:
 *
 * - `CORREO_DOMINIO`, `CORREO_DOMINIO_ENTRADA` y `CORREO_MAILPACE_CLAVE`.
 * - `VENTA_CORREO_REMITENTE`, `PROGRAMADOR_CORREO_REMITENTE` y
 *   `PAGINA_CONTACTO_CORREO` (con los valores por defecto que tenía
 *   `services.yaml`).
 * - Si `MAILER_DSN` apunta a MailPace, queda encendido con su servidor y su
 *   token (cifrado con `APP_SECRET`) para cada dominio que usa el sistema:
 *   antes salía todo con ese token. Si no, apagado y los dominios sin token.
 *
 * Y el enlace «Correo (MailPace)» al final de la caja «Sistema» de la
 * administración (si la caja existe). No toca filas que ya existan.
 */
final class Version20261101190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'configuracion_correo, poblada desde el entorno, y su enlace en la administración';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE IF NOT EXISTS configuracion_correo (id INT NOT NULL, activo BOOLEAN DEFAULT false NOT NULL, servidor VARCHAR(255) DEFAULT 'smtp.mailpace.com' NOT NULL, puerto INT DEFAULT 465 NOT NULL, tokens JSON DEFAULT '{}' NOT NULL, dominio VARCHAR(255) DEFAULT 'sistemafdn.com' NOT NULL, dominio_entrada VARCHAR(255) DEFAULT NULL, clave_webhook VARCHAR(100) DEFAULT NULL, remitente_boletos VARCHAR(180) DEFAULT 'boletos@transportesfuentedelnorte.com' NOT NULL, remitente_reportes VARCHAR(180) DEFAULT 'reportes@transportesfuentedelnorte.com' NOT NULL, buzon_contacto VARCHAR(180) DEFAULT 'contacto@transportesfuentedelnorte.com' NOT NULL, actualizada_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, actualizada_por_id INT DEFAULT NULL, PRIMARY KEY (id))");
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_6EEFD1CE39C682E ON configuracion_correo (actualizada_por_id)');
        $this->addSql('ALTER TABLE configuracion_correo DROP CONSTRAINT IF EXISTS FK_6EEFD1CE39C682E');
        $this->addSql('ALTER TABLE configuracion_correo ADD CONSTRAINT FK_6EEFD1CE39C682E FOREIGN KEY (actualizada_por_id) REFERENCES usuario (id) ON DELETE SET NULL NOT DEFERRABLE');

        $dominio = strtolower(self::entorno('CORREO_DOMINIO') ?? 'sistemafdn.com');
        $entrada = strtolower(self::entorno('CORREO_DOMINIO_ENTRADA') ?? '');
        $boletos = strtolower(self::entorno('VENTA_CORREO_REMITENTE') ?? 'boletos@transportesfuentedelnorte.com');
        $reportes = strtolower(self::entorno('PROGRAMADOR_CORREO_REMITENTE') ?? 'reportes@transportesfuentedelnorte.com');

        $mailpace = self::mailpace(self::entorno('MAILER_DSN'));
        $secreto = self::entorno('APP_SECRET');
        if ($mailpace !== null && $secreto === null) {
            $this->warnIf(true, 'El token de MAILER_DSN no se copia: falta APP_SECRET para cifrarlo (cárguelo en Administración → Correo).');
            $mailpace = null;
        }
        $token = $mailpace !== null ? (new CifradoCredenciales((string) $secreto))->cifrar('fdn-credencial-correo', $mailpace['token']) : null;
        $tokens = [];
        foreach ([$dominio, self::dominioDe($boletos), self::dominioDe($reportes)] as $d) {
            if ($d !== '') {
                $tokens[$d] = $token;
            }
        }
        ksort($tokens);

        $this->addSql(
            'INSERT INTO configuracion_correo (id, activo, servidor, puerto, tokens, dominio, dominio_entrada, clave_webhook, remitente_boletos, remitente_reportes, buzon_contacto)
             VALUES (1, :activo, :servidor, :puerto, :tokens, :dominio, :entrada, :clave, :boletos, :reportes, :contacto) ON CONFLICT (id) DO NOTHING',
            [
                'activo' => $mailpace !== null,
                'servidor' => $mailpace['servidor'] ?? 'smtp.mailpace.com',
                'puerto' => $mailpace['puerto'] ?? 465,
                'tokens' => json_encode($tokens, JSON_THROW_ON_ERROR),
                'dominio' => $dominio,
                'entrada' => $entrada !== '' && $entrada !== $dominio ? $entrada : null,
                'clave' => self::entorno('CORREO_MAILPACE_CLAVE'),
                'boletos' => $boletos,
                'reportes' => $reportes,
                'contacto' => strtolower(self::entorno('PAGINA_CONTACTO_CORREO') ?? 'contacto@transportesfuentedelnorte.com'),
            ],
            ['activo' => 'boolean', 'puerto' => 'integer'],
        );

        $this->addSql(
            "INSERT INTO enlace_administracion (seccion_id, etiqueta, icono, claves, ruta, parametros, posicion, activo)
             SELECT s.id, 'Correo (MailPace)', 'mail-outline', '[\"correo\",\"mailpace\",\"smtp\",\"remitente\",\"webhook\"]', 'configuracion-correo', '{}',
                    (SELECT COALESCE(MAX(e.posicion), -1) + 1 FROM enlace_administracion e WHERE e.seccion_id = s.id), true
             FROM seccion_administracion s
             WHERE s.titulo = 'Sistema'
               AND NOT EXISTS (SELECT 1 FROM enlace_administracion x WHERE x.ruta = 'configuracion-correo')
             ORDER BY s.id LIMIT 1",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM enlace_administracion WHERE ruta = 'configuracion-correo'");
        $this->addSql('DROP TABLE IF EXISTS configuracion_correo');
    }

    /**
     * Servidor, puerto y token si el DSN es de MailPace (`smtp://TOKEN:TOKEN@smtp.mailpace.com:465`).
     *
     * @return ?array{servidor: string, puerto: int, token: string}
     */
    private static function mailpace(?string $dsn): ?array
    {
        $partes = $dsn !== null ? parse_url($dsn) : false;
        if (!is_array($partes) || !str_contains(strtolower($partes['host'] ?? ''), 'mailpace') || ($partes['user'] ?? '') === '') {
            return null;
        }

        return [
            'servidor' => str_starts_with((string) ($partes['scheme'] ?? ''), 'smtp') ? strtolower((string) $partes['host']) : 'smtp.mailpace.com',
            'puerto' => (int) ($partes['port'] ?? 465),
            'token' => urldecode((string) $partes['user']),
        ];
    }

    private static function dominioDe(string $direccion): string
    {
        $at = strrpos($direccion, '@');

        return $at === false ? '' : substr($direccion, $at + 1);
    }

    /** Variable de entorno (la carga Dotenv o la da el contenedor); null si falta o está vacía. */
    private static function entorno(string $nombre): ?string
    {
        $valor = $_SERVER[$nombre] ?? $_ENV[$nombre] ?? getenv($nombre);
        $valor = is_string($valor) ? trim($valor) : '';

        return $valor !== '' ? $valor : null;
    }
}
