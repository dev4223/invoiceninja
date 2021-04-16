<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\Events\Task\TaskWasCreated;
use App\Events\Task\TaskWasUpdated;
use App\Factory\TaskFactory;
use App\Filters\TaskFilters;
use App\Http\Requests\Task\CreateTaskRequest;
use App\Http\Requests\Task\DestroyTaskRequest;
use App\Http\Requests\Task\EditTaskRequest;
use App\Http\Requests\Task\ShowTaskRequest;
use App\Http\Requests\Task\SortTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Requests\Task\UploadTaskRequest;
use App\Models\Account;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Repositories\TaskRepository;
use App\Transformers\TaskTransformer;
use App\Utils\Ninja;
use App\Utils\Traits\BulkOptions;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use App\Utils\Traits\Uploadable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Class TaskController.
 */

class TaskController extends BaseController
{
    use MakesHash;
    use Uploadable;
    use BulkOptions;
    use SavesDocuments;

    protected $entity_type = Task::class;

    protected $entity_transformer = TaskTransformer::class;

    /**
     * @var Taskepository
     */
    protected $task_repo;

    /**
     * TaskController constructor.
     * @param TaskRepository $task_repo
     */
    public function __construct(TaskRepository $task_repo)
    {
        parent::__construct();

        $this->task_repo = $task_repo;
    }

