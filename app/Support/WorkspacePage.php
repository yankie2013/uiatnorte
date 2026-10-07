<?php
declare(strict_types=1);
namespace App\Support;
final class WorkspacePage {
    public static function escape(mixed $s): string {return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    public static function start(string $title, string $extraCss = '', ?array $breadcrumbs = null, bool $simpleHeader = false): void {
        $userTopbarSection = $title;
        $userTopbarBreadcrumbs = $breadcrumbs;
        $title=self::escape($title);
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$title.' · DEPIAT</title><link rel="stylesheet" href="assets/css/gestion.css"><link rel="stylesheet" href="assets/css/expediente_card.css?v='.(int)filemtime(base_path('assets/css/expediente_card.css')).'">'.($extraCss !== '' ? '<link rel="stylesheet" href="'.self::escape($extraCss).'">' : '').'</head><body>';
        require base_path('sidebar.php');
        if ($simpleHeader) {
            echo '<main class="gestion"><header><h1>'.$title.'</h1></header>';
            return;
        }
        echo '<main class="gestion"><header><p class="eyebrow">DEPIAT · GESTIÓN DE EXPEDIENTES</p><h1>'.$title.'</h1><p>'.self::escape(trim((Access::actor()['grado']??'').' '.(Access::actor()['nombre']??''))).' · '.self::escape(Access::ROLES[Access::role()]??'').'</p></header>';
    }
    public static function end(): void {echo '</main></body></html>';}
    public static function token(): void {echo '<input type="hidden" name="_csrf" value="'.self::escape(Access::csrf()).'">';}
    public static function notice(string $message,bool $error=false): void {if($message!=='')echo '<p role="status" class="notice '.($error?'error':'').'">'.self::escape($message).'</p>';}
}
