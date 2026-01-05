<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Learning\GlossaryTermPermission;
use App\Http\Requests\Learning\CreateGlossaryTermRequest;
use App\Http\Requests\Learning\ReOrderGlossaryTermRequest;
use App\Http\Requests\Learning\UpdateGlossaryTermRequest;
use App\Http\Resources\Learning\GlossaryTermResource;
use App\Http\Services\Learning\GlossaryTermService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class GlossaryTermController extends Controller
{
    protected $glossaryTermService;

    public function __construct(GlossaryTermService $glossaryTermService)
    {
        $this->glossaryTermService = $glossaryTermService;
    }

    public function index(Request $request, $message = null)
    {
        GlossaryTermPermission::canView();

        $glossaryTerms = $this->glossaryTermService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'message' => $message,
            'data' => $glossaryTerms,
            'meta' => true,
            'resource' => GlossaryTermResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        GlossaryTermPermission::canView();

        $glossaryTerm = $this->glossaryTermService->show($id);
        GlossaryTermPermission::canShow($glossaryTerm);

        return ResponseService::response([
            'success' => true,
            'data' => $glossaryTerm,
            'resource' => GlossaryTermResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateGlossaryTermRequest $request)
    {
        GlossaryTermPermission::canCreate();

        $glossaryTerm = $this->glossaryTermService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $glossaryTerm,
            'message' => 'messages.glossary_term.created',
            'status' => 201,
            'resource' => GlossaryTermResource::class,
        ]);
    }

    public function update(UpdateGlossaryTermRequest $request, int $id)
    {
        GlossaryTermPermission::canUpdate();

        $glossaryTerm = $this->glossaryTermService->show($id);

        $glossaryTerm = $this->glossaryTermService->update($request->validated(), $glossaryTerm);

        return ResponseService::response([
            'success' => true,
            'data' => $glossaryTerm,
            'message' => 'messages.glossary_term.updated',
            'status' => 200,
            'resource' => GlossaryTermResource::class,
        ]);
    }

    public function delete(int $id)
    {
        GlossaryTermPermission::canDelete();

        $glossaryTerm = $this->glossaryTermService->show($id);

        $this->glossaryTermService->delete($glossaryTerm);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.glossary_term.deleted',
            'status' => 200,
        ]);
    }

    public function reorder(int $id, ReOrderGlossaryTermRequest $request)
    {
        GlossaryTermPermission::canReorder();

        $glossaryTerm = $this->glossaryTermService->show($id);

        $glossaryTerm = $this->glossaryTermService->reorder($glossaryTerm, $request->validated());

        return $this->index(request(), 'messages.glossary_term.reordered');
    }
}