    /**
     * @OA\Get(
     *      path="/api/v1/tasks",
     *      operationId="getTasks",
     *      tags={"tasks"},
     *      summary="Gets a list of tasks",
     *      description="Lists tasks, search and filters allow fine grained lists to be generated.
     *
     *   Query parameters can be added to performed more fine grained filtering of the tasks, these are handled by the TaskFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of tasks",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Task"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param TaskFilters $filters
     * @return Response|mixed
     */
    public function index(TaskFilters $filters)
    {
        $tasks = Task::filter($filters);

        return $this->listResponse($tasks);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowTaskRequest $request
     * @param Task $task
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/tasks/{id}",
     *      operationId="showTask",
     *      tags={"tasks"},
     *      summary="Shows a client",
     *      description="Displays a client by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Task Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the task object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Task"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function show(ShowTaskRequest $request, Task $task)
    {
        return $this->itemResponse($task);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditTaskRequest $request
     * @param Task $task
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/tasks/{id}/edit",
     *      operationId="editTask",
     *      tags={"tasks"},
     *      summary="Shows a client for editting",
     *      description="Displays a client by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Task Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the client object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Task"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function edit(EditTaskRequest $request, Task $task)
    {
        return $this->itemResponse($task);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateTaskRequest $request
     * @param Task $task
     * @return Response
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/tasks/{id}",
     *      operationId="updateTask",
     *      tags={"tasks"},
     *      summary="Updates a client",
     *      description="Handles the updating of a client by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Task Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the client object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Task"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function update(UpdateTaskRequest $request, Task $task)
    {
        if ($request->entityIsDeleted($task)) {
            return $request->disallowUpdate();
        }

        $old_task = json_decode(json_encode($task));
        
        $task = $this->task_repo->save($request->all(), $task);

        if($task->status_order != $old_task->status_order)
            $this->task_repo->sortStatuses($old_task, $task);

        event(new TaskWasUpdated($task, $task->company, Ninja::eventVars(auth()->user()->id)));

        return $this->itemResponse($task->fresh());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateTaskRequest $request
     * @return Response
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/tasks/create",
     *      operationId="getTasksCreate",
     *      tags={"tasks"},
     *      summary="Gets a new blank client object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank client object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Task"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function create(CreateTaskRequest $request)
    {
        $task = TaskFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($task);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreTaskRequest $request
     * @return Response
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/tasks",
     *      operationId="storeTask",
     *      tags={"tasks"},
     *      summary="Adds a client",
     *      description="Adds an client to a company",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved client object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Task"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function store(StoreTaskRequest $request)
    {
        $task = $this->task_repo->save($request->all(), TaskFactory::create(auth()->user()->company()->id, auth()->user()->id));

        event(new TaskWasCreated($task, $task->company, Ninja::eventVars(auth()->user()->id)));

        return $this->itemResponse($task);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyTaskRequest $request
     * @param Task $task
     * @return Response
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/tasks/{id}",
     *      operationId="deleteTask",
     *      tags={"tasks"},
     *      summary="Deletes a client",
     *      description="Handles the deletion of a client by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Task Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns a HTTP status",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function destroy(DestroyTaskRequest $request, Task $task)
    {
        //may not need these destroy routes as we are using actions to 'archive/delete'
        $this->task_repo->delete($task);

        return $this->itemResponse($task->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Response
     *
     *
     * @OA\Post(
     *      path="/api/v1/tasks/bulk",
     *      operationId="bulkTasks",
     *      tags={"tasks"},
     *      summary="Performs bulk actions on an array of tasks",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="User credentials",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                     description="Array of hashed IDs to be bulk 'actioned",
     *                     example="[0,1,2,3]",
     *                 ),
     *             )
     *         )
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="The Task User response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Task"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function bulk()
    {
        $action = request()->input('action');

        $ids = request()->input('ids');
        $tasks = Task::withTrashed()->find($this->transformKeys($ids));

        $tasks->each(function ($task, $key) use ($action) {
            if (auth()->user()->can('edit', $task)) {
                $this->task_repo->{$action}($task);
            }
        });

        return $this->listResponse(Task::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }

    /**
     * Returns a client statement.
     *
     * @return void [type] [description]
     */
    public function statement()
    {
        //todo
    }

/**
     * Update the specified resource in storage.
     *
     * @param UploadTaskRequest $request
     * @param Task $task
     * @return Response
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/tasks/{id}/upload",
     *      operationId="uploadTask",
     *      tags={"tasks"},
     *      summary="Uploads a document to a task",
     *      description="Handles the uploading of a document to a task",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Task Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Task object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Task"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function upload(UploadTaskRequest $request, Task $task)
    {

        if(!$this->checkFeature(Account::FEATURE_DOCUMENTS))
            return $this->featureFailure();
        
        if ($request->has('documents')) 
            $this->saveDocuments($request->file('documents'), $task);

        return $this->itemResponse($task->fresh());

    }  

    public function sort(SortTaskRequest $request)
    {
        // : {"status_ids":
        // ["VolejYWdjN","wMvbmqpbYA","OpnelRlbKB","Wpmbk2xazJ"],
        // "task_ids":{"VolejYWdjN":
        //     ["xYRdG7dDzO","q9wdLwbjPX","4w9aAOdvMR","mxkazYeJ0P","WJxbojagwO","oBDbDxbl2E"],"wMvbmqpbYA":["4openRe7Az","1YQdJ2dOGp"],"OpnelRlbKB":["X46dBXa79j"],"Wpmbk2xazJ":["k8mep2bMyJ","JX7ax9byv4"]}}
    
        $task_statuses = json_decode($request->input('status_ids'));
        $tasks = json_decode($request->input('task_ids'));

        collect($task_statuses)->each(function ($task_status_hashed_id, $key){

            $task_status = TaskStatus::where('id', $this->decodePrimaryKey($task_status_hashed_id))
                                     ->where('company_id', auth()->user()->company()->id)
                                     ->first();

            $task_status->status_order = $key;
            $task_status->save();

        });

        foreach($tasks as $key => $task_list)
        {

            $sort_status_id = $this->decodePrimaryKey($key);
            
            foreach ($task_list as $key => $task)
            {

                $task = Task::where('id', $this->decodePrimaryKey($task))
                             ->where('company_id', auth()->user()->company()->id)
                             ->first();

                $task->status_sort_order = $key;
                $task->status_id = $sort_status_id;
                $task->save();
            }

        }
    }
}
