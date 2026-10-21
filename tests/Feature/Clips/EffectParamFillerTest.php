<?php

namespace Tests\Feature\Clips;

use App\Services\Clips\EffectLibrary;
use App\Services\Clips\EffectParamFiller;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Clips\Store\EffectStore;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EffectParamFillerTest extends TestCase
{
    public function test_it_fills_only_the_missing_inputs_from_the_voiceover(): void
    {
        app(EffectStore::class)->create([
            'slug' => 'listing-card', 'status' => EffectRecord::STATUS_ACTIVE, 'enabled' => true,
            'prompt' => 'x', 'display_name' => 'Listing', 'description' => 'Listing card', 'tsx' => 'export default () => null;',
            'param_schema' => '{ "src"?: image, "avatar"?: image, "logo"?: image, "price"?: string, "title"?: string }',
            'sample_params' => ['price' => '450.000 €', 'title' => 'T3 COM VARANDA'],
        ]);
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 3, 'layers' => [['type' => 'listing-card', 'params' => ['src' => 'img_house', 'title' => 'KEEP ME']]]],
            ['start' => 3, 'end' => 6, 'layers' => [['type' => 'image-reveal', 'params' => ['src' => 'img_house']]]],
        ]];

        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['s0l0' => [
            'price' => '740.000 €', 'title' => 'OVERWRITE', 'avatar' => 'img_agent', 'logo' => 'img_invented',
        ]])]]])]);

        $out = app(EffectParamFiller::class)->fill($plan, [
            'text' => '9 hectares em Santarém, 740 mil euros. Fala com o Manuel Reis.',
            'words' => [['word' => '9', 'start' => 0, 'end' => 0.3]],
        ], [
            ['id' => 'img_house', 'description' => 'moradia'],
            ['id' => 'img_agent', 'description' => 'retrato do agente'],
        ], app(EffectLibrary::class), 'Agente: Manuel Reis, IAD Portugal');

        $params = $out['scenes'][0]['layers'][0]['params'];
        $this->assertSame('740.000 €', $params['price']);          // filled
        $this->assertSame('KEEP ME', $params['title']);             // already set — untouched
        $this->assertSame('img_agent', $params['avatar']);          // a real image id
        $this->assertArrayNotHasKey('logo', $params);               // invented id refused
        $this->assertSame($plan['scenes'][1], $out['scenes'][1]);   // built-ins untouched
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'Agente: Manuel Reis'));
    }

    /** An effect previewed with a sample text takes its headline from the layer text — fill that too. */
    public function test_it_fills_the_layer_text_of_an_effect_that_shows_a_title(): void
    {
        app(EffectStore::class)->create([
            'slug' => 'blur-title', 'status' => EffectRecord::STATUS_ACTIVE, 'enabled' => true,
            'prompt' => 'x', 'display_name' => 'Blur', 'description' => 'blurred photo with a title', 'tsx' => 'export default () => null;',
            'param_schema' => '{ "src"?: string, "blur"?: number }', 'sample_text' => 'T3 com varanda em Alvalade', 'sample_params' => ['blur' => 16],
        ]);
        $plan = ['scenes' => [['start' => 0, 'end' => 3, 'layers' => [['type' => 'blur-title', 'text' => null, 'params' => ['src' => 'img_a', 'blur' => 14]]]]]];

        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['s0l0' => [
            'on_screen_text' => 'Moradia com alpendre',
        ]])]]])]);

        $out = app(EffectParamFiller::class)->fill($plan, ['text' => 'A moradia tem um alpendre.', 'words' => []], [['id' => 'img_a', 'description' => 'casa']], app(EffectLibrary::class));

        $layer = $out['scenes'][0]['layers'][0];
        $this->assertSame('Moradia com alpendre', $layer['text']);
        $this->assertSame(14, $layer['params']['blur']);                 // set numbers untouched
        $this->assertArrayNotHasKey('on_screen_text', $layer['params']);
    }

    /** An outro's set-but-incomplete text is fixed from what the video already shows (the intro's phone). */
    public function test_an_outro_is_reviewed_with_the_facts_already_on_screen(): void
    {
        foreach (['intro-card' => '{ "phone"?: string }', 'agent-card' => '{ "src"?: string, "buttons"?: string, "phone"?: string }'] as $slug => $schema) {
            app(EffectStore::class)->create([
                'slug' => $slug, 'status' => EffectRecord::STATUS_ACTIVE, 'enabled' => true, 'prompt' => 'x', 'display_name' => $slug,
                'description' => $slug, 'param_schema' => $schema, 'tsx' => 'export default () => null;',
                'sample_params' => ['phone' => '+351 900 000 000', 'buttons' => 'Ligue +351 900 000 000|Marque a sua visita'],
            ]);
        }
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 3, 'layers' => [['type' => 'intro-card', 'params' => ['phone' => '+351 969 449 777', 'buttons' => 'x']]]],
            ['start' => 3, 'end' => 6, 'layers' => [['type' => 'agent-card', 'params' => ['src' => 'img_a', 'phone' => '+351 969 449 777', 'buttons' => 'Ligue|Marque a sua visita']]]],
        ]];

        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['s1l0' => [
            'buttons' => ['Marque a sua visita', 'Ligue +351 969 449 777'], 'phone' => '+351 969 449 777', 'src' => 'img_b',
        ]])]]])]);

        $out = app(EffectParamFiller::class)->fill($plan, ['text' => 'x', 'words' => []], [['id' => 'img_a'], ['id' => 'img_b']], app(EffectLibrary::class), '', ['agent-card']);

        $card = $out['scenes'][1]['layers'][0]['params'];
        $this->assertSame(['Marque a sua visita', 'Ligue +351 969 449 777'], $card['buttons']); // completed though it was set — a list stays a list
        $this->assertSame('img_a', $card['src']);                                            // a set picture is never swapped
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'FACTS ALREADY ON SCREEN')
            && str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), '969 449 777'));
    }

    public function test_nothing_missing_means_no_call(): void
    {
        Http::fake();
        $plan = ['scenes' => [['start' => 0, 'end' => 2, 'layers' => [['type' => 'image-reveal', 'params' => ['src' => 'a']]]]]];

        $this->assertSame($plan, app(EffectParamFiller::class)->fill($plan, [], [], app(EffectLibrary::class)));
        Http::assertNothingSent();
    }
}
