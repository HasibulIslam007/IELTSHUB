<?php

namespace App\Services;

use App\Models\Exam;

class SeoService
{
    /** @return array<string, array{title: string, description: string, public: bool}> */
    public function pages(): array
    {
        return json_decode(file_get_contents(base_path('../shared/seo.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array{title: string, description: string, public: bool, canonical: string} */
    public function metadata(string $path): array
    {
        $path = '/'.trim($path, '/');
        $page = $this->pages()[$path] ?? [
            'title' => 'Page not found', 'description' => 'Return to the IELTS practice library.', 'public' => false,
        ];
        if (preg_match('#^/library/(\d+)$#', $path, $matches)) {
            $exam = Exam::where('status', 'published')->find($matches[1]);
            if ($exam) {
                $page = ['title' => $exam->title, 'description' => $exam->description, 'public' => true];
            }
        } elseif (preg_match('#^/(attempts|results|reset-password)/#', $path, $matches)) {
            $page['title'] = ['attempts' => 'Your Practice Session', 'results' => 'Your Practice Results', 'reset-password' => 'Choose a New Password'][$matches[1]];
        }
        if ($path !== '/') {
            $page['title'] .= ' | IELTS Practice Hub';
        }

        return $page + ['canonical' => rtrim(config('app.url'), '/').$path];
    }
}
