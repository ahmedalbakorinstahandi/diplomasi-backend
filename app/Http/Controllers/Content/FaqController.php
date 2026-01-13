<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Content\FaqPermission;
use App\Http\Requests\Content\CreateFaqRequest;
use App\Http\Requests\Content\ReOrderFaqRequest;
use App\Http\Requests\Content\UpdateFaqRequest;
use App\Http\Resources\Content\FaqResource;
use App\Http\Services\Content\FaqService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    protected $faqService;

    public function __construct(FaqService $faqService)
    {
        $this->faqService = $faqService;
    }

    public function index(Request $request, $message = null)
    {
        FaqPermission::canView();

        $faqs = $this->faqService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'message' => $message,
            'data' => $faqs,
            'meta' => true,
            'resource' => FaqResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        FaqPermission::canView();

        $faq = $this->faqService->show($id);
        FaqPermission::canShow($faq);

        return ResponseService::response([
            'success' => true,
            'data' => $faq,
            'resource' => FaqResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateFaqRequest $request)
    {
        FaqPermission::canCreate();

        $faq = $this->faqService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $faq,
            'message' => 'messages.faq.created',
            'status' => 201,
            'resource' => FaqResource::class,
        ]);
    }

    public function update(UpdateFaqRequest $request, int $id)
    {
        FaqPermission::canUpdate();

        $faq = $this->faqService->show($id);

        $faq = $this->faqService->update($request->validated(), $faq);

        return ResponseService::response([
            'success' => true,
            'data' => $faq,
            'message' => 'messages.faq.updated',
            'status' => 200,
            'resource' => FaqResource::class,
        ]);
    }

    public function delete(int $id)
    {
        FaqPermission::canDelete();

        $faq = $this->faqService->show($id);

        $this->faqService->delete($faq);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.faq.deleted',
            'status' => 200,
        ]);
    }

    public function reorder(int $id, ReOrderFaqRequest $request)
    {
        FaqPermission::canReorder();

        $faq = $this->faqService->show($id);

        $faq = $this->faqService->reorder($faq, $request->validated());

        return $this->index(request(), 'messages.faq.reordered');
    }
}
