<?php

declare(strict_types=1);

namespace App\Reporte\Xlsx;

/**
 * Libro de Excel (.xlsx) de una sola hoja, sin dependencias: lo justo para los
 * reportes (estilos fijos, anchos, celdas combinadas, panel inmovilizado,
 * autofiltro y página horizontal ajustada al ancho).
 *
 * Una celda es un valor (`string|int|float|bool|\DateTimeInterface|null`) o
 * `[valor, estilo]`; sin estilo propio usa el de la fila. Los importes se
 * escriben como número (no como texto) para poder sumarlos en Excel.
 */
final class LibroXlsx
{
    public const NORMAL = 0;
    public const TITULO = 1;
    public const SUBTITULO = 2;
    public const ETIQUETA = 3;
    public const VALOR = 4;
    public const ENCABEZADO = 5;
    public const TEXTO = 6;
    public const CENTRO = 7;
    public const DINERO = 8;
    public const ENTERO = 9;
    public const FECHA_HORA = 10;
    public const TOTAL_TEXTO = 11;
    public const TOTAL_DINERO = 12;
    public const SECCION = 13;
    public const APAGADO = 14;
    public const TOTAL_ENTERO = 15;

    /** @var list<string> */
    private array $filas = [];
    /** @var list<float> */
    private array $anchos = [];
    /** @var list<string> */
    private array $combinadas = [];
    private int $siguiente = 1;
    private int $columnasMax = 1;
    private ?int $congelarEn = null;
    private ?string $filtro = null;

    public function __construct(private readonly string $hoja)
    {
    }

    /** @param list<float> $anchos en caracteres */
    public function anchos(array $anchos): self
    {
        $this->anchos = $anchos;

        return $this;
    }

    /**
     * @param list<mixed> $celdas
     * @return int número (1-based) de la fila escrita
     */
    public function fila(array $celdas, int $estilo = self::TEXTO, ?float $alto = null): int
    {
        $n = $this->siguiente++;
        $xml = "";
        foreach (array_values($celdas) as $i => $celda) {
            [$valor, $e] = is_array($celda) ? [$celda[0], $celda[1] ?? $estilo] : [$celda, $estilo];
            $xml .= self::celda(self::columna($i) . $n, $valor, $e);
        }
        $this->columnasMax = max($this->columnasMax, count($celdas));
        $this->filas[] = sprintf('<row r="%d"%s>%s</row>', $n, $alto !== null ? sprintf(' ht="%s" customHeight="1"', $alto) : "", $xml);

        return $n;
    }

    public function saltar(int $filas = 1): void
    {
        $this->siguiente += $filas;
    }

    /** Combina de la columna `$desde` a `$hasta` (0-based) en la fila `$fila`. */
    public function combinar(int $fila, int $desde, int $hasta): void
    {
        $this->combinadas[] = sprintf("%s%d:%s%d", self::columna($desde), $fila, self::columna($hasta), $fila);
    }

    /** Inmoviliza las filas por encima de `$fila` (la primera que se desplaza). */
    public function congelar(int $fila): void
    {
        $this->congelarEn = $fila;
    }

    public function autofiltro(int $filaEncabezado, int $ultimaFila, int $columnas): void
    {
        $this->filtro = sprintf("A%d:%s%d", $filaEncabezado, self::columna($columnas - 1), max($ultimaFila, $filaEncabezado));
    }

    public function contenido(): string
    {
        $archivo = tempnam(sys_get_temp_dir(), "xlsx");
        if ($archivo === false) {
            throw new \RuntimeException("No se pudo crear el archivo temporal del Excel.");
        }
        $zip = new \ZipArchive();
        $zip->open($archivo, \ZipArchive::OVERWRITE);
        $zip->addFromString("[Content_Types].xml", $this->tipos());
        $zip->addFromString("_rels/.rels", self::RELS);
        $zip->addFromString("xl/workbook.xml", $this->libro());
        $zip->addFromString("xl/_rels/workbook.xml.rels", self::RELS_LIBRO);
        $zip->addFromString("xl/styles.xml", self::ESTILOS);
        $zip->addFromString("xl/worksheets/sheet1.xml", $this->hojaXml());
        $zip->close();
        $bytes = (string) file_get_contents($archivo);
        @unlink($archivo);

        return $bytes;
    }

