<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ModuleSettingsViewTest extends CIUnitTestCase
{
    public function testSharedFieldsRenderPassedModuleDetails(): void
    {
        $html = view('module_settings/fields', [
            'prefix'        => 'edit',
            'mode'          => 'edit',
            'm'             => [
                'name'        => 'Module Setting',
                'slug'        => 'module_setting',
                'short_order' => 3,
                'url'         => 'modules',
                'icon'        => 'fa-cubes-stacked',
                'is_category' => 0,
            ],
            'parentModules' => [],
        ], ['saveData' => false]);

        $this->assertStringContainsString('value="Module&#x20;Setting"', $html);
        $this->assertStringContainsString('value="module_setting"', $html);
        $this->assertStringContainsString('value="modules"', $html);
        $this->assertStringContainsString('value="3"', $html);
        $this->assertStringContainsString('fa-cubes-stacked', $html);
    }

    public function testAddModuleWizardListsExistingActionsForSelection(): void
    {
        $html = view('module_settings/index', [
            'title'           => 'Module Settings',
            'pageTitle'       => 'Module Settings',
            'filteredModules' => [],
            'breadcrumbs'     => [],
            'can'             => ['create' => true],
            'modules'         => [],
            'parentModules'   => [],
            'availableActions' => [
                ['id' => 4, 'name' => 'View', 'action_key' => 'read', 'description' => 'Open this module.'],
            ],
        ], ['saveData' => false]);

        $this->assertStringContainsString('Module Details', $html);
        $this->assertStringContainsString('Choose Module Actions', $html);
        $this->assertStringContainsString('name="action_ids[]" value="4"', $html);
        $this->assertStringContainsString('Open this module.', $html);
        $this->assertStringNotContainsString('name="new_action', $html);
    }
}
