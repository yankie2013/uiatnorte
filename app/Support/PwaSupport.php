<?php
declare(strict_types=1);

namespace App\Support;

final class PwaSupport
{
    private const THEME_COLOR = '#0d1526';
    private const APP_NAME = 'UIAT Norte';
    private const SHORT_NAME = 'UIAT';
    private const ICON_VERSION = '20260404-logo';

    public static function boot(): void
    {
        if (\defined('UIAT_PWA_BOOTED') || PHP_SAPI === 'cli') {
            return;
        }

        \define('UIAT_PWA_BOOTED', true);

        if (self::shouldSkipRequest()) {
            return;
        }

        \ob_start([self::class, 'inject']);
    }

    private static function shouldSkipRequest(): bool
    {
        $script = \strtolower((string) \basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
        $skipScripts = [
            'sw.js',
            'manifest.webmanifest',
            'offline.html',
            'accidente_exportar_word.php',
            'citacion_diligencia.php',
            'citacion_diligencia_pdf.php',
            'exportar_accidente.php',
            'exportar_accidente_debug.php',
            'exportar_word.php',
            'informe_policial2.php',
            'marcador_abogado.php',
            'marcador_manifestacion_familiar.php',
            'marcador_manifestacion_investigado.php',
            'marcador_manifestacion_policia.php',
            'marcador_manifestacion_propietario.php',
            'oficio_peritaje.php',
            'oficio_peritaje_diag.php',
            'oficio_protocolo.php',
            'oficio_remitir_diligencia.php',
            'oficio_resultado_dosaje.php',
            'word_informe_atropello.php',
            'word_informe_atropello_probe.php',
            'word_informe_atropello_safe.php',
            'word_informe_atropello_tplcheck.php',
            'word_informe_choque_dos_vehiculos.php',
            'word_oficio_camaras.php',
        ];
        if (\in_array($script, $skipScripts, true)) {
            return true;
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($requestUri !== '' && \preg_match('~/(sw\.js|manifest\.webmanifest|offline\.html)$~i', $requestUri)) {
            return true;
        }

        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        if ($accept !== '' && !\str_contains($accept, 'text/html') && !\str_contains($accept, '*/*')) {
            return true;
        }

        return false;
    }

    public static function inject(string $buffer): string
    {
        if ($buffer === '' || self::isNonHtmlResponse() || !self::looksLikeHtmlDocument($buffer)) {
            return $buffer;
        }

        if (\stripos($buffer, 'manifest.webmanifest') !== false || \stripos($buffer, 'assets/pwa/pwa-register.js') !== false) {
            return $buffer;
        }

        $basePath = self::basePath();
        $headMarkup = self::headMarkup($basePath);
        $bodyMarkup = self::bodyMarkup($basePath);

        $headInjected = 0;
        $buffer = (string) \preg_replace('~</head>~i', $headMarkup . "\n</head>", $buffer, 1, $headInjected);
        if ($headInjected === 0) {
            return $buffer;
        }

        $buffer = (string) \preg_replace('~</body>~i', $bodyMarkup . "\n</body>", $buffer, 1);

        return $buffer;
    }

    private static function looksLikeHtmlDocument(string $buffer): bool
    {
        return \stripos($buffer, '<html') !== false && \stripos($buffer, '<head') !== false;
    }

    private static function isNonHtmlResponse(): bool
    {
        foreach (\headers_list() as $header) {
            if (!\str_starts_with(\strtolower($header), 'content-type:')) {
                continue;
            }

            $value = \strtolower(\trim(\substr($header, \strlen('content-type:'))));
            if ($value === '' || \str_contains($value, 'text/html')) {
                return false;
            }

            return true;
        }

        return false;
    }

    private static function basePath(): string
    {
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName === '') {
            return '';
        }

        $dir = \str_replace('\\', '/', \dirname($scriptName));
        if ($dir === '/' || $dir === '.') {
            return '';
        }

        return \rtrim($dir, '/');
    }

    private static function assetUrl(string $basePath, string $path): string
    {
        $fullPath = ($basePath === '' ? '' : $basePath) . '/' . \ltrim($path, '/');
        return \htmlspecialchars($fullPath, ENT_QUOTES, 'UTF-8');
    }

    private static function headMarkup(string $basePath): string
    {
        $themeColor = self::THEME_COLOR;
        $appName = \htmlspecialchars(self::APP_NAME, ENT_QUOTES, 'UTF-8');
        $shortName = \htmlspecialchars(self::SHORT_NAME, ENT_QUOTES, 'UTF-8');
        $manifest = self::assetUrl($basePath, 'manifest.webmanifest');
        $icon192 = self::assetUrl($basePath, 'assets/pwa/icon-192.png?v=' . self::ICON_VERSION);
        $icon512 = self::assetUrl($basePath, 'assets/pwa/icon-512.png?v=' . self::ICON_VERSION);
        $appleIcon = self::assetUrl($basePath, 'assets/pwa/apple-touch-icon.png?v=' . self::ICON_VERSION);

        return <<<HTML
<meta name="theme-color" content="{$themeColor}">
<meta name="application-name" content="{$appName}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{$shortName}">
<meta name="mobile-web-app-capable" content="yes">
<link rel="manifest" href="{$manifest}">
<link rel="shortcut icon" href="{$icon192}">
<link rel="icon" type="image/png" sizes="192x192" href="{$icon192}">
<link rel="icon" type="image/png" sizes="512x512" href="{$icon512}">
<link rel="apple-touch-icon" href="{$appleIcon}">
<style>
.uiat-pwa-banner{
  position:fixed;
  right:16px;
  bottom:16px;
  z-index:2147483000;
  display:none;
  align-items:center;
  gap:10px;
  max-width:min(92vw, 360px);
  padding:12px 14px;
  border-radius:16px;
  border:1px solid rgba(255,255,255,.16);
  background:rgba(13,21,38,.94);
  color:#f8fafc;
  box-shadow:0 18px 45px rgba(2,6,23,.35);
  backdrop-filter:blur(12px);
}
.uiat-pwa-banner.is-visible{ display:flex; }
.uiat-pwa-banner__copy{ min-width:0; }
.uiat-pwa-banner__title{
  margin:0 0 2px;
  font:700 14px/1.2 system-ui,-apple-system,"Segoe UI",sans-serif;
}
.uiat-pwa-banner__text{
  margin:0;
  color:rgba(248,250,252,.82);
  font:400 12px/1.35 system-ui,-apple-system,"Segoe UI",sans-serif;
}
.uiat-pwa-banner__actions{
  display:flex;
  align-items:center;
  gap:8px;
  margin-left:auto;
}
.uiat-pwa-banner__button,
.uiat-pwa-banner__dismiss{
  appearance:none;
  border:0;
  border-radius:999px;
  cursor:pointer;
  font:700 12px/1 system-ui,-apple-system,"Segoe UI",sans-serif;
}
.uiat-pwa-banner__button{
  padding:10px 14px;
  background:#d4af37;
  color:#0f172a;
}
.uiat-pwa-banner__dismiss{
  width:32px;
  height:32px;
  background:rgba(255,255,255,.1);
  color:#fff;
}
.uiat-pwa-status{
  position:fixed;
  top:16px;
  right:16px;
  z-index:2147483000;
  display:none;
  align-items:center;
  gap:8px;
  padding:10px 14px;
  border-radius:999px;
  border:1px solid rgba(255,255,255,.14);
  background:rgba(13,21,38,.94);
  color:#f8fafc;
  box-shadow:0 16px 40px rgba(2,6,23,.28);
  backdrop-filter:blur(12px);
  font:700 12px/1 system-ui,-apple-system,"Segoe UI",sans-serif;
}
.uiat-pwa-status.is-visible{ display:inline-flex; }
.uiat-pwa-status::before{
  content:"";
  width:9px;
  height:9px;
  border-radius:50%;
  background:#f59e0b;
  box-shadow:0 0 0 4px rgba(245,158,11,.14);
}
.uiat-pwa-status[data-state="online"]::before{
  background:#22c55e;
  box-shadow:0 0 0 4px rgba(34,197,94,.14);
}
.uiat-pwa-update{
  position:fixed;
  left:16px;
  bottom:16px;
  z-index:2147483000;
  display:none;
  align-items:center;
  gap:10px;
  max-width:min(92vw, 380px);
  padding:14px;
  border-radius:18px;
  border:1px solid rgba(255,255,255,.16);
  background:rgba(6,14,29,.96);
  color:#f8fafc;
  box-shadow:0 18px 45px rgba(2,6,23,.35);
  backdrop-filter:blur(12px);
}
.uiat-pwa-update.is-visible{ display:flex; }
.uiat-pwa-update__copy{ min-width:0; }
.uiat-pwa-update__title{
  margin:0 0 4px;
  font:700 14px/1.2 system-ui,-apple-system,"Segoe UI",sans-serif;
}
.uiat-pwa-update__text{
  margin:0;
  color:rgba(248,250,252,.82);
  font:400 12px/1.35 system-ui,-apple-system,"Segoe UI",sans-serif;
}
.uiat-pwa-update__actions{
  display:flex;
  align-items:center;
  gap:8px;
  margin-left:auto;
}
.uiat-pwa-update__button,
.uiat-pwa-update__dismiss{
  appearance:none;
  border:0;
  border-radius:999px;
  cursor:pointer;
  font:700 12px/1 system-ui,-apple-system,"Segoe UI",sans-serif;
}
.uiat-pwa-update__button{
  padding:10px 14px;
  background:#38bdf8;
  color:#082f49;
}
.uiat-pwa-update__dismiss{
  width:32px;
  height:32px;
  background:rgba(255,255,255,.1);
  color:#fff;
}
@media (max-width: 640px){
  .uiat-pwa-banner{
    left:12px;
    right:12px;
    bottom:12px;
    max-width:none;
  }
  .uiat-pwa-update{
    left:12px;
    right:12px;
    bottom:86px;
    max-width:none;
  }
  .uiat-pwa-status{
    left:12px;
    right:12px;
    top:12px;
    justify-content:center;
  }
}
</style>
HTML;
    }

    private static function bodyMarkup(string $basePath): string
    {
        $registerScript = self::assetUrl($basePath, 'assets/pwa/pwa-register.js');
        $scope = self::assetUrl($basePath, '');
        $escapedAppName = \htmlspecialchars(self::APP_NAME, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div class="uiat-pwa-banner" id="uiat-pwa-banner" hidden>
  <div class="uiat-pwa-banner__copy">
    <p class="uiat-pwa-banner__title">Instala {$escapedAppName}</p>
    <p class="uiat-pwa-banner__text" id="uiat-pwa-text">Accede m&aacute;s r&aacute;pido desde tu escritorio o pantalla de inicio.</p>
  </div>
  <div class="uiat-pwa-banner__actions">
    <button type="button" class="uiat-pwa-banner__button" id="uiat-pwa-install">Instalar</button>
    <button type="button" class="uiat-pwa-banner__dismiss" id="uiat-pwa-dismiss" aria-label="Cerrar aviso">&times;</button>
  </div>
</div>
<div class="uiat-pwa-status" id="uiat-pwa-status" data-state="offline" hidden>
  <span id="uiat-pwa-status-text">Sin conexi&oacute;n</span>
</div>
<div class="uiat-pwa-update" id="uiat-pwa-update" hidden>
  <div class="uiat-pwa-update__copy">
    <p class="uiat-pwa-update__title">Nueva versi&oacute;n disponible</p>
    <p class="uiat-pwa-update__text">Recarga para aplicar mejoras recientes del sistema.</p>
  </div>
  <div class="uiat-pwa-update__actions">
    <button type="button" class="uiat-pwa-update__button" id="uiat-pwa-refresh">Actualizar</button>
    <button type="button" class="uiat-pwa-update__dismiss" id="uiat-pwa-update-dismiss" aria-label="Cerrar aviso">&times;</button>
  </div>
</div>
<script src="{$registerScript}" data-pwa-scope="{$scope}"></script>
HTML;
    }
}
