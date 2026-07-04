<?php

namespace Schilo\Builder\Service;

class PrefixDetector
{
    public function detectFromPostId($postId)
    {
        return $this->detectFromTitle((string) get_the_title((int) $postId));
    }

    public function detectFromTitle($title)
    {
        $title = trim((string) $title);

        if (preg_match('/^([A-Z]{3})[0-9]{1,}/', $title, $matches)) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/^([A-Z]{3})/', $title, $matches)) {
            return strtoupper($matches[1]);
        }

        return 'DEFAULT';
    }
}
