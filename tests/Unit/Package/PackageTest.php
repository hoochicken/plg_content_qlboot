<?php

/**
 * @package        plg_content_qlboot
 * @copyright      Copyright (C) 2026 ql.de All rights reserved.
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Consistency checks between manifest, code, language files and media,
 * i.e. the things that break silently on install or update.
 */

namespace Hoochicken\Plugin\Content\Qlboot\Tests\Unit\Package;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PackageTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';
    private const LANGUAGES = ['en-GB', 'de-DE'];

    private static function manifest(): \SimpleXMLElement
    {
        $xml = simplexml_load_file(self::ROOT . '/qlboot.xml');
        self::assertInstanceOf(\SimpleXMLElement::class, $xml, 'qlboot.xml is not valid XML');

        return $xml;
    }

    /** @return array<string, string> */
    private static function ini(string $tag, string $file): array
    {
        $strings = parse_ini_file(self::ROOT . "/language/$tag/$file", false, INI_SCANNER_RAW);
        self::assertIsArray($strings, "$tag/$file is not a valid ini file");

        return $strings;
    }

    public function testManifestIsAJoomla6ContentPlugin(): void
    {
        $xml = self::manifest();

        $this->assertSame('plugin', (string) $xml['type']);
        $this->assertSame('content', (string) $xml['group']);
        $this->assertSame('upgrade', (string) $xml['method']);
        $this->assertSame('Hoochicken\Plugin\Content\Qlboot', (string) $xml->namespace);
        $this->assertSame('src', (string) $xml->namespace['path']);
        $this->assertSame('services', (string) $xml->xpath('//files/folder[@plugin="qlboot"]')[0]);
        $this->assertFileExists(self::ROOT . '/services/provider.php');
        $this->assertFileExists(self::ROOT . '/src/Extension/Qlboot.php');
        $this->assertFileDoesNotExist(self::ROOT . '/qlboot.php', 'legacy entry file must be gone');
    }

    public function testFilesListedInManifestExist(): void
    {
        $xml = self::manifest();
        foreach ($xml->files->children() as $node) {
            $this->assertFileExists(self::ROOT . '/' . $node);
        }
        foreach ($xml->languages->language as $language) {
            $this->assertFileExists(self::ROOT . '/' . $language);
        }
    }

    public function testMediaIsInstalledWhereThePluginLoadsIt(): void
    {
        $media = self::manifest()->media;
        $this->assertSame('plg_content_qlboot', (string) $media['destination']);
        foreach ($media->folder as $folder) {
            $this->assertDirectoryExists(self::ROOT . '/' . $media['folder'] . '/' . $folder);
        }

        // 'plg_content_qlboot/qlboot.css' resolves to media/plg_content_qlboot/css/qlboot.css
        $this->assertStringContainsString("'plg_content_qlboot/qlboot.css'", file_get_contents(self::ROOT . '/src/Extension/Qlboot.php'));
        $this->assertFileExists(self::ROOT . '/media/css/qlboot.css');
    }

    public function testBootstrapAssetsExistInJoomla(): void
    {
        $registry = json_decode(file_get_contents(JPATH_ROOT . '/media/vendor/joomla.asset.json'), true, 512, JSON_THROW_ON_ERROR);
        $names = array_column($registry['assets'], 'version', 'name');

        $this->assertArrayHasKey('bootstrap.css', $names);
        $this->assertArrayHasKey('bootstrap.css.grid', $names);
        $this->assertStringStartsWith('5.', $names['bootstrap.css.grid']);
    }

    public function testVersionsMatchUpdateServer(): void
    {
        $update = simplexml_load_file(self::ROOT . '/_jupdate/plg_content_qlboot.xml');
        $version = (string) self::manifest()->version;

        $this->assertSame($version, (string) $update->update->version);
        $this->assertStringContainsString("v$version.zip", (string) $update->update->downloads->downloadurl);
        $this->assertSame('qlboot', (string) $update->update->element);
        $this->assertSame('content', (string) $update->update->folder);
        $this->assertMatchesRegularExpression('/^' . $update->update->targetplatform['version'] . '$/', '6.2.0');
    }

    /** @return iterable<string, array{string}> */
    public static function languages(): iterable
    {
        foreach (self::LANGUAGES as $tag) {
            yield $tag => [$tag];
        }
    }

    #[DataProvider('languages')]
    public function testAllManifestLanguageKeysAreTranslated(string $tag): void
    {
        $xml = file_get_contents(self::ROOT . '/qlboot.xml');
        preg_match_all('/\bPLG_CONTENT_QLBOOT[A-Z0-9_]*/', $xml, $matches);
        $strings = self::ini($tag, 'plg_content_qlboot.ini') + self::ini($tag, 'plg_content_qlboot.sys.ini');

        $missing = array_diff(array_unique($matches[0]), array_keys($strings));
        $this->assertSame([], array_values($missing), "keys used in the manifest but not translated in $tag");
    }

    #[DataProvider('languages')]
    public function testSysIniHasNameAndDescription(string $tag): void
    {
        $sys = self::ini($tag, 'plg_content_qlboot.sys.ini');

        $this->assertArrayHasKey('PLG_CONTENT_QLBOOT', $sys);
        $this->assertArrayHasKey('PLG_CONTENT_QLBOOT_DESCRIPTION', $sys);
    }

    #[DataProvider('languages')]
    public function testLanguageFilesHaveSameKeys(string $tag): void
    {
        $this->assertSame(
            array_keys(self::ini('en-GB', 'plg_content_qlboot.ini')),
            array_keys(self::ini($tag, 'plg_content_qlboot.ini'))
        );
    }

    /** @return iterable<string, array{string}> */
    public static function phpFiles(): iterable
    {
        $root = realpath(self::ROOT);
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $relative = substr(str_replace('\\', '/', $file->getPathname()), \strlen($root) + 1);
            if ($file->getExtension() !== 'php' || preg_match('#^(vendor|\.git|tests)/#', $relative)) {
                continue;
            }
            yield $relative => [$file->getPathname()];
        }
    }

    #[DataProvider('phpFiles')]
    public function testPhpFileLints(string $path): void
    {
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
    }
}
