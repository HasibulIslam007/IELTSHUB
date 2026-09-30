<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Services\SeoService;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(SeoService $seo): Response
    {
        $base = rtrim(config('app.url'), '/');
        $urls = collect($seo->pages())->filter(fn (array $page): bool => $page['public'])->keys()
            ->map(fn (string $path): array => ['loc' => $base.$path]);
        Exam::where('status', 'published')->select(['id', 'updated_at'])->orderBy('id')->chunkById(250, function ($exams) use ($urls, $base): void {
            foreach ($exams as $exam) {
                $urls->push(['loc' => $base.'/library/'.$exam->id, 'lastmod' => $exam->updated_at->toAtomString()]);
            }
        });
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            $xml .= '<url><loc>'.htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8').'</loc>';
            if (isset($url['lastmod'])) {
                $xml .= '<lastmod>'.$url['lastmod'].'</lastmod>';
            }
            $xml .= '</url>';
        }

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        return response("User-agent: *\nDisallow: /admin\nDisallow: /api/\nDisallow: /attempts/\nDisallow: /results/\nSitemap: ".rtrim(config('app.url'), '/')."/sitemap.xml\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
