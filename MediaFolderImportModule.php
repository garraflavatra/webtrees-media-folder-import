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

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\MediaFileService;
use Fisharebest\Webtrees\Services\PendingChangesService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use League\Flysystem\FilesystemReader;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A webtrees module that adds the possibility to import many media files at
 * once.
 */
class MediaFolderImportModule extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        return I18N::translate('Import media files from folder');
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
        return __DIR__ . '/resources/';
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
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
        View::registerCustomView('::modules/import-media-folder', $this->name() . '::modules/import-media-folder');
    }

    private function getTemplateData(ServerRequestInterface $request): array
    {
        $this->layout = 'layouts/administration';
        $title = I18N::translate('Import media files from a folder');

        $trees = [];
        foreach (app(TreeService::class)->all() as $tree) {
            assert($tree instanceof Tree);
            $trees[$tree->id()] = $tree->name();
        }

        $tree = null;
        $tree_id = e($request->getQueryParams()['tree_id'] ?? '');
        if ($tree_id != '') {
            $tree = app(TreeService::class)->find(intval($tree_id));
        }

        return [
            'title'   => $title,
            'request' => $request,
            'trees'   => $trees,
            'tree'    => $tree,
            'tree_id' => $tree_id,
        ];
    }

    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->viewResponse(
            'modules/import-media-folder',
            $this->getTemplateData($request),
        );
    }

    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $media_file_service      = app(MediaFileService::class);
        $pending_changes_service = app(PendingChangesService::class);

        $template_data           = $this->getTemplateData($request);
        $tree                    = $template_data['tree'];

        assert($tree instanceof Tree);

        $unused_files  = $media_file_service->unusedFiles($tree);

        $title         = Validator::parsedBody($request)->string('title');
        $type          = Validator::parsedBody($request)->string('type');
        $note          = Validator::parsedBody($request)->string('note');
        $restriction   = Validator::parsedBody($request)->string('restriction');

        $note          = Registry::elementFactory()->make('OBJE:NOTE')->canonical($note);
        $type          = Registry::elementFactory()->make('OBJE:FILE:FORM:TYPE')->canonical($type);
        $restriction   = Registry::elementFactory()->make('OBJE:RESN')->canonical($restriction);

        $folder        = Validator::parsedBody($request)->string('folder');
        $files         = $tree->mediaFilesystem()->listContents($folder, FilesystemReader::LIST_SHALLOW);
        $file_index    = 0;
        $created_media = [];

        foreach ($files as $listing) {
            $file_path = $listing->path();

            // Skip the file if it is already linked to a media object
            $is_new = array_key_exists($file_path, $unused_files);
            if (!$is_new) continue;

            $human_index = strval($file_index + 1);

            // Replace variable parts of title
            $current_title = $title;
            $current_title = str_ireplace('{index}', $human_index, $title);
            $current_title = str_ireplace('{counter}', str_pad($human_index, 6, "0", STR_PAD_LEFT), $current_title);
            $current_title = str_ireplace('{basename}', pathinfo($file_path, PATHINFO_BASENAME), $current_title);
            $current_title = str_ireplace('{filename}', pathinfo($file_path, PATHINFO_FILENAME), $current_title);
            $current_title = Registry::elementFactory()->make('OBJE:FILE:TITL')->canonical($current_title);

            $gedcom = "0 @@ OBJE\n" . $media_file_service->createMediaFileGedcom($file_path, $type, $current_title, $note);

            if ($restriction !== '') {
                $gedcom .= "\n1 RESN " . strtr($restriction, ["\n" => "\n2 CONT "]);
            }

            $record = $tree->createMediaObject($gedcom);
            $pending_changes_service->acceptRecord($record);

            $created_media[] = $record;
            $file_index++;
        }

        return $this->viewResponse(
            'modules/import-media-folder',
            array_merge($template_data, [
                'success'       => I18N::translate('%s records have been created.', $file_index),
                'created_media' => $created_media,
            ]),
        );
    }
}
