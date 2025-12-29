<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Learning\LessonPermission;
use App\Http\Requests\Learning\CreateLessonRequest;
use App\Http\Requests\Learning\ReOrderLessonRequest;
use App\Http\Requests\Learning\SubmitLessonAnswerRequest;
use App\Http\Requests\Learning\UpdateLessonRequest;
use App\Http\Resources\Learning\LessonResource;
use App\Http\Resources\Progress\UserLessonAttemptResource;
use App\Http\Services\Learning\LessonQuestionService;
use App\Http\Services\Learning\LessonService;
use App\Models\Users\User;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    protected $lessonService;
    protected $lessonQuestionService;

    public function __construct(LessonService $lessonService, LessonQuestionService $lessonQuestionService)
    {
        $this->lessonService = $lessonService;
        $this->lessonQuestionService = $lessonQuestionService;
    }

    public function index(Request $request, $message = null)
    {
        LessonPermission::canView();

        $lessons = $this->lessonService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'message' => $message,
            'data' => $lessons,
            'meta' => true,
            'resource' => LessonResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        LessonPermission::canView();

        $lesson = $this->lessonService->show($id);
        LessonPermission::canShow($lesson);

        return ResponseService::response([
            'success' => true,
            'data' => $lesson,
            'resource' => LessonResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateLessonRequest $request)
    {
        LessonPermission::canCreate();

        $lesson = $this->lessonService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $lesson,
            'message' => 'messages.lesson.created',
            'status' => 201,
            'resource' => LessonResource::class,
        ]);
    }

    public function update(UpdateLessonRequest $request, int $id)
    {
        LessonPermission::canUpdate();

        $lesson = $this->lessonService->show($id);

        $lesson = $this->lessonService->update($request->validated(), $lesson);

        return ResponseService::response([
            'success' => true,
            'data' => $lesson,
            'message' => 'messages.lesson.updated',
            'status' => 200,
            'resource' => LessonResource::class,
        ]);
    }

    public function delete(int $id)
    {
        LessonPermission::canDelete();

        $lesson = $this->lessonService->show($id);

        $this->lessonService->delete($lesson);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.lesson.deleted',
            'status' => 200,
        ]);
    }

    public function reorder(int $id, ReOrderLessonRequest $request)
    {
        LessonPermission::canReorder();

        $lesson = $this->lessonService->show($id);

        $lesson = $this->lessonService->reorder($lesson, $request->validated());

        return $this->index(request(), 'messages.lesson.reordered');
    }

    /**
     * بدء محاولة جديدة أو إرجاع المحاولة الحالية
     */
    public function startAttempt(int $lessonId)
    {
        $user = User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'messages.unauthorized',
                'status' => 401,
            ]);
        }

        $attempt = $this->lessonQuestionService->startOrGetAttempt($lessonId, $user->id);

        return ResponseService::response([
            'success' => true,
            'data' => $attempt,
            'resource' => UserLessonAttemptResource::class,
            'status' => 200,
        ]);
    }

    /**
     * جلب جميع أسئلة الدرس مع حالاتها
     */
    public function getQuestions(int $lessonId, Request $request)
    {
        $user = User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'messages.unauthorized',
                'status' => 401,
            ]);
        }

        $attemptId = $request->query('attempt_id');
        $result = $this->lessonQuestionService->getQuestionsWithStatus($lessonId, $attemptId);

        return ResponseService::response([
            'success' => true,
            'data' => $result,
            'status' => 200,
        ]);
    }

    /**
     * جلب السؤال الحالي بالتفاصيل الكاملة
     */
    public function getCurrentQuestion(int $lessonId, int $attemptId)
    {
        $user = User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'messages.unauthorized',
                'status' => 401,
            ]);
        }

        $result = $this->lessonQuestionService->getCurrentQuestion($attemptId);

        return ResponseService::response([
            'success' => true,
            'data' => $result,
            'status' => 200,
        ]);
    }

    /**
     * إرسال إجابة على سؤال
     */
    public function submitAnswer(int $lessonId, int $attemptId, SubmitLessonAnswerRequest $request)
    {
        $user = User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'messages.unauthorized',
                'status' => 401,
            ]);
        }

        $validated = $request->validated();
        $result = $this->lessonQuestionService->submitAnswer(
            $attemptId,
            $validated['question_id'],
            $validated,
            $user->id
        );

        return ResponseService::response([
            'success' => true,
            'data' => $result,
            'message' => 'messages.answer.submitted',
            'status' => 200,
        ]);
    }

    /**
     * إنهاء المحاولة
     */
    public function finishAttempt(int $lessonId, int $attemptId)
    {
        $user = User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'messages.unauthorized',
                'status' => 401,
            ]);
        }

        $result = $this->lessonQuestionService->finishAttempt($attemptId, $user->id);

        return ResponseService::response([
            'success' => true,
            'data' => $result,
            'message' => 'messages.attempt.finished',
            'status' => 200,
        ]);
    }
}
