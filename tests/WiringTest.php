<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Magento assembles a module from XML, and a mistake there only shows on a live installation
 * after setup:upgrade: the button does not appear, the layout cannot find its block, the page
 * answers 404 on a permission that does not exist. This checks what can be checked without
 * Magento: that the files tell the truth about each other.
 */
final class WiringTest extends TestCase
{
    private static function xml(string $relative): \SimpleXMLElement
    {
        $path = \dirname(__DIR__) . '/' . $relative;
        self::assertFileExists($path);
        $xml = simplexml_load_file($path);
        self::assertInstanceOf(\SimpleXMLElement::class, $xml, sprintf('%s is not valid XML', $relative));

        return $xml;
    }

    private static function classFile(string $class): string
    {
        return \dirname(__DIR__) . '/' . str_replace('\\', '/', str_replace('Calmfox\\AdminInvitation\\', '', $class)) . '.php';
    }

    /** Every class named in di.xml, layouts and plugins has to exist under this module. */
    public function testEveryClassNamedInXmlExists(): void
    {
        $files = ['etc/di.xml', 'etc/adminhtml/di.xml', 'etc/adminhtml/system.xml'];
        foreach (glob(\dirname(__DIR__) . '/view/adminhtml/layout/*.xml') ?: [] as $layout) {
            $files[] = 'view/adminhtml/layout/' . basename($layout);
        }

        $found = 0;
        foreach ($files as $file) {
            $content = (string) file_get_contents(\dirname(__DIR__) . '/' . $file);
            // virtual types are configuration, not code: they name no file on purpose
            preg_match_all('/<virtualType name="([^"]+)"/', $content, $virtual);
            preg_match_all('/Calmfox\\\\AdminInvitation\\\\[A-Za-z0-9_\\\\]+/', $content, $matches);

            foreach (array_unique($matches[0]) as $class) {
                if (\in_array($class, $virtual[1], true)) {
                    continue;
                }
                ++$found;
                self::assertFileExists(self::classFile($class), sprintf('%s names %s, which has no file', $file, $class));
            }
        }

        self::assertGreaterThan(5, $found, 'the XML suddenly names almost no classes of ours');
    }

    /** Every template a block declares has to be shipped with the module. */
    public function testEveryDeclaredTemplateExists(): void
    {
        $blocks = self::phpFilesIn(\dirname(__DIR__) . '/Block');
        self::assertNotEmpty($blocks);

        foreach ($blocks as $block) {
            $content = (string) file_get_contents($block);
            if (!preg_match('/Calmfox_AdminInvitation::([A-Za-z0-9_\/.\-]+\.phtml)/', $content, $match)) {
                continue;
            }
            self::assertFileExists(
                \dirname(__DIR__) . '/view/adminhtml/templates/' . $match[1],
                sprintf('%s declares the template %s, which is not in the module', basename($block), $match[1]),
            );
        }
    }

    /** Layout files carry the handle name of the route they belong to, or they never load. */
    public function testLayoutHandlesMatchTheControllers(): void
    {
        $expected = [
            'calmfox_invitation_user_invite.xml' => 'Controller/Adminhtml/User/Invite.php',
            'calmfox_invitation_accept_index.xml' => 'Controller/Adminhtml/Accept/Index.php',
        ];

        foreach ($expected as $layout => $controller) {
            self::assertFileExists(\dirname(__DIR__) . '/view/adminhtml/layout/' . $layout);
            self::assertFileExists(\dirname(__DIR__) . '/' . $controller);
        }
    }

    /** The acceptance page must keep reusing the admin login screen, logo and all. */
    public function testTheAcceptancePageUsesTheAdminLoginScreen(): void
    {
        $page = self::xml('view/adminhtml/layout/calmfox_invitation_accept_index.xml');

        self::assertSame('admin-login', (string) $page['layout']);
        self::assertSame('admin_login', (string) $page->update['handle']);
        self::assertSame('login.content', (string) $page->body->referenceContainer['name']);
    }

    /** The route front name is what the e-mail links point at; changing it breaks sent links. */
    public function testTheRouteFrontNameIsStable(): void
    {
        $route = self::xml('etc/adminhtml/routes.xml')->router->route;

        self::assertSame('calmfox_invitation', (string) $route['id']);
        self::assertSame('calmfox_invitation', (string) $route['frontName']);
    }

    /**
     * The public pages stay public only while they keep away from Magento's backend action:
     * the plugin that guards it would send every invitee to the sign-in screen instead.
     */
    public function testThePublicPagesDoNotExtendTheBackendAction(): void
    {
        foreach (['Controller/Adminhtml/Accept/Index.php', 'Controller/Adminhtml/Accept/Save.php'] as $file) {
            $content = (string) file_get_contents(\dirname(__DIR__) . '/' . $file);

            self::assertStringNotContainsString('extends Action', $content, $file);
            self::assertStringNotContainsString('Magento\\Backend\\App\\Action', $content, $file);
            self::assertMatchesRegularExpression('/implements [A-Za-z]*Http(Get|Post)ActionInterface/', $content, $file);
        }
    }

