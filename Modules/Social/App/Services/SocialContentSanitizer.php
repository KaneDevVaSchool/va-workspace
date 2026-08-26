<?php

namespace Modules\Social\App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

class SocialContentSanitizer
{
    private HTMLPurifier $purifier;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,h1,h2,h3,ul,ol,li,a[href],span[style|class|data-mention-id|data-sticker|data-hashtag]');
        $config->set('CSS.AllowedProperties', 'color,font-size');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('HTML.TargetBlank', true);
        $config->set('AutoFormat.RemoveEmpty', true);

        $cachePath = storage_path('app/htmlpurifier');
        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $config->set('Cache.SerializerPath', $cachePath);
        $config->set('HTML.DefinitionID', 'social-content-stickers');
        $config->set('HTML.DefinitionRev', 2);

        if ($def = $config->maybeGetRawHTMLDefinition()) {
            $def->addAttribute('span', 'data-mention-id', 'Number');
            $def->addAttribute('span', 'data-sticker', 'Text');
            $def->addAttribute('span', 'data-hashtag', 'Text');
        }

        $this->purifier = new HTMLPurifier($config);
    }

    public function sanitize(string $html): string
    {
        return $this->filterStickerIds($this->purifier->purify($html));
    }

    private function filterStickerIds(string $html): string
    {
        return (string) preg_replace_callback(
            '/<span\b([^>]*)>/i',
            function (array $match): string {
                $attrs = $match[1];
                if (! preg_match('/\sdata-sticker=(["\'])([^"\']*)\1/i', $attrs, $idMatch)) {
                    return $match[0];
                }

                $id = strtolower($idMatch[2]);
                if (! preg_match('/^[0-9a-f]{2,8}(?:_[0-9a-f]{2,8}){0,12}$/', $id)) {
                    $attrs = preg_replace('/\sdata-sticker=(["\'])([^"\']*)\1/i', '', $attrs) ?? $attrs;

                    return '<span'.$attrs.'>';
                }

                return '<span'.$attrs.'>';
            },
            $html
        );
    }

    /**
     * @return list<int>
     */
    public function mentionIds(string $html): array
    {
        if ($html === '' || ! preg_match_all('/data-mention-id=["\'](\d+)["\']/', $html, $matches)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $matches[1])));
    }

    public function excerpt(string $html, int $limit = 140): string
    {
        $plain = trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
        if ($plain === '') {
            return '';
        }

        if (mb_strlen($plain) <= $limit) {
            return $plain;
        }

        return rtrim(mb_substr($plain, 0, $limit)).'…';
    }
}