    private function hojaXml(): string
    {
        $ultima = max($this->siguiente - 1, 1);
        $cols = "";
        foreach ($this->anchos as $i => $ancho) {
            $cols .= sprintf('<col min="%1$d" max="%1$d" width="%2$s" customWidth="1"/>', $i + 1, $ancho);
        }
        $vista = $this->congelarEn === null
            ? '<sheetView workbookViewId="0" showGridLines="0"/>'
            : sprintf(
                '<sheetView workbookViewId="0" showGridLines="0"><pane ySplit="%d" topLeftCell="A%d" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft"/></sheetView>',
                $this->congelarEn - 1,
                $this->congelarEn,
            );

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            . sprintf('<dimension ref="A1:%s%d"/>', self::columna($this->columnasMax - 1), $ultima)
            . "<sheetViews>{$vista}</sheetViews>"
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . ($cols !== "" ? "<cols>{$cols}</cols>" : "")
            . "<sheetData>" . implode("", $this->filas) . "</sheetData>"
            . ($this->filtro !== null ? sprintf('<autoFilter ref="%s"/>', $this->filtro) : "")
            . ($this->combinadas !== [] ? sprintf('<mergeCells count="%d">%s</mergeCells>', count($this->combinadas), implode("", array_map(static fn(string $r) => sprintf('<mergeCell ref="%s"/>', $r), $this->combinadas))) : "")
            . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
            . '<pageSetup orientation="landscape" paperSize="1" fitToWidth="1" fitToHeight="0"/>'
            . "</worksheet>";
    }

    private function libro(): string
    {
        $nombre = self::xml($this->hoja);
        $definidos = $this->filtro === null
            ? ""
            : sprintf(
                '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'%s\'!%s</definedName></definedNames>',
                $nombre,
                preg_replace('/([A-Z]+)(\d+)/', '$\1$\2', $this->filtro),
            );

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . sprintf('<sheets><sheet name="%s" sheetId="1" r:id="rId1"/></sheets>', $nombre)
            . $definidos
            . "</workbook>";
    }

    private function tipos(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . "</Types>";
    }

    private static function celda(string $ref, mixed $valor, int $estilo): string
    {
        $s = sprintf(' s="%d"', $estilo);

        return match (true) {
            $valor === null || $valor === "" => sprintf('<c r="%s"%s/>', $ref, $s),
            is_bool($valor) => sprintf('<c r="%s"%s t="b"><v>%d</v></c>', $ref, $s, $valor),
            is_int($valor) || is_float($valor) => sprintf('<c r="%s"%s><v>%s</v></c>', $ref, $s, $valor),
            $valor instanceof \DateTimeInterface => sprintf('<c r="%s"%s><v>%s</v></c>', $ref, $s, self::serial($valor)),
            default => sprintf('<c r="%s"%s t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>', $ref, $s, self::xml((string) $valor)),
        };
    }

    /** Fecha y hora locales como número de serie de Excel. */
    private static function serial(\DateTimeInterface $d): string
    {
        $utc = new \DateTimeImmutable($d->format("Y-m-d H:i:s"), new \DateTimeZone("UTC"));

        return rtrim(rtrim(number_format($utc->getTimestamp() / 86400 + 25569, 8, ".", ""), "0"), ".");
    }

    private static function xml(string $s): string
    {
        return htmlspecialchars((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', "", $s), ENT_XML1 | ENT_QUOTES, "UTF-8");
    }

    /** 0 → A, 25 → Z, 26 → AA. */
    private static function columna(int $i): string
    {
        $c = "";
        for ($n = $i + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $c = chr(65 + ($n - 1) % 26) . $c;
        }

        return $c;
    }

    private const RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . "</Relationships>";

    private const RELS_LIBRO = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . "</Relationships>";

    /** Índices de `cellXfs` = constantes de estilo de arriba. */
    private const ESTILOS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy\ hh:mm:ss"/></numFmts>'
        . '<fonts count="7">'
        . '<font><sz val="10"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="10"/><color rgb="FF1F2937"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="16"/><color rgb="FF1E3A5F"/><name val="Calibri"/></font>'
        . '<font><sz val="10"/><color rgb="FF6B7280"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FF1E3A5F"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="9"/><color rgb="FF6B7280"/><name val="Calibri"/></font>'
        . "</fonts>"
        . '<fills count="5">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF1E3A5F"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFDBE6F4"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFF3F6FA"/><bgColor indexed="64"/></patternFill></fill>'
        . "</fills>"
        . '<borders count="3">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left/><right/><top/><bottom style="thin"><color rgb="FFE5E9EF"/></bottom><diagonal/></border>'
        . '<border><left/><right/><top style="thin"><color rgb="FF1E3A5F"/></top><bottom style="thin"><color rgb="FF1E3A5F"/></bottom><diagonal/></border>'
        . "</borders>"
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="16">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="0" fontId="4" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top"/></xf>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>'
        . '<xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top"/></xf>'
        . '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="top"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="3" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>'
        . '<xf numFmtId="164" fontId="1" fillId="3" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/>'
        . '<xf numFmtId="0" fontId="5" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>'
        . '<xf numFmtId="1" fontId="1" fillId="3" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
        . "</cellXfs>"
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . "</styleSheet>";
}
