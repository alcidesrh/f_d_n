<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Íconos: de Tabler a Google Material Symbols. Renombra los `icon.icon` (y su
 * `name` cuando era el mismo texto) que usa el sistema; un nombre que no está
 * en la tabla se deja igual. Amplía `icon.icon` a 100 caracteres (hay símbolos
 * de 51). Idempotente: re-ejecutarla no cambia nada porque los destinos no son
 * claves de la tabla.
 */
final class Version20261005120000 extends AbstractMigration
{
    /** Nombre de Tabler => nombre de Material Symbols (Iconify). */
    private const TABLER_A_SYMBOLS = [
        'pencil' => 'edit-outline',
        'trash' => 'delete-outline',
        'ban' => 'block',
        'square-check' => 'check-box-outline',
        'eye-off' => 'visibility-off-outline',
        'eye' => 'visibility-outline',
        'settings' => 'settings-outline',
        'eraser' => 'ink-eraser-outline',
        'chevron-down' => 'keyboard-arrow-down',
        'chevrons-down' => 'keyboard-double-arrow-down',
        'chevrons-up' => 'keyboard-double-arrow-up',
        'chevrons-left' => 'keyboard-double-arrow-left',
        'chevrons-right' => 'keyboard-double-arrow-right',
        'forms' => 'dynamic-form-outline',
        'arrow-back-up' => 'undo',
        'arrow-forward-up' => 'redo',
        'filter' => 'filter-alt-outline',
        'filter-filled' => 'filter-alt',
        'x' => 'close',
        'sort-ascending' => 'sort',
        'sort-descending' => 'arrow-downward',
        'arrows-sort' => 'swap-vert',
        'report-money' => 'request-quote-outline',
        'file-type-pdf' => 'picture-as-pdf-outline',
        'file-type-xls' => 'table-chart-outline',
        'chart-bar' => 'bar-chart',
        'file-invoice' => 'receipt-long-outline',
        'receipt' => 'receipt-outline',
        'info-circle' => 'info-outline',
        'error' => 'error-outline',
        'alert' => 'warning-outline',
        'alert-triangle' => 'warning-outline',
        'alert-octagon' => 'dangerous-outline',
        'exclamation-circle' => 'error-outline',
        'circle-dashed-check' => 'check-circle-outline',
        'circle-check' => 'check-circle-outline',
        'circle-dashed' => 'radio-button-unchecked',
        'device-floppy' => 'save-outline',
        'grip-vertical' => 'drag-indicator',
        'calendar-plus' => 'calendar-add-on-outline',
        'calendar' => 'calendar-month-outline',
        'clock' => 'schedule-outline',
        'reload' => 'refresh',
        'rotate-ccw' => 'restart-alt',
        'arrows-minimize' => 'fullscreen-exit',
        'arrows-maximize' => 'fullscreen',
        'bus' => 'directions-bus-outline',
        'external-link' => 'open-in-new',
        'mail' => 'mail-outline',
        'plus' => 'add',
        'minus' => 'remove',
        'file-description' => 'description-outline',
        'steering-wheel' => 'sports-motorsports-outline',
        'drag-drop' => 'drag-pan',
        'arrow-bar-to-left' => 'format-indent-decrease',
        'arrow-bar-to-right' => 'format-indent-increase',
        'point' => 'fiber-manual-record',
        'arrow-left' => 'arrow-back',
        'template' => 'bookmark-outline',
        'stack-push' => 'stacks',
        'list-numbers' => 'format-list-numbered',
        'copy' => 'content-copy-outline',
        'lock' => 'lock-outline',
        'hand-grab' => 'pan-tool-outline',
        'brush' => 'brush-outline',
        'arrows-exchange' => 'swap-horiz',
        'trash-x' => 'delete-forever-outline',
        'keyboard' => 'keyboard-outline',
        'sort-ascending-numbers' => 'compress',
        'printer' => 'print-outline',
        'menu-2' => 'menu',
        'palette' => 'palette-outline',
        'bell' => 'notifications-outline',
        'wallet' => 'account-balance-wallet-outline',
        'layout-grid' => 'grid-view-outline',
        'layout-sidebar' => 'left-panel-open-outline',
        'layout-sidebar-right' => 'right-panel-open-outline',
        'layout-navbar' => 'web-asset',
        'adjustments' => 'tune',
        'sitemap' => 'account-tree-outline',
        'ticket' => 'confirmation-number-outline',
        'map-pin' => 'location-on-outline',
        'world-www' => 'language',
        'database' => 'database-outline',
        'calendar-time' => 'calendar-clock-outline',
        'gps' => 'my-location-outline',
        'menu-order' => 'reorder',
    ];

    public function getDescription(): string
    {
        return 'Íconos: Tabler => Material Symbols (icon.icon hasta 100 caracteres)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE icon ALTER icon TYPE VARCHAR(100)');
        foreach (self::TABLER_A_SYMBOLS as $tabler => $symbol) {
            $this->addSql('UPDATE icon SET name = :symbol WHERE icon = :tabler AND name = :tabler', ['symbol' => $symbol, 'tabler' => $tabler]);
            $this->addSql('UPDATE icon SET icon = :symbol WHERE icon = :tabler', ['symbol' => $symbol, 'tabler' => $tabler]);
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::TABLER_A_SYMBOLS as $tabler => $symbol) {
            // Varios nombres de Tabler comparten destino; se restaura solo el primero.
            if (array_search($symbol, self::TABLER_A_SYMBOLS, true) !== $tabler) {
                continue;
            }
            $this->addSql('UPDATE icon SET name = :tabler WHERE icon = :symbol AND name = :symbol', ['symbol' => $symbol, 'tabler' => $tabler]);
            $this->addSql('UPDATE icon SET icon = :tabler WHERE icon = :symbol', ['symbol' => $symbol, 'tabler' => $tabler]);
        }
        $this->addSql('ALTER TABLE icon ALTER icon TYPE VARCHAR(50)');
    }
}
