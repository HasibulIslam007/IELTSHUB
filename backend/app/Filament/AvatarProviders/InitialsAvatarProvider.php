<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Renders workspace avatars inline so the admin panel never calls a third-party service.
 *
 * Filament's default provider requests https://ui-avatars.com, which the application
 * Content-Security-Policy blocks and which would disclose staff names to an external host.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $initials = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->map(function (string $segment): string {
                $letters = preg_replace('/^[^\p{L}\p{N}]+/u', '', $segment);

                return filled($letters) ? mb_substr($letters, 0, 1) : '';
            })
            ->filter()
            ->take(2)
            ->join(' ');

        $label = htmlspecialchars($initials === '' ? '?' : $initials, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img" aria-label="User avatar">'
            .'<rect width="64" height="64" rx="32" fill="#09090b"/>'
            .'<text x="32" y="41" text-anchor="middle" font-family="Inter, ui-sans-serif, system-ui, sans-serif" font-size="26" font-weight="600" fill="#ffffff">'.$label.'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
