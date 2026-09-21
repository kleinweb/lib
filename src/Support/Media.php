<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Support;

final class Media
{
    /**
     * Name of the object storage bucket containing offloaded media.
     *
     * @see KLEINWEB_SITE_MEDIA_BUCKET
     */
    public static function bucketName(): ?string
    {
        return defined('KLEINWEB_SITE_MEDIA_BUCKET')
            ? constant('KLEINWEB_SITE_MEDIA_BUCKET')
            : null;
    }

    /**
     * PCRE2 pattern matching an offloaded media resource.
     */
    public static function bucketUrlPattern(): ?string
    {
        if (!self::bucketName()) {
            return null;
        }

        return '#https?://(?:' . self::bucketName() . ')\.s3\.amazonaws\.com/(?:.*)$#i';
    }
}
