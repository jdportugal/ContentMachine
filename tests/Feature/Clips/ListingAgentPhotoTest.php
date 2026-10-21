<?php

namespace Tests\Feature\Clips;

use App\Jobs\Clips\ImportListingJob;
use App\Livewire\ClipsAnimados;
use App\Services\Clips\Api\ElevenLabsVoiceoverService;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\Store\EffectStore;
use App\Services\Projects\ProjectResources;
use App\Services\RealEstate\AgencyLogoFinder;
use App\Services\RealEstate\ListingScraper;
use App\Services\RealEstate\ListingScriptWriter;
use App\Services\RealEstate\PropertyVideoAnalyzer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ListingAgentPhotoTest extends TestCase
{
    /** A photo uploaded with the listing URL is the agent's portrait, not the listing's. */
    public function test_an_uploaded_agent_photo_replaces_the_listings(): void
    {
        Queue::fake();
        $disk = Storage::fake(config('contentmachine.clips.disk'));
        $png = function (int $color) {
            $i = imagecreatetruecolor(4, 4);
            imagefill($i, 0, 0, $color);
            ob_start();
            imagepng($i);

            return ob_get_clean();
        };

        Livewire::test(ClipsAnimados::class)
            ->set('listingUrl', 'https://www.iadportugal.pt/anuncio/x/r1')
            ->set('listingAgentPhoto', UploadedFile::fake()->createWithContent('me.png', $png(0xFF0000)))
            ->call('importarAnuncio')
            ->assertHasNoErrors();
        $clip = app(ClipStore::class)->all()->first();
        $own = $disk->get($clip->meta['agent_photo']);

        Http::fake(['*' => Http::response($png(0x00FF00))]);
        $this->mock(ListingScraper::class)->shouldReceive('scrape')->andReturn([
            'title' => 'T2', 'description' => 'd', 'url' => 'u', 'price' => 1, 'details' => [], 'photos' => ['https://x/house.png'],
            'agent' => ['name' => 'Ana', 'phone' => '1', 'photo' => 'https://x/listing-agent.png'], 'agency' => 'IAD Portugal',
        ]);
        $this->mock(ListingScriptWriter::class)->shouldReceive('write')->andReturn([
            'title' => 'T2', 'flags' => [], 'agent_gender' => 'f', 'voiceover' => 'x', 'hook' => '', 'scenes' => [], 'message' => '',
        ]);
        $this->mock(ElevenLabsVoiceoverService::class)->shouldReceive('synthesizeWith');
        $this->mock(AgencyLogoFinder::class)->shouldReceive('find')->andReturn(null);
        config(['contentmachine.real_estate.voices.f.takes' => 1]);

        app()->call([new ImportListingJob($clip->id, $clip->meta['listing']['url']), 'handle']);

        $portrait = collect(app(ClipStore::class)->findOrFail($clip->id)->images)->first(fn ($i) => str_starts_with($i['description'] ?? '', 'Retrato'));
        $this->assertSame($own, $disk->get($portrait['path']));
    }

    /** The after-intro line typed with the listing goes right after the intro sentence; the project's is the default. */
    public function test_the_after_intro_line_is_placed_in_the_script(): void
    {
        Queue::fake();
        $disk = Storage::fake(config('contentmachine.clips.disk'));
        app(EffectStore::class)->putAfterIntroLine('Se queres vender casa, fala com o Nuno.');

        $form = Livewire::test(ClipsAnimados::class)->assertSet('listingAfterIntroLine', 'Se queres vender casa, fala com o Nuno.');
        $form->set('listingUrl', 'https://www.iadportugal.pt/anuncio/x/r1')
            ->set('listingAfterIntro', UploadedFile::fake()->create('a.mp4', 10, 'video/mp4'))
            ->set('listingAfterIntroLine', 'Se queres vender ou comprar casa, fala com o Nuno')
            ->call('importarAnuncio')
            ->assertHasNoErrors();
        $clip = app(ClipStore::class)->all()->first();

        $img = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($img);
        Http::fake(['*' => Http::response(ob_get_clean())]);
        $this->mock(ListingScraper::class)->shouldReceive('scrape')->andReturn([
            'title' => 'T2', 'description' => 'd', 'url' => 'u', 'price' => 1, 'details' => [], 'photos' => ['https://x/house.png'],
            'agent' => ['name' => 'Nuno', 'phone' => '1', 'photo' => null], 'agency' => 'IAD Portugal',
        ]);
        $this->mock(ListingScriptWriter::class)->shouldReceive('write')->andReturn([
            'title' => 'T2', 'flags' => [], 'agent_gender' => 'm', 'voiceover' => 'Um T2 em Mafra. Tem vista mar.', 'hook' => '', 'scenes' => [], 'message' => '',
        ]);
        $this->mock(ElevenLabsVoiceoverService::class)->shouldReceive('synthesizeWith');
        $this->mock(AgencyLogoFinder::class)->shouldReceive('find')->andReturn(null);

        config(['contentmachine.real_estate.voices.m.takes' => 1]);
        app()->call([new ImportListingJob($clip->id, 'u'), 'handle']);

        $this->assertSame('Um T2 em Mafra. Se queres vender ou comprar casa, fala com o Nuno. Tem vista mar.', $this->voiceoverOf($clip->id));
    }

    /** One agent per project: its details, photo, logo, extra images and music stand in for the listing's. */
    public function test_project_resources_stand_in_for_the_listings(): void
    {
        Queue::fake();
        $disk = Storage::fake(config('contentmachine.clips.disk'));
        $png = function (int $color) {
            $i = imagecreatetruecolor(4, 4);
            imagefill($i, 0, 0, $color);
            ob_start();
            imagepng($i);

            return ob_get_clean();
        };
        $file = function (int $color) use ($png) {
            $f = tempnam(sys_get_temp_dir(), 'res');
            file_put_contents($f, $png($color));

            return $f;
        };
        $resources = app(ProjectResources::class);
        $resources->putAgent(['name' => 'Nuno Narciso', 'phone' => '912', 'email' => 'nuno@iad.pt', 'licence' => 'AMI 1234']);
        $resources->putMusic('my-track.mp3');
        $resources->putFile('agent-photo', $file(0xFF0000), 'png');
        $resources->putFile('logo', $file(0x0000FF), 'png');
        $resources->addImage($file(0xFFFF00), 'png', 'Nuno com as chaves');

        Livewire::test(ClipsAnimados::class)->set('listingUrl', 'https://www.iadportugal.pt/anuncio/x/r1')->call('importarAnuncio')->assertHasNoErrors();
        $clip = app(ClipStore::class)->all()->first();

        Http::fake(['*' => Http::response($png(0x00FF00))]);
        $this->mock(ListingScraper::class)->shouldReceive('scrape')->andReturn([
            'title' => 'T2', 'description' => 'd', 'url' => 'u', 'price' => 1, 'details' => [], 'photos' => ['https://x/house.png'],
            'agent' => ['name' => 'Ana', 'phone' => '1', 'photo' => 'https://x/listing-agent.png'], 'agency' => 'IAD Portugal',
        ]);
        $this->mock(ListingScriptWriter::class)->shouldReceive('write')
            ->withArgs(fn ($listing) => $listing['agent']['name'] === 'Nuno Narciso')
            ->andReturn(['title' => 'T2', 'flags' => [], 'agent_gender' => 'm', 'voiceover' => 'x', 'hook' => '', 'scenes' => [], 'message' => '']);
        $this->mock(ElevenLabsVoiceoverService::class)->shouldReceive('synthesizeWith');
        $this->mock(AgencyLogoFinder::class)->shouldNotReceive('find');
        config(['contentmachine.real_estate.voices.m.takes' => 1]);

        app()->call([new ImportListingJob($clip->id, 'u'), 'handle']);

        $clip = app(ClipStore::class)->findOrFail($clip->id);
        $images = collect($clip->images);
        $this->assertSame($png(0xFF0000), $disk->get($images->first(fn ($i) => str_starts_with($i['description'], 'Retrato'))['path']));
        $this->assertSame($png(0x0000FF), $disk->get($images->firstWhere('name', 'logo.png')['path']));
        $this->assertSame($png(0xFFFF00), $disk->get($images->firstWhere('description', 'Nuno com as chaves')['path']));
        $this->assertSame(['Nuno Narciso', '912', 'nuno@iad.pt', 'AMI 1234'], [$clip->meta['listing']['agent'], $clip->meta['listing']['phone'], $clip->meta['listing']['email'], $clip->meta['listing']['licence']]);
        $this->assertSame('my-track.mp3', $clip->meta['musica']);
        $this->assertStringContainsString('Nuno com as chaves', $clip->meta['instructions']);
        $this->assertStringContainsString('AMI 1234', $clip->meta['instructions']);
    }

    /** A licence is shown only when given (the clip, the project) or read from the listing — never made up. */
    public function test_a_licence_is_only_shown_when_known(): void
    {
        $plan = ['scenes' => [['layers' => [['type' => 'agente-revelacao', 'params' => ['ami' => 'AMI 99999', 'licence' => '4321', 'phone' => '912', 'legal' => 'IAD PORTUGAL S.A. | AMI: 99999']]]]]];

        $none = \App\Services\Clips\EffectParamFiller::semLicencaInventada($plan, "Agent: Ana, phone 912 345 678. Preço 350.000 €");
        $this->assertSame(['ami' => '', 'licence' => '', 'phone' => '912', 'legal' => 'IAD PORTUGAL S.A.'], $none['scenes'][0]['layers'][0]['params']);

        $given = \App\Services\Clips\EffectParamFiller::semLicencaInventada($plan, "Show the licence number (AMI 99999) small on the outro card.");
        $this->assertSame('AMI 99999', $given['scenes'][0]['layers'][0]['params']['ami']);
        $this->assertSame('IAD PORTUGAL S.A. | AMI: 99999', $given['scenes'][0]['layers'][0]['params']['legal']);
        $this->assertSame('', $given['scenes'][0]['layers'][0]['params']['licence']);
    }

    /** By hand: the after-intro and its line, and the licence, start as the project's and go with the clip. */
    public function test_a_hand_made_animation_takes_the_after_intro_and_licence(): void
    {
        Queue::fake();
        Storage::fake(config('contentmachine.clips.disk'));
        app(\App\Services\Clips\Store\EffectStore::class)->putAfterIntroLine('Fala com o Nuno');
        app(ProjectResources::class)->putAgent(['licence' => 'AMI 1234']);

        Livewire::test(ClipsAnimados::class)
            ->call('escolherTipo', 'animation')
            ->assertSet('afterIntroLine', 'Fala com o Nuno')
            ->assertSet('licence', 'AMI 1234')
            ->set('text', 'Uma casa em Mafra. Tem vista mar.')
            ->set('afterIntro', UploadedFile::fake()->create('a.mp4', 10, 'video/mp4'))
            ->call('submitAnimation')
            ->assertHasNoErrors();

        $clip = app(ClipStore::class)->all()->first();
        $this->assertSame('Uma casa em Mafra. Fala com o Nuno. Tem vista mar.', $clip->source_text);
        $this->assertNotEmpty($clip->meta['after_intro']);
        $this->assertStringContainsString('AMI 1234', $clip->meta['instructions']);
    }

    private function voiceoverOf(string $id): ?string
    {
        return app(ClipStore::class)->findOrFail($id)->meta['listing']['voiceover'] ?? null;
    }

    /** The walkthrough put in the after-intro field (and the brand clip as the property video) is put right. */
    public function test_swapped_videos_are_put_right_and_photos_mixed_on_request(): void
    {
        Queue::fake();
        $disk = Storage::fake(config('contentmachine.clips.disk'));
        $video = function (int $seconds) {
            $f = sys_get_temp_dir().'/'.uniqid().'.mp4';
            exec('ffmpeg -v error -y -f lavfi -i color=c=black:s=64x64:d='.$seconds.' -pix_fmt yuv420p '.escapeshellarg($f));

            $upload = UploadedFile::fake()->create(basename($f), 1, 'video/mp4');
            file_put_contents($upload->getRealPath(), file_get_contents($f));   // real video bytes, so it can be measured

            return $upload;
        };

        Livewire::test(ClipsAnimados::class)
            ->set('listingUrl', 'https://www.iadportugal.pt/anuncio/x/r1')
            ->set('listingVideo', $video(5))         // the logo animation
            ->set('listingAfterIntro', $video(30))   // the walkthrough
            ->set('listingMixPhotos', true)
            ->call('importarAnuncio')
            ->assertHasNoErrors();

        $meta = app(ClipStore::class)->all()->first()->meta;
        $this->assertTrue($meta['videos_swapped']);
        $this->assertEqualsWithDelta(30, PropertyVideoAnalyzer::probe($disk->path($meta['listing_video']))['duration'], 0.5);
        $this->assertEqualsWithDelta(5, PropertyVideoAnalyzer::probe($disk->path($meta['after_intro']))['duration'], 0.5);
        $this->assertTrue($meta['mix_photos']);
    }
}
