<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Document;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Document\DocumentFolder;
use App\Services\Document\DocumentVaultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentFolderController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        protected DocumentVaultService $documentVaultService
    ) {
    }

    /**
     * List document folders.
     */
    public function index(Request $request): JsonResponse
    {
        $folders = $this->documentVaultService->paginateFolders(
            $request->user()->organization_id,
            $request->has('parent_id') ? $request->input('parent_id') : null,
            $request->has('search') ? (string) $request->input('search') : null,
            $request->integer('per_page', 50)
        );

        return $this->paginated($folders);
    }

    /**
     * Create a new folder.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => ['nullable', 'integer', $this->ownedBy('document_folders')],
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'access_level' => 'nullable|string|in:' . implode(',', DocumentFolder::ACCESS_LEVELS),
        ]);

        $folder = $this->documentVaultService->createFolder($data, $request->user()->id, $request->user()->organization_id);

        return $this->created($folder->load('creator'));
    }

    /**
     * Show a folder with its contents.
     */
    public function show(DocumentFolder $documentFolder): JsonResponse
    {
        $documentFolder->load(['children', 'creator', 'parent', 'documents' => function ($q) {
            $q->notArchived()->orderBy('name')->limit(100);
        }]);
        $documentFolder->loadCount('documents');

        return $this->success($documentFolder);
    }

    /**
     * Update a folder.
     */
    public function update(Request $request, DocumentFolder $documentFolder): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => ['nullable', 'integer', $this->ownedBy('document_folders')],
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'access_level' => 'nullable|string|in:' . implode(',', DocumentFolder::ACCESS_LEVELS),
        ]);

        $folder = $this->documentVaultService->updateFolder($documentFolder, $data);

        return $this->success($folder);
    }

    /**
     * Delete a folder.
     */
    public function destroy(DocumentFolder $documentFolder): JsonResponse
    {
        if ($documentFolder->is_system) {
            return $this->error('System folders cannot be deleted.', 'SYSTEM_FOLDER', 403);
        }

        $this->documentVaultService->deleteFolder($documentFolder);

        return $this->success(null, 'Folder deleted successfully.');
    }
}
