<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_metadata_is_specific_and_private_routes_are_not_indexed(): void
    {
        config(['app.url' => 'https://practice.example.test']);
        $this->get('/library')->assertOk()
            ->assertSee('<title>IELTS Practice Library | IELTS Practice Hub</title>', false)
            ->assertSee('https://practice.example.test/library', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('name="twitter:card"', false);
        $this->get('/dashboard')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('content="noindex, nofollow"', false);
    }

    public function test_security_policy_preserves_recording_and_limits_script_execution(): void
    {
        $response = $this->get('/')->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=(self)')
            ->assertHeaderMissing('Strict-Transport-Security');
        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("media-src 'self' blob:", $policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $admin = User::factory()->create(['role' => 'admin']);
        $workspace = $this->actingAs($admin)->get('/admin')->assertOk();
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' 'unsafe-eval'", $workspace->headers->get('Content-Security-Policy'));
    }

    public function test_sitemap_contains_published_tests_only_and_robots_points_to_it(): void
    {
        config(['app.url' => 'https://practice.example.test']);
        $published = Exam::create(['slug' => 'published', 'title' => 'Published exercise', 'description' => 'Original demo', 'skill' => 'reading', 'test_type' => 'academic', 'status' => 'published', 'draft' => []]);
        $draft = Exam::create(['slug' => 'draft', 'title' => 'Private draft', 'description' => 'Unpublished', 'skill' => 'reading', 'test_type' => 'academic', 'status' => 'draft', 'draft' => []]);
        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $urls = array_map('strval', $xml->xpath('//*[local-name()="loc"]'));
        $this->assertContains('https://practice.example.test/library/'.$published->id, $urls);
        $this->assertNotContains('https://practice.example.test/library/'.$draft->id, $urls);
        $this->assertNotContains('https://practice.example.test/dashboard', $urls);
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: https://practice.example.test/sitemap.xml');
        $this->get('/library/'.$published->id)->assertSee('Published exercise | IELTS Practice Hub');
    }

    public function test_openapi_document_is_downloadable_and_covers_all_versioned_api_routes(): void
    {
        $response = $this->get('/api-docs')->assertOk()->assertHeader('Content-Type', 'text/yaml; charset=UTF-8');
        $spec = file_get_contents($response->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString('openapi: 3.1.0', $spec);
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }
            $path = '/'.substr($route->uri(), strlen('api/v1/'));
            if (str_contains($path, '{action}')) {
                foreach (['submit', 'pause', 'resume'] as $action) {
                    $this->assertStringContainsString(str_replace('{action}', $action, $path).':', $spec);
                }
            } else {
                $this->assertStringContainsString($path.':', $spec);
            }
        }
    }
}
