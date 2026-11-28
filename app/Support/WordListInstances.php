<?php
declare(strict_types=1);
namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/** Restarts a source list without flattening its numbering, styles or indentation. */
final class WordListInstances
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private DOMDocument $numbering;
    private DOMXPath $numbers;
    private DOMXPath $styles;
    private array $instances = [];
    private int $nextId = 1;
    private bool $changed = false;

    public function __construct(ZipArchive $zip)
    {
        $this->numbering = new DOMDocument();
        $styleDocument = new DOMDocument();
        if (!$this->numbering->loadXML($zip->getFromName('word/numbering.xml'), LIBXML_NONET)
            || !$styleDocument->loadXML($zip->getFromName('word/styles.xml'), LIBXML_NONET)) {
            throw new RuntimeException('La plantilla no contiene estilos y numeración válidos.');
        }
        $this->numbers = new DOMXPath($this->numbering);
        $this->numbers->registerNamespace('w', self::W);
        $this->styles = new DOMXPath($styleDocument);
        $this->styles->registerNamespace('w', self::W);
        foreach ($this->numbers->query('/w:numbering/w:num') as $num) {
            $this->nextId = max($this->nextId, (int) $num->getAttributeNS(self::W, 'numId') + 1);
        }
    }

    /** Resolve a paragraph's native list, including the list inherited from its heading style. */
    public function resolve(DOMElement $paragraph): ?array
    {
        $xpath = new DOMXPath($paragraph->ownerDocument);
        $xpath->registerNamespace('w', self::W);
        $id = $xpath->evaluate('string(w:pPr/w:numPr/w:numId/@w:val)', $paragraph);
        $level = $xpath->evaluate('string(w:pPr/w:numPr/w:ilvl/@w:val)', $paragraph);
        $style = $xpath->evaluate('string(w:pPr/w:pStyle/@w:val)', $paragraph);
        $seen = [];
        while ($style !== '' && !isset($seen[$style])) {
            $seen[$style] = true;
            $node = null;
            foreach ($this->styles->query('/w:styles/w:style') as $candidate) {
                if ($candidate->getAttributeNS(self::W, 'styleId') === $style) {$node = $candidate; break;}
            }
            if (!$node) break;
            if ($id === '') $id = $this->styles->evaluate('string(w:pPr/w:numPr/w:numId/@w:val)', $node);
            if ($level === '') $level = $this->styles->evaluate('string(w:pPr/w:numPr/w:ilvl/@w:val)', $node);
            $style = $this->styles->evaluate('string(w:basedOn/@w:val)', $node);
        }
        return $id === '' || $id === '0' ? null : ['id' => (int) $id, 'level' => (int) $level];
    }

    /** A separate instance per repeated group allows each road's attributes to start at a. */
    public function restart(DOMElement $paragraph, string $group, bool $bullet = false): void
    {
        $source = $this->resolve($paragraph);
        if (!$source) throw new RuntimeException('El patrón no tiene una lista nativa para reiniciar.');
        $key = $source['id'] . ':' . $source['level'] . ':' . $group . ':' . (int) $bullet;
        if (!isset($this->instances[$key])) {
            $original = $this->numbers->query('/w:numbering/w:num[@w:numId="' . $source['id'] . '"]')->item(0);
            if (!$original) throw new RuntimeException('No se encontró la numeración del patrón.');
            $copy = $original->cloneNode(true);
            $id = $this->nextId++;
            $copy->setAttributeNS(self::W, 'w:numId', (string) $id);
            foreach (iterator_to_array($copy->attributes) as $attribute) {
                if ($attribute->localName === 'durableId') $copy->removeAttributeNode($attribute);
            }
            $override = $this->numbers->query('w:lvlOverride[@w:ilvl="' . $source['level'] . '"]', $copy)->item(0);
            if (!$override) {
                $override = $this->numbering->createElementNS(self::W, 'w:lvlOverride');
                $override->setAttributeNS(self::W, 'w:ilvl', (string) $source['level']);
                $copy->appendChild($override);
            }
            $start = $this->numbers->query('w:startOverride', $override)->item(0);
            if (!$start) {$start = $this->numbering->createElementNS(self::W, 'w:startOverride'); $override->insertBefore($start, $override->firstChild);}
            $start->setAttributeNS(self::W, 'w:val', '1');
            if ($bullet) {
                // Risk conditions are an additional bullet group; the original alphabetic list stays intact.
                $abstract = $this->numbers->evaluate('string(w:abstractNumId/@w:val)', $copy);
                $level = $this->numbers->query('w:lvl', $override)->item(0);
                if (!$level) {
                    $originalLevel = $this->numbers->query('/w:numbering/w:abstractNum[@w:abstractNumId="' . $abstract . '"]/w:lvl[@w:ilvl="' . $source['level'] . '"]')->item(0);
                    if (!$originalLevel) throw new RuntimeException('Nivel de lista no disponible.');
                    $level = $originalLevel->cloneNode(true); $override->appendChild($level);
                }
                $this->numbers->query('w:numFmt', $level)->item(0)->setAttributeNS(self::W, 'w:val', 'bullet');
                $this->numbers->query('w:lvlText', $level)->item(0)->setAttributeNS(self::W, 'w:val', '-');
            }
            $this->numbering->documentElement->appendChild($copy);
            $this->instances[$key] = $id;
            $this->changed = true;
        }
        $xpath = new DOMXPath($paragraph->ownerDocument); $xpath->registerNamespace('w', self::W);
        $pPr = $xpath->query('w:pPr', $paragraph)->item(0);
        if (!$pPr) {$pPr = $paragraph->ownerDocument->createElementNS(self::W, 'w:pPr'); $paragraph->insertBefore($pPr, $paragraph->firstChild);}
        $num = $xpath->query('w:numPr', $pPr)->item(0);
        if (!$num) {
            $num = $paragraph->ownerDocument->createElementNS(self::W, 'w:numPr');
            $before = null;
            foreach ($pPr->childNodes as $node) {
                if ($node instanceof DOMElement && !in_array($node->localName, ['pStyle','keepNext','keepLines','pageBreakBefore','framePr','widowControl'], true)) {$before=$node; break;}
            }
            $pPr->insertBefore($num, $before);
        }
        $level = $xpath->query('w:ilvl', $num)->item(0);
        if (!$level) {$level = $paragraph->ownerDocument->createElementNS(self::W, 'w:ilvl'); $num->insertBefore($level, $num->firstChild);}
        $level->setAttributeNS(self::W, 'w:val', (string) $source['level']);
        $id = $xpath->query('w:numId', $num)->item(0);
        if (!$id) {$id = $paragraph->ownerDocument->createElementNS(self::W, 'w:numId'); $num->appendChild($id);}
        $id->setAttributeNS(self::W, 'w:val', (string) $this->instances[$key]);
    }

    public function save(ZipArchive $zip): void
    {
        if ($this->changed) $zip->addFromString('word/numbering.xml', $this->numbering->saveXML());
    }
}
