<?php

namespace Kernel\Coroutine;

use Kernel\Exception\KException;

class Parallel
{
    private $task;
    private $childTasks = [];
    private $sendValues = [];
    private $exceptions = [];
    private $fetchCtx   = [];

    /**
     * @var Context
     */
    private $taskContext;

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function call($coroutines, &$fetchCtx = [])
    {
        $this->fetchCtx = &$fetchCtx;

        $parentTaskId = $this->task->getTaskId();
        $this->taskContext = $this->task->getContext();

        $taskDoneEventName = 'parallel_task_done_' . $parentTaskId;
        $event = $this->task->getContext()->getEvent();
        $eventChain = $event->getEventChain();

        $event->bind($taskDoneEventName, [$this,'done']);

        foreach ($coroutines as $key => $coroutine) {
            if ($coroutine instanceof SysCall) {
                throw new \InvalidArgumentException('can not run syscall in parallel');
            }

            if (!($coroutine instanceof \Generator)) {
                $this->sendValues[$key] = $coroutine;
                continue;
            }

            $childTask = new Task($this->catchException($key, $coroutine), $this->taskContext, 0, $this->task);
            $this->childTasks[$key] = $childTask;

            $newTaskId = $childTask->getTaskId();
            $evtName = 'task_event_' . $newTaskId;
            $eventChain->before($evtName, $taskDoneEventName);
        }

        if ($this->childTasks == []) {
            $event->fire($taskDoneEventName);
        }

        foreach ($this->childTasks as $childTask) {
            $childTask->run();
        }
    }

    public function done()
    {
        $event = $this->task->getContext()->getEvent();
        $eventChain = $event->getEventChain();
        $parentTaskId = $this->task->getTaskId();
        $taskDoneEventName = 'parallel_task_done_' . $parentTaskId;

        foreach ($this->childTasks as $key => $childTask) {
            $this->sendValues[$key] = $childTask->getResult();

            $newTaskId = $childTask->getTaskId();
            $evtName = 'task_event_' . $newTaskId;
            $eventChain->breakChain($evtName, $taskDoneEventName);
        }

        $event->unregister($taskDoneEventName);

        if (empty($this->exceptions)) {
            $this->task->send($this->sendValues);
            $this->task->run();
        } else {
            $ex = new KException($this->sendValues, 0, $this->exceptions);
            try {
                $this->task->getCoroutine()->throw($ex);
            } catch (\Throwable $t) {
                $this->task->sendException($t);
            } catch (\Exception $e) {
                $this->task->sendException($e);
            }
            $this->task->run();
        }
    }

    private function catchException($key, \Generator $coroutine)
    {
        try {
            $r = (yield $coroutine);
        } catch (\Throwable $r) {
            if ($r instanceof \Exception) {
                $r =  $r;
            } elseif ($r instanceof \Error) {
                $r =  new \Exception($r->getMessage(), $r->getCode(), $r);
            } else {
                $r = assert(false);
            }

            $this->exceptions[$key] = $r;
        } catch (\Exception $r) {
            $this->exceptions[$key] = $r;
        }

        foreach ($this->fetchCtx as $ctxKey) {
            $this->fetchCtx[$ctxKey] = $this->taskContext->get($ctxKey);
        }

        yield $r;
    }
}