    /** Having left the backend action behind, they have to load the admin design themselves. */
    public function testThePublicPagesLoadTheAdminDesign(): void
    {
        foreach (['Controller/Adminhtml/Accept/Index.php', 'Controller/Adminhtml/Accept/Save.php'] as $file) {
            $content = (string) file_get_contents(\dirname(__DIR__) . '/' . $file);

            self::assertStringContainsString('designLoader->load()', $content, $file);
        }
    }

    /** The form that posts a password must check the form key itself; no base class does it here. */
    public function testTheAcceptancePostIsCsrfAware(): void
    {
        $content = (string) file_get_contents(\dirname(__DIR__) . '/Controller/Adminhtml/Accept/Save.php');

        self::assertStringContainsString('CsrfAwareActionInterface', $content);
        self::assertStringContainsString('formKeyValidator->validate', $content);
    }

    /** Every panel page the module adds stays behind the permission for managing users. */
    public function testThePanelPagesRequireThePermissionForUsers(): void
    {
        foreach (['Controller/Adminhtml/User/Invite.php', 'Controller/Adminhtml/User/Send.php', 'Controller/Adminhtml/User/AccessLink.php'] as $file) {
            $content = (string) file_get_contents(\dirname(__DIR__) . '/' . $file);

            self::assertStringContainsString("ADMIN_RESOURCE = 'Magento_User::acl_users'", $content, $file);
        }
    }

    /**
     * The acceptance page asks in two steps, and both have to be in the markup: the script
     * only hides one of them, so an invitee without JavaScript still sees every field.
     */
    public function testTheAcceptanceFormShipsBothStepsAndTheScriptThatSplitsThem(): void
    {
        $template = (string) file_get_contents(\dirname(__DIR__) . '/view/adminhtml/templates/accept/form.phtml');

        self::assertStringContainsString('data-step="1"', $template);
        self::assertStringContainsString('data-step="2"', $template);
        foreach (['firstname', 'lastname', 'username', 'password', 'confirmation'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $template, $field);
        }

        self::assertFileExists(\dirname(__DIR__) . '/view/adminhtml/web/js/accept-steps.js');
        self::assertFileExists(\dirname(__DIR__) . '/view/adminhtml/web/js/password-generator.js');
    }

    /** Both steps are posted at once, so an abandoned setup leaves no half-made account. */
    public function testTheTwoStepsArePostedTogether(): void
    {
        $template = (string) file_get_contents(\dirname(__DIR__) . '/view/adminhtml/templates/accept/form.phtml');

        self::assertSame(1, substr_count($template, '<form '), 'the acceptance page must post one form, not one per step');
        self::assertSame(1, substr_count($template, 'type="submit"'), 'only the last step submits');
    }

    /** Whatever the e-mail templates say, they must not carry our developer comments to readers. */
    public function testTheEmailTemplatesCarryNoDeveloperComments(): void
    {
        foreach (glob(\dirname(__DIR__) . '/view/frontend/email/*.html') ?: [] as $file) {
            $content = (string) file_get_contents($file);
            $comments = preg_match_all('/<!--(?!@subject|@vars)/', $content);

            self::assertSame(0, $comments, basename($file) . ' ships a comment inside every message it sends');
        }
    }

    /** The e-mail templates declared in email_templates.xml have to be shipped. */
    public function testEveryDeclaredEmailTemplateExists(): void
    {
        $templates = self::xml('etc/email_templates.xml')->template;
        self::assertGreaterThan(0, $templates->count());

        foreach ($templates as $template) {
            $area = (string) $template['area'];
            self::assertFileExists(
                \dirname(__DIR__) . '/view/' . $area . '/email/' . (string) $template['file'],
                sprintf('the e-mail template %s is declared but missing', (string) $template['file']),
            );
        }
    }

    /** Every configuration path read by the Config model has a field or a default. */
    public function testEveryConfiguredPathHasADefault(): void
    {
        $defaults = self::xml('etc/config.xml')->default->calmfox_admin_invitation;
        $config = (string) file_get_contents(\dirname(__DIR__) . '/Model/Config.php');

        preg_match_all("/XML_PATH \\. '([a-z_]+)\\/([a-z_]+)'/", $config, $matches, \PREG_SET_ORDER);
        self::assertNotEmpty($matches);

        foreach ($matches as [, $group, $field]) {
            self::assertTrue(
                isset($defaults->{$group}->{$field}),
                sprintf('calmfox_admin_invitation/%s/%s is read but has no default in config.xml', $group, $field),
            );
        }
    }

