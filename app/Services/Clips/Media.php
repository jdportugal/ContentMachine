<?php

namespace App\Services\Clips;

/**
 * A clip's visual media is either an image or a video; both flow through the same
 * `images` array and image-reveal layers, and both display inset (contained) in the
 * render. This is the single place that decides which a file is, by extension.
 */
final class Media
{
    public const IMAGE_EXT = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'avif', 'bmp'];

    public const VIDEO_EXT = ['mp4', 'mov', 'webm', 'm4v'];

    public static function isVideo(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::VIDEO_EXT, true);
    }

    /** The `mimes:` rule body accepting every supported image and video type. */
    public static function mimesRule(): string
    {
        return 'mimes:'.implode(',', array_merge(self::IMAGE_EXT, self::VIDEO_EXT));
    }
}
