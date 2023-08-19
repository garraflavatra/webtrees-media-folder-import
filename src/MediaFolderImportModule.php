<?php

/**
 * webtrees: online genealogy
 * Media folder import plugin
 *
 * Copyright (C) 2023 Romein van Buren
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Garraflavatra\Webtrees\Module\MediaFolderImportModule;

use Aura\Router\RouterContainer;
use Fisharebest\Webtrees\Http\RequestHandlers\AddMediaFileModal as OldAddMediaFileModal;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateMediaObjectAction as OldCreateMediaObjectAction;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateMediaObjectModal as OldCreateMediaObjectModal;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\View;
use Garraflavatra\Webtrees\Module\MediaFolderImportModule\RequestHandlers\AddMediaFileModal;
use Garraflavatra\Webtrees\Module\MediaFolderImportModule\RequestHandlers\CreateMediaObjectAction;
use Garraflavatra\Webtrees\Module\MediaFolderImportModule\RequestHandlers\CreateMediaObjectModal;

/**
 * A webtrees module that adds the possibility to import many media files at
 * once.
 */
class MediaFolderImportModule extends AbstractModule implements ModuleCustomInterface
{
    use ModuleCustomTrait;

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        return I18N::translate('Media folder import');
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        return I18N::translate('This module adds the possibility to import all media files in the same folder at once from within the admin panel.');
    }

    /**
     * The person or organisation who created this module.
     *
     * @return string
     */
    public function customModuleAuthorName(): string
    {
        return 'Romein van Buren';
    }

    /**
     * The version of this module.
     *
     * @return string
     */
    public function customModuleVersion(): string
    {
        return '1.0.0';
    }

    /**
     * A URL that will provide the latest version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://github.com/garraflavatra/webtrees-media-folder-import/raw/main/resources/latest-version.txt';
    }

    /**
     * Where to get support for this module.
     *
     * @return string
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/garraflavatra/webtrees-media-folder-import';
    }

    /**
     * Where does this module store its resources?
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/../resources/';
    }

    /**
     * Bootstrap. This function is called on *enabled* modules. It is a good
     * place to register routes and views. Note that it is only called on
     * genealogy pages - not on admin pages.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register the custom CreateMediaObjectAction handler
        $router_container = method_exists(Registry::class, 'container')
            ? Registry::container(RouterContainer::class)
            : app(RouterContainer::class);
        $routes = $router_container->getMap()->getRoutes();

        // We need to replace the routes manually, since it is not possible to
        // overwrite them using the router.
        foreach ($routes as $class_name => $route) {
            if ($class_name == OldAddMediaFileModal::class) {
                $routes[OldAddMediaFileModal::class]->handler(AddMediaFileModal::class);
            }
            if ($class_name == OldCreateMediaObjectAction::class) {
                $routes[OldCreateMediaObjectAction::class]->handler(CreateMediaObjectAction::class);
            }
            if ($class_name == OldCreateMediaObjectModal::class) {
                $routes[OldCreateMediaObjectModal::class]->handler(CreateMediaObjectModal::class);
            }
        }

        // Register custom templates
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
        View::registerCustomView('::modals/add-media-file', $this->name() . '::modals/add-media-file');
        View::registerCustomView('::modals/create-media-object', $this->name() . '::modals/create-media-object');
        View::registerCustomView('::modals/media-file-fields', $this->name() . '::modals/media-file-fields');
        View::registerCustomView('::modals/media-object-fields', $this->name() . '::modals/media-object-fields');
    }

    // /**
    //  * Additional/updated translations.
    //  *
    //  * @param string $language
    //  * @return array<string>
    //  */
    // public function customTranslations(string $language): array
    // {
    //     switch ($language) {
    //         case 'fr':
    //         case 'fr-CA':
    //             return [
    //                 // // These are new translations:
    //                 // 'Example module'                                  => 'Exemple module',
    //                 // 'This module does not do anything'                => 'Ce module ne fait rien',
    //                 // // These are updates to existing translations:
    //                 // 'Individual'                                      => 'Poisson',
    //                 // 'Individuals'                                     => 'Poissons',
    //                 // '%s individual' . I18N::PLURAL . '%s individuals' => '%s poisson' . I18N::PLURAL . '%s poissons',
    //                 // 'Unknown given name' . I18N::CONTEXT . '…'        => '?poission?',
    //                 // 'Unknown surname' . I18N::CONTEXT . '…'           => '?POISSON?',
    //             ];

    //         default:
    //             return [];
    //     }
    // }

}
