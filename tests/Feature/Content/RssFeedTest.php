<?php

namespace Tests\Feature\Content;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * RSS-Feed der Beiträge (Pflichtenheft 11.1).
 */
class RssFeedTest extends TestCase
{
    use RefreshDatabase;

    private function newPost(string $title, array $attrs = []): Post
    {
        return Post::query()->create([
            'title' => $title, 'slug' => str($title)->slug()->value(), 'body' => '<p>Text zu '.$title.' &amp; mehr</p>',
            'teaser' => '', 'status' => 'published', 'published_at' => now()->subDay(), ...$attrs,
        ]);
    }

    #[TestDox('/feed liefert gültiges RSS mit veröffentlichten Beiträgen, neueste zuerst – ohne Entwürfe')]
    public function test_main_feed(): void
    {
        $this->newPost('Älter', ['published_at' => now()->subWeek(), 'teaser' => 'Kurz & knapp']);
        $this->newPost('Neuer');
        $this->newPost('Entwurf', ['status' => 'draft']);
        $this->newPost('Geplant', ['published_at' => now()->addDay()]);
        $this->newPost('Bolivien-Meldung', ['section' => 'bolivien']);

        $response = $this->get('/feed')->assertOk();
        $this->assertStringStartsWith('application/rss+xml', $response->headers->get('content-type'));

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $titles = array_map('strval', $xml->xpath('//item/title'));
        $this->assertSame(['Neuer', 'Bolivien-Meldung', 'Älter'], array_values(array_intersect($titles, ['Neuer', 'Bolivien-Meldung', 'Älter'])));
        $this->assertNotContains('Entwurf', $titles);
        $this->assertNotContains('Geplant', $titles);

        $aelter = $xml->xpath('//item[title="Älter"]')[0];
        $this->assertSame(url('/aktuelles/'.Post::query()->where('title', 'Älter')->value('slug')), (string) $aelter->link);
        $this->assertSame('Kurz & knapp', (string) $aelter->description);
        $this->assertSame('Text zu Neuer & mehr', (string) $xml->xpath('//item[title="Neuer"]')[0]->description);
    }

    #[Group('dv-trier')]

    #[TestDox('Sektionsfeed (/bolivien/feed) nur mit Beiträgen der Sektion; Autodiscovery im Kopf und Link auf „Aktuelles“')]
    public function test_section_feed_and_discovery(): void
    {
        $this->newPost('Allgemein');
        $this->newPost('Aus Cochabamba', ['section' => 'bolivien']);

        $xml = simplexml_load_string($this->get('/bolivien/feed')->assertOk()->getContent());
        $this->assertSame(['Aus Cochabamba'], array_map('strval', $xml->xpath('//item/title')));
        $this->assertStringContainsString('Bolivienpartnerschaft', (string) $xml->channel->title);

        $this->get('/aktuelles')->assertOk()
            ->assertSee('<link rel="alternate" type="application/rss+xml"', false)
            ->assertSee('href="'.url('/feed').'"', false);
    }
}
