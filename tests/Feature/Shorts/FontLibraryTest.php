<?php

namespace Tests\Feature\Shorts;

use App\Services\Shorts\FontLibrary;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Design System's body face is what the animated clips caption with, and it
 * is almost never one of the bundled ones. libass reads font FILES, so the face
 * has to be fetched as TTF and cached next to the bundled ones.
 */
class FontLibraryTest extends TestCase
{
    private string $bundled;

    private string $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $raiz = sys_get_temp_dir().'/cm-fonts-'.bin2hex(random_bytes(4));
        $this->bundled = "$raiz/bundled";
        $this->cache = "$raiz/cache";
        File::makeDirectory($this->bundled, 0775, true);
        file_put_contents($this->bundled.'/Anton-Regular.ttf', 'ANTON');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->bundled));
        parent::tearDown();
    }

    private function library(): FontLibrary
    {
        return new FontLibrary($this->bundled, $this->cache);
    }

    /** A believable TTF: the sfnt signature plus enough bytes to pass the size check. */
    private function ttf(): string
    {
        return "\x00\x01\x00\x00".str_repeat('F', 2048);
    }

    private function fakeGoogle(): void
    {
        Http::fake([
            'fonts.googleapis.com/*' => Http::response('@font-face { src: url(https://fonts.gstatic.com/s/x/v1/abc.ttf); }'),
            'fonts.gstatic.com/*' => Http::response($this->ttf()),
        ]);
    }

    public function test_it_fetches_the_family_and_keeps_the_bundled_faces_beside_it(): void
    {
        $this->fakeGoogle();

        $dir = $this->library()->dirFor('Space Grotesk');

        $this->assertSame($this->cache, $dir);
        $this->assertFileExists("$dir/space-grotesk.ttf");
        // libass gets ONE fontsdir, so losing the bundled faces would break the
        // other styles a user can pick.
        $this->assertFileExists("$dir/Anton-Regular.ttf");
    }

    public function test_it_asks_google_for_a_ttf_of_the_regular_weight(): void
    {
        $this->fakeGoogle();

        $this->library()->dirFor('Space Grotesk');

        Http::assertSent(function (Request $r) {
            if (! str_contains($r->url(), 'fonts.googleapis.com')) {
                return false;
            }

            // A full modern UA is served woff2 and an ancient IE one eot; only a
            // bare "Mozilla/5.0" gets TTF. Weight 400, because a heavier face names
            // its family after itself ("Space Grotesk SemiBold") and stops matching.
            return $r->header('User-Agent') === ['Mozilla/5.0']
                && str_contains($r->url(), 'css2')
                && str_contains($r->url(), 'wght@400');
        });
    }

    public function test_it_only_fetches_once(): void
    {
        $this->fakeGoogle();

        $this->library()->dirFor('Space Grotesk');
        $this->library()->dirFor('Space Grotesk');

        Http::assertSentCount(2); // the stylesheet + the font, from the first call only
    }

    public function test_a_bundled_family_is_never_fetched(): void
    {
        Http::fake();

        $this->assertSame($this->bundled, $this->library()->dirFor('Anton'));
        Http::assertNothingSent();
    }

    public function test_junk_is_rejected_rather_than_cached(): void
    {
        // An HTML error page, or a woff2 — anything libass cannot read.
        Http::fake([
            'fonts.googleapis.com/*' => Http::response('@font-face { src: url(https://fonts.gstatic.com/s/x/v1/abc.ttf); }'),
            'fonts.gstatic.com/*' => Http::response('<html>nope</html>'),
        ]);

        $this->assertSame($this->bundled, $this->library()->dirFor('Space Grotesk'));
        $this->assertFileDoesNotExist($this->cache.'/space-grotesk.ttf');
    }

    public function test_a_network_failure_falls_back_instead_of_breaking_the_render(): void
    {
        Http::fake(fn () => throw new \RuntimeException('offline'));

        $this->assertSame($this->bundled, $this->library()->dirFor('Space Grotesk'));
    }

    public function test_no_family_means_the_bundled_directory(): void
    {
        Http::fake();

        $this->assertSame($this->bundled, $this->library()->dirFor(null));
        $this->assertSame($this->bundled, $this->library()->dirFor('  '));
        Http::assertNothingSent();
    }
}
