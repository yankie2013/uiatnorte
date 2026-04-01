<?php
$zip = new ZipArchive();
$zip->open(__DIR__ . '/tmp/last_notificacion_abogado_debug.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
preg_match_all('/[\x80-\xFF]/', $xml, $m, PREG_OFFSET_CAPTURE);
$count = 0;
foreach ($m[0] as [$char, $offset]) {
  if ($offset < 43000) continue;
  $b = ord($char);
  echo 'offset=' . $offset . ' byte=0x' . strtoupper(dechex($b)) . PHP_EOL;
  echo substr($xml, max(0, $offset - 80), 180) . PHP_EOL;
  echo "---\n";
  $count++;
  if ($count >= 10) break;
}
