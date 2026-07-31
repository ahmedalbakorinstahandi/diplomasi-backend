<?php

namespace App\Http\Controllers\Negotiation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Negotiation\UpsertNegotiationSituationNoteRequest;
use App\Http\Resources\Negotiation\NegotiationSituationResource;
use App\Http\Services\Negotiation\NegotiationLibraryService;
use App\Models\Users\User;
use App\Services\MessageService;
use App\Services\ResponseService;

class NegotiationSituationController extends Controller
{
    public function __construct(
        protected NegotiationLibraryService $libraryService,
    ) {}

    public function show(int $situation)
    {
        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        $result = $this->libraryService->showSituation($situation, $user->id);

        $payload = (new NegotiationSituationResource($result['situation']))
            ->withAccessStatus($result['access_status'])
            ->withNoteText($result['note_text'])
            ->resolve();

        return ResponseService::response([
            'success' => true,
            'data' => $payload,
            'status' => 200,
        ]);
    }

    public function getNote(int $situation)
    {
        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        $note = $this->libraryService->getNote($situation, $user->id);

        return ResponseService::response([
            'success' => true,
            'data' => $note,
            'status' => 200,
        ]);
    }

    public function upsertNote(UpsertNegotiationSituationNoteRequest $request, int $situation)
    {
        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        $note = $this->libraryService->upsertNote(
            $situation,
            $user->id,
            $request->validated('note_text')
        );

        return ResponseService::response([
            'success' => true,
            'data' => $note,
            'message' => 'messages.negotiation.note.saved',
            'status' => 200,
        ]);
    }
}
