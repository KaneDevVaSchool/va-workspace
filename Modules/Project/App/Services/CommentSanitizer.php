<?php

namespace Modules\Project\App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitize nội dung HTML của bình luận (rich-text + mention), khuôn theo
 * Modules\Social\App\Services\SocialContentSanitizer nhưng bỏ hẳn phần
 * data-sticker/data-hashtag (Task/Project không cần). Dùng DefinitionID
 * riêng để không đụng cache definition của Social.
 */
class CommentSanitizer
{
    private HTMLPurifier $purifier;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,h2,h3,ul,ol,li,a[href],span[style|class|data-mention-id]');
        $config->set('CSS.AllowedProperties', 'color,font-size');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('HTML.TargetBlank', true);
        $config->set('AutoFormat.RemoveEmpty', true);

        $cachePath = storage_path('app/htmlpurifier');
        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $config->set('Cache.SerializerPath', $cachePath);
        $config->set('HTML.DefinitionID', 'project-comment-content');
        $config->set('HTML.DefinitionRev', 1);

        if ($def = $config->maybeGetRawHTMLDefinition()) {
            $def->addAttribute('span', 'data-mention-id', 'Number');
        }

        $this->purifier = new HTMLPurifier($config);
    }

    public function sanitize(string $html): string
    {
        return $this->purifier->purify($html);
    }

    /** @return list<int> */
    public function mentionIds(string $html): array
    {
        if ($html === '' || ! preg_match_all('/data-mention-id=["\'](\d+)["\']/', $html, $matches)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $matches[1])));
    }
}
