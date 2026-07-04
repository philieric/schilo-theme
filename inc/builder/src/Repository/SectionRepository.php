<?php

namespace Schilo\Builder\Repository;

use Schilo\Builder\Entity\Section;

class SectionRepository
{
    const META_KEY = '_schilo_builder_sections';

    public function findByPostId($postId)
    {
        $rawSections = get_post_meta((int) $postId, self::META_KEY, true);

        if (!is_array($rawSections)) {
            return array();
        }

        $sections = array();

        foreach ($rawSections as $rawSection) {
            if (is_array($rawSection)) {
                $sections[] = Section::fromArray($rawSection);
            }
        }

        usort($sections, function (Section $a, Section $b) {
            return $a->getOrder() <=> $b->getOrder();
        });

        return $sections;
    }

    public function save($postId, array $sections)
    {
        $data = array();

        foreach ($sections as $index => $section) {
            if (!$section instanceof Section) {
                continue;
            }

            $section->setOrder($index);
            $data[] = $section->toArray();
        }

        update_post_meta((int) $postId, self::META_KEY, $data);
    }
}
