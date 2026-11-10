<?php

namespace ErnestDefoe\StickerBook\Storage;

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;
use Throwable;

/**
 * Sticker art on its own disk (public/assets/stickers unless a storage driver
 * says otherwise). Every upload is re-encoded as WebP three times: the art
 * (512 px), a small copy (256 px, for grids and posts) and a silhouette (the
 * shape, all one colour) for members who don't have it yet. 🚨 The three
 * files get unrelated random names: a silhouette's address must not lead to
 * the art. SVG is never accepted (it can carry script).
 */
class Art
{
    public const DISK = 'ernestdefoe-sticker-book';

    private const FULL = 512;

    private const SMALL = 256;

    public function __construct(private readonly Factory $filesystems)
    {
    }

    /**
     * @return array{full: string, small: string, sil: string, w: int, h: int}
     */
    public function store(string $source): array
    {
        $manager = resolve(ImageManager::class);
        $image = $manager->read($source)->scaleDown(width: self::FULL, height: self::FULL);
        $small = (clone $image)->scaleDown(width: self::SMALL, height: self::SMALL);
        // Every visible pixel black, the alpha kept: the sticker's outline and nothing else.
        $silhouette = (clone $small)->brightness(-100)->contrast(100);

        $paths = [];
        try {
            foreach (['full' => $image, 'small' => $small, 'sil' => $silhouette] as $key => $copy) {
                $paths[$key] = $this->put($copy);
            }
        } catch (Throwable $e) {
            $this->disk()->delete(array_values($paths));

            throw $e;
        }

        return [...$paths, 'w' => $image->width(), 'h' => $image->height()];
    }

    /** @param array<string, mixed>|null $image */
    public function delete(?array $image): void
    {
        if ($image) {
            $this->disk()->delete(array_values(array_filter([$image['full'] ?? null, $image['small'] ?? null, $image['sil'] ?? null], 'is_string')));
        }
    }

    /** @param array<string, mixed>|null $image */
    public function url(?array $image, string $which): ?string
    {
        return isset($image[$which]) && is_string($image[$which]) ? $this->disk()->url($image[$which]) : null;
    }

    private function put(ImageInterface $image): string
    {
        $encoded = (string) $image->toWebp(quality: 86);
        if ($encoded === '') {
            throw new RuntimeException('The image could not be encoded.');
        }

        $name = Str::random(32).'.webp';
        $this->disk()->put($name, $encoded);

        return $name;
    }

    private function disk(): Cloud
    {
        /** @var Cloud */
        return $this->filesystems->disk(self::DISK);
    }
}