    /** @return list<string> */
    private static function phpFilesIn(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && 'php' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * Every translatable string the module produces has to be in both translation files.
     *
     * This exists because it is easy to miss a whole class of them: the e-mail templates speak
     * {{trans}} rather than __(), and the configuration screen translates its comments as well
     * as its labels. A missing entry is not an error anywhere, it just quietly shows English.
     */
    public function testEveryTranslatableStringIsInBothTranslationFiles(): void
    {
        $used = self::translatableStrings();
        self::assertNotEmpty($used);

        foreach (['en_US', 'pl_PL'] as $locale) {
            $translated = self::translations($locale);

            foreach ($used as $string) {
                self::assertArrayHasKey(
                    $string,
                    $translated,
                    sprintf('%s.csv has no entry for "%s"', $locale, mb_substr($string, 0, 60)),
                );
            }
        }
    }

    /** The Polish file must actually translate, not repeat the English. */
    public function testThePolishFileIsTranslated(): void
    {
        $untranslated = [];
        foreach (self::translations('pl_PL') as $source => $target) {
            // abbreviations and names are the same in both languages
            if ($source === $target && !\in_array($source, self::SAME_IN_BOTH, true)) {
                $untranslated[] = $source;
            }
        }

        self::assertSame([], $untranslated);
    }

    /** A placeholder dropped in translation shows the reader a literal %1. */
    public function testPlaceholdersSurviveTranslation(): void
    {
        foreach (self::translations('pl_PL') as $source => $target) {
            preg_match_all('/%\\d+|%[a-z_]+(?![a-z_])/', $source, $inSource);
            preg_match_all('/%\\d+|%[a-z_]+(?![a-z_])/', $target, $inTarget);

            sort($inSource[0]);
            sort($inTarget[0]);
            self::assertSame($inSource[0], $inTarget[0], sprintf('placeholders differ for "%s"', mb_substr($source, 0, 50)));
        }
    }

    /** @return list<string> every string the module asks to be translated */
    private static function translatableStrings(): array
    {
        $strings = [];

        foreach (self::filesIn(\dirname(__DIR__), ['php', 'phtml']) as $file) {
            if (str_contains($file, '/tests/')) {
                continue;
            }
            $content = (string) file_get_contents($file);
            preg_match_all('/__\\(\\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', $content, $single);
            preg_match_all('/__\\(\\s*"((?:[^"\\\\]|\\\\.)*)"/', $content, $double);
            foreach ($single[1] as $match) {
                $strings[] = str_replace("\\'", "'", $match);
            }
            foreach ($double[1] as $match) {
                $strings[] = str_replace('\\"', '"', $match);
            }
        }

        // e-mail templates speak {{trans}}
        foreach (self::filesIn(\dirname(__DIR__) . '/view', ['html']) as $file) {
            preg_match_all('/\\{\\{trans\\s+"((?:[^"\\\\]|\\\\.)*)"/', (string) file_get_contents($file), $matches);
            foreach ($matches[1] as $match) {
                $strings[] = $match;
            }
        }

        // the configuration screen translates labels and comments alike
        foreach (self::filesIn(\dirname(__DIR__) . '/etc', ['xml']) as $file) {
            $content = (string) file_get_contents($file);
            preg_match_all('/<label>(.*?)<\\/label>/s', $content, $labels);
            foreach ($labels[1] as $label) {
                $strings[] = trim(preg_replace('/\\s+/', ' ', $label) ?? '');
            }
            preg_match_all('/<field\\b[^>]*translate="([^"]*)"[^>]*>(.*?)<\\/field>/s', $content, $fields, \PREG_SET_ORDER);
            foreach ($fields as [, $translate, $body]) {
                if (!\in_array('comment', explode(' ', $translate), true)) {
                    continue;
                }
                if (preg_match('/<comment>(.*?)<\\/comment>/s', $body, $comment)) {
                    $text = preg_replace('/^<!\\[CDATA\\[|\\]\\]>$/', '', trim($comment[1]));
                    $strings[] = trim(preg_replace('/\\s+/', ' ', (string) $text) ?? '');
                }
            }
        }

        return array_values(array_unique(array_filter($strings)));
    }

    /** @return array<string, string> */
    private static function translations(string $locale): array
    {
        $path = \dirname(__DIR__) . '/i18n/' . $locale . '.csv';
        self::assertFileExists($path);

        $rows = [];
        $handle = fopen($path, 'rb');
        self::assertIsResource($handle);
        while (false !== ($row = fgetcsv($handle, 0, ',', '"', '\\'))) {
            if (\is_array($row) && 2 === \count($row) && \is_string($row[0]) && \is_string($row[1])) {
                $rows[$row[0]] = $row[1];
            }
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param list<string> $extensions
     *
     * @return list<string>
     */
    private static function filesIn(string $directory, array $extensions): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && \in_array($file->getExtension(), $extensions, true)) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /** Strings that read the same in Polish as in English. */
    private const SAME_IN_BOTH = ['Calmfox', '%name,'];
}
