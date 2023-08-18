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

/**
 * This file contains code that is derived from webtrees
 * Copyright (C) 2023 webtrees development team
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

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\MediaFileService;
use Fisharebest\Webtrees\Services\PendingChangesService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use League\Flysystem\FilesystemReader;
use League\Flysystem\StorageAttributes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function response;

/**
 * Process a form to create a new media object.
 */
class CreateMediaObjectAction implements RequestHandlerInterface
{
    private MediaFileService $media_file_service;

    private PendingChangesService $pending_changes_service;

    /**
     * @param MediaFileService      $media_file_service
     * @param PendingChangesService $pending_changes_service
     */
    public function __construct(MediaFileService $media_file_service, PendingChangesService $pending_changes_service)
    {
        $this->media_file_service      = $media_file_service;
        $this->pending_changes_service = $pending_changes_service;
    }

    /**
     * @param Tree   $tree
     * @param string $file
     * @param string $title
     * @param string $type
     * @param string $restriction
     * @param string $note
     *
     * @return ResponseInterface|Media
     */
    private function createFile(Tree $tree, string $file, string $title, string $type, string $restriction, string $note)
    {
        if ($file === '') {
            return response(['error_message' => I18N::translate('There was an error uploading your file.')], StatusCodeInterface::STATUS_NOT_ACCEPTABLE);
        }

        $gedcom = "0 @@ OBJE\n" . $this->media_file_service->createMediaFileGedcom($file, $type, $title, $note);

        if ($restriction !== '') {
            $gedcom .= "\n1 RESN " . strtr($restriction, ["\n" => "\n2 CONT "]);
        }

        $record = $tree->createMediaObject($gedcom);

        // Accept the new record to keep the filesystem synchronized with the genealogy.
        $this->pending_changes_service->acceptRecord($record);

        // OK!
        return $record;
    }

    /**
     * Process a form to create a new media object.
     *
     * @param  ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree          = Validator::attributes($request)->tree();
        $note          = Validator::parsedBody($request)->string('media-note');
        $title         = Validator::parsedBody($request)->string('title');
        $type          = Validator::parsedBody($request)->string('type');
        $restriction   = Validator::parsedBody($request)->string('restriction');
        $file_location = Validator::parsedBody($request)->string('file_location');

        $note          = Registry::elementFactory()->make('OBJE:NOTE')->canonical($note);
        $type          = Registry::elementFactory()->make('OBJE:FILE:FORM:TYPE')->canonical($type);
        $restriction   = Registry::elementFactory()->make('OBJE:RESN')->canonical($restriction);

        /** @var StorageAttributes */
        $first_record = null;

        // Import an entire folder on the server.
        if ($file_location == 'folder') {
            $folder = Validator::parsedBody($request)->string('folder');
            $files = $tree->mediaFilesystem()->listContents($folder, FilesystemReader::LIST_SHALLOW);
            $file_index = 0;

            foreach ($files as $listing) {
                $file_path = $listing->path();
                $human_index = strval($file_index + 1);

                // Replace variable parts of title
                $current_title = $title;
                $current_title = str_ireplace('{index}', $human_index, $title);
                $current_title = str_ireplace('{counter}', str_pad($human_index, 6, "0", STR_PAD_LEFT), $current_title);
                $current_title = str_ireplace('{basename}', pathinfo($file_path, PATHINFO_BASENAME), $current_title);
                $current_title = str_ireplace('{filename}', pathinfo($file_path, PATHINFO_FILENAME), $current_title);
                $current_title = Registry::elementFactory()->make('OBJE:FILE:TITL')->canonical($current_title);

                $record = $this->createFile($tree, $file_path, $current_title, $type, $restriction, $note);

                if ($record instanceof ResponseInterface) {
                    // An error response has been returned
                    return $record;
                }

                if ($file_index == 0) {
                    $first_record = $record;
                }

                $file_index++;
            }

            // value and text are for autocomplete
            // html is for interactive modals
            return response([
                'value' => '@' . $first_record->xref() . '@',
                'text'  => view('selects/media', ['media' => $first_record]),
                'html'  => view('modals/record-created', [
                    'title' => I18N::translate('The media objects have been created'),
                    'name'  => $first_record->fullName(),
                    'url'   => $first_record->url(),
                ]),
            ]);
        }

        // Regular import
        $file = $this->media_file_service->uploadFile($request);
        $record = $this->createFile($tree, $file, $title, $type, $restriction, $note);

        // value and text are for autocomplete
        // html is for interactive modals
        return response([
            'value' => '@' . $record->xref() . '@',
            'text'  => view('selects/media', ['media' => $record]),
            'html'  => view('modals/record-created', [
                'title' => I18N::translate('The media object has been created'),
                'name'  => $record->fullName(),
                'url'   => $record->url(),
            ]),
        ]);
    }
}
