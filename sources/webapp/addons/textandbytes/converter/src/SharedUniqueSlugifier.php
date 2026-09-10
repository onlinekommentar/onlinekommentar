<?php

namespace Textandbytes\Converter;

use Cocur\Slugify\Slugify;
use Cocur\Slugify\SlugifyInterface;

class SharedUniqueSlugifier implements SlugifyInterface
{
    protected SlugifyInterface $slugify;

    protected array $used = [];

    public function __construct(?SlugifyInterface $slugify = null)
    {
        $this->slugify = $slugify ?: new Slugify();
    }

    public function slugify(string $string, array|string|null $options = null): string
    {
        $slugged = $this->slugify->slugify($string, $options);

        $count = 1;
        $orig = $slugged;
        while (in_array($slugged, $this->used)) {
            $slugged = $orig . '-' . $count;
            $count++;
        }

        $this->used[] = $slugged;

        return $slugged;
    }
}
