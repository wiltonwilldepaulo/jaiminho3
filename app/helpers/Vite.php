<?php

declare(strict_types=1);

namespace app\helpers;

final class Vite
{
    private const PUBLIC_DIR    = __DIR__ . '/../../public';
    private const HOT_FILE      = self::PUBLIC_DIR . '/hot';
    private const MANIFEST_FILE = self::PUBLIC_DIR . '/assets/manifest.json';
    private const BUILD_BASE    = '/assets/';

    private static ?array $manifest = null;

    private static ?string $hotUrl = null;
    private static bool   $clientInjected = false;

    public static function tag(string ...$entries): string
    {
        # Guard Clause — pré-condição explícita
        if (count($entries) === 0) {
            throw new \InvalidArgumentException(
                'Vite::tag() exige ao menos um entrypoint.'
            );
        }

        foreach ($entries as $entry) {
            if (trim($entry) === '') {
                throw new \InvalidArgumentException(
                    'Entrypoint vazio passado para Vite::tag().'
                );
            }
        }

        return self::isHot()
            ? self::renderHot($entries)
            : self::renderBuild($entries);
    }

    # Indica se o dev server do Vite está rodando agora.
    public static function isHot(): bool
    {
        return file_exists(self::HOT_FILE);
    }

    # Modo desenvolvimento: aponta tudo para o dev server.
    private static function renderHot(array $entries): string
    {
        $hotUrl = self::getHotUrl();
        $tags   = [];

        if (!self::$clientInjected) {
            $tags[] = sprintf(
                '<script type="module" src="%s/@vite/client"></script>',
                $hotUrl
            );
            self::$clientInjected = true;
        }

        foreach ($entries as $entry) {
            $tags[] = sprintf(
                '<script type="module" src="%s/%s"></script>',
                $hotUrl,
                ltrim($entry, '/')
            );
        }

        return implode("\n    ", $tags);
    }

    private static function renderBuild(array $entries): string
    {
        $manifest = self::getManifest();
        $cssTags  = [];
        $jsTags   = [];
        $preloadTags = [];
        $seenCss     = [];
        $seenPreload = [];

        foreach ($entries as $entry) {
            # Guard Clause — entry precisa existir no manifest
            if (!isset($manifest[$entry])) {
                throw new \RuntimeException(
                    "Entrypoint '{$entry}' não encontrado em manifest.json. "
                        . "Execute 'npm run build'."
                );
            }

            $chunk = $manifest[$entry];
            $file  = $chunk['file'] ?? null;

            if (!is_string($file) || $file === '') {
                throw new \RuntimeException(
                    "Manifest inválido: entry '{$entry}' não possui campo 'file' válido."
                );
            }

            # 1. CSS associado diretamente ao chunk (Vite resolve via @import)
            foreach (($chunk['css'] ?? []) as $css) {
                if (!isset($seenCss[$css])) {
                    $cssTags[] = sprintf(
                        '<link rel="stylesheet" href="%s%s">',
                        self::BUILD_BASE,
                        $css
                    );
                    $seenCss[$css] = true;
                }
            }

            # 2. Chunks compartilhados (jQuery, Bootstrap etc.) — modulepreload
            #    melhora performance: navegador busca em paralelo antes de precisar
            self::collectImportedChunks($manifest, $entry, $preloadTags, $seenPreload, $seenCss, $cssTags);

            # 3. Tag principal (script ou link, dependendo da extensão)
            if (str_ends_with($file, '.css')) {
                if (!isset($seenCss[$file])) {
                    $cssTags[] = sprintf(
                        '<link rel="stylesheet" href="%s%s">',
                        self::BUILD_BASE,
                        $file
                    );
                    $seenCss[$file] = true;
                }
            } else {
                $jsTags[] = sprintf(
                    '<script type="module" src="%s%s"></script>',
                    self::BUILD_BASE,
                    $file
                );
            }
        }

        # Ordem: CSS → modulepreload → JS (evita FOUC e maximiza paralelismo)
        return implode("\n    ", array_merge($cssTags, $preloadTags, $jsTags));
    }

    private static function collectImportedChunks(
        array $manifest,
        string $entryKey,
        array &$preloadTags,
        array &$seenPreload,
        array &$seenCss,
        array &$cssTags,
    ): void {
        $chunk = $manifest[$entryKey] ?? null;
        if ($chunk === null) {
            return;
        }

        foreach (($chunk['imports'] ?? []) as $importedKey) {
            if (isset($seenPreload[$importedKey])) {
                continue;
            }
            $seenPreload[$importedKey] = true;

            $imported = $manifest[$importedKey] ?? null;
            if ($imported === null) {
                continue;
            }

            $importedFile = $imported['file'] ?? null;
            if (is_string($importedFile) && $importedFile !== '') {
                $preloadTags[] = sprintf(
                    '<link rel="modulepreload" href="%s%s">',
                    self::BUILD_BASE,
                    $importedFile
                );
            }

            # CSS de chunks compartilhados também precisa entrar
            foreach (($imported['css'] ?? []) as $css) {
                if (!isset($seenCss[$css])) {
                    $cssTags[] = sprintf(
                        '<link rel="stylesheet" href="%s%s">',
                        self::BUILD_BASE,
                        $css
                    );
                    $seenCss[$css] = true;
                }
            }

            # Recursão controlada — segue cadeia de imports estáticos
            self::collectImportedChunks(
                $manifest,
                $importedKey,
                $preloadTags,
                $seenPreload,
                $seenCss,
                $cssTags
            );
        }
    }

    private static function getHotUrl(): string
    {
        if (self::$hotUrl !== null) {
            return self::$hotUrl;
        }

        $content = @file_get_contents(self::HOT_FILE);
        if ($content === false) {
            throw new \RuntimeException(
                'Falha ao ler ' . self::HOT_FILE
            );
        }

        $url = trim($content);
        return self::$hotUrl = ($url !== '' ? $url : 'http:#localhost:5173');
    }

    private static function getManifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        if (!file_exists(self::MANIFEST_FILE)) {
            throw new \RuntimeException(
                "manifest.json não encontrado em " . self::MANIFEST_FILE
                    . ". Execute 'npm run build' antes do deploy."
            );
        }

        $content = @file_get_contents(self::MANIFEST_FILE);
        if ($content === false) {
            throw new \RuntimeException(
                'Falha ao ler ' . self::MANIFEST_FILE
            );
        }

        try {
            /** @var array<string, array<string, mixed>> $decoded */
            $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(
                'manifest.json corrompido: ' . $e->getMessage(),
                0,
                $e
            );
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException(
                'manifest.json não retornou objeto JSON válido.'
            );
        }

        return self::$manifest = $decoded;
    }
}
