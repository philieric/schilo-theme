<?php

namespace Schilo\Builder\Service;

use Schilo\Builder\Entity\Section;

class AdminSectionRenderer
{
    public function render(Section $section, $index, $prefix = '')
    {
        $sectionTypeService = new SectionTypeService();
        $viewFile = $sectionTypeService->getAdminViewForType($section->getType());

        $view = SCHILO_BUILDER_PATH . 'views/admin/sections/' . $viewFile;

        if (!file_exists($view)) {
            $view = SCHILO_BUILDER_PATH . 'views/admin/sections/default.php';
        }

        $data = method_exists($section, 'getData') ? $section->getData() : array();

        include $view;
    }
}
