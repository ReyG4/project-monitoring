<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function index(Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        return ExpenseResource::collection(
            $task->expenses()->orderByDesc('expense_date')->orderByDesc('id')->get()
        );
    }

    public function store(StoreExpenseRequest $request, Task $task): JsonResponse
    {
        if ($task->children()->exists()) {
            throw ValidationException::withMessages([
                'task' => 'Biaya hanya bisa dicatat pada tugas tanpa subaktivitas.',
            ]);
        }

        $expense = new Expense($request->validated());
        $expense->task_id = $task->id;
        $expense->recorded_by = $request->user()->id;
        $expense->save();

        return (new ExpenseResource($expense->refresh()))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): ExpenseResource
    {
        $expense->update($request->validated());

        return new ExpenseResource($expense->refresh());
    }

    public function destroy(Expense $expense): Response
    {
        Gate::authorize('delete', $expense);

        $expense->delete();

        return response()->noContent();
    }
}