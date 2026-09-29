<?php

use App\Services\Compendium\CompendiumSourceHtmlSanitizer;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CompendiumSourceHtmlSanitizerTest extends CIUnitTestCase
{
    public function testDropsExecutableMarkupAndRewritesLocalDocumentLinks(): void
    {
        $html = '<script>alert(1)</script><p onclick="steal()">Tekst '
            . '<a href="7522.html#section-1" style="color:red">Reikland</a>'
            . '<a href="javascript:alert(2)">zły</a></p><iframe src="https://example.test"></iframe>';
        $result = (new CompendiumSourceHtmlSanitizer())->sanitize($html);

        $this->assertStringNotContainsString('script', $result);
        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringNotContainsString('iframe', $result);
        $this->assertStringContainsString('data-compendium-source-id="warhammerpl:7522"', $result);
        $this->assertStringContainsString('data-compendium-anchor="section-1"', $result);
    }

    public function testSelectsOnlyExplicitlyRevealedSections(): void
    {
        $sanitizer = new CompendiumSourceHtmlSanitizer();
        $html = '<p>Wstęp tajny</p><h3 id="public">Publiczne</h3><p>Do ujawnienia</p>'
            . '<h3 id="secret">Sekretne</h3><p>Nie ujawniać</p>';
        $selected = $sanitizer->selectSections($html, ['public']);

        $this->assertStringContainsString('Do ujawnienia', $selected);
        $this->assertStringNotContainsString('Wstęp tajny', $selected);
        $this->assertStringNotContainsString('Nie ujawniać', $selected);
    }
}
