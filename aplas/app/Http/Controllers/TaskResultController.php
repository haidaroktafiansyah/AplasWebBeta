<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

use Redirect;
use Session;

class TaskResultController extends Controller
{
  public function index(Request $request) {
    //$check=\App\StudentTeacher::where('student','=',Auth::user()->id);
    //if ($check->count()==0) return view('student/home')->with(['count'=>$check->count()]);
	$check=\App\User::find(Auth::user()->id);
        if ($check->status!='active') return view('student/home')->with(['status'=>$check->status]);

      $filter = $request->input('topicList','1');
      if ($filter=='0') {
        $entities=\App\TaskResult::where('userid','=',Auth::user()->id);
      } else {
        $entities = \App\Task::where('tasks.topic','=',$filter)
              ->select(
                  'task_results.id',
                  'task_results.taskid',
                  'task_results.userid',
                  'task_results.status',
                  'task_results.duration',
                  'task_results.comment',
                  'task_results.imgFile',
                  'tasks.taskno',
                  'tasks.desc',
                  'tasks.topic'
              )
              ->leftJoin('task_results', function($join)
                    {
                      $join->on('tasks.id','=','task_results.taskid')
                      ->where('task_results.userid', '=', Auth::user()->id);
                    }
                  )
              ->orderBy('tasks.taskno', 'asc')
              ->get();
      }

      $lfiles = \App\TopicFiles::where('topic_files.topic','=',$filter)
            ->select(
                'file_results.id',
                'file_results.userid',
                'file_results.rscfile',
                'file_results.fileid',
                'topic_files.fileName',
                'topic_files.path',
                'topic_files.desc'
            )
            ->leftJoin('file_results', function($join)
                  {
                    $join->on('topic_files.id','=','file_results.fileid')
                    ->where('file_results.userid', '=', Auth::user()->id);
                  }
                )
            ->orderBy('topic_files.fileName', 'asc')
            ->get();

    $items = \App\Topic::where('status','>=','0')
	->where('androidclass','=','AndroidX')
        ->orderBy('status','desc')
        ->orderBy('name','asc')
        ->pluck('name', 'id');

      $valid = \App\StudentSubmit::where('userid','=',Auth::user()->id)
              ->where('topic','=',$filter)
              ->get()->count();

      return view('student/results/index')
        ->with(compact('entities'))
        ->with(compact('lfiles'))
        ->with(compact('items'))
        ->with(compact('filter'))
        ->with(compact('valid'));

  }

  public function getTaskData($topic) {
    $items = \App\Task::where('tasks.topic','=',$topic)
          ->select(
              'tasks.id',
              'tasks.taskno',
              'tasks.desc',
              'topics.name'
          )
          ->join(
              'topics',
              'topics.id','=','tasks.topic'
          )
          ->orderBy('topics.name', 'asc')
          ->orderBy('tasks.taskno', 'asc')
          ->get();

    return $items;
  }
  public function create($id)
  {
      $items = \App\Task::where('topic','=',$id)
        ->orderBy('taskno', 'asc')
        ->get();
      $topic = \App\Topic::find($id);
      return view('student/results/create')
        ->with(compact('topic'))
        ->with(compact('items'));
  }

  public function store(Request $request)
  {
      //
      $rules =[
          'duration'=>'required',
          'image'=>'required',
	'comment'=>'required'
      ];

      $msg=[
          'duration.required'=>'Duration time must not empty',
          'image.required'=>'Evidence image file must not empty',
	  'comment.required'=>'Comment must not empty'
      ];

      $validator=Validator::make($request->all(),$rules,$msg);

      //jika data ada yang kosong
      if ($validator->fails()) {

          //refresh halaman
          return Redirect::to('student/results/create/'.$request->get('topic'))
          ->withErrors($validator);

      } else {
        $check = \App\TaskResult::where('userid','=',Auth::user()->id)
                ->where('taskid','=',$request->get('taskid'))
                ->get();

        if (sizeof($check)>0) {
          $task = \App\Task::find($request->get('taskid'));
          $message = 'Result of Task '.$task['desc'].' is already submitted!!';
          //Session::flash('message',);
          return Redirect::to('student/results/create'.$request->get('topic'))->withErrors($message);

        } else {
          $file = $request->file('image');
          $imgFile=$file->store('results','public');

          $entity=new \App\TaskResult;
	
	$comment = ($request->get('comment')==null)?'-':$request->get('comment');

          $entity->userid=Auth::user()->id;
          $entity->taskid=$request->get('taskid');
          $entity->status=$request->get('status');
          $entity->duration=$request->get('duration');
          $entity->comment=$comment;
          $entity->imgFile=$imgFile;
          $entity->save();

          Session::flash('message','A New Task Result Stored');

          //return "Add new topic is success";
          return Redirect::to('student/results?topicList='.$request->get('topic'))->with( [ 'topic' => $request->get('topic') ] );
        }
      }
  }

  public function destroy(Request $request, $id)
  {
      //
      $entity = \App\TaskResult::find($id);
      $entity->delete();
      Session::flash('message','Task Result with Id='.$id.' is deleted');
      return Redirect::to('student/results?topicList='.$request->get('topic'));
  }

  public function edit($id)
  {
    //
    $entity = \App\TaskResult::where('id','=',$id)->first();
    $task = \App\Task::where('id','=',$entity['taskid'])->first();
    return view('student/results/edit')->with(compact('entity'))
      ->with(compact('task'));
  }

  public function update(Request $request, $id) {
    //
    $rules =[
        'duration'=>'required',
    ];

    $msg=[
        'duration.required'=>'Duration time must not empty',
    ];


    $validator=Validator::make($request->all(),$rules,$msg);

    if ($validator->fails()) {
        return Redirect::to('student/results/'.$id.'/edit')
        ->withErrors($validator);

    }else{
      $file = $request->file('image');

      $entity=\App\TaskResult::find($id);

      $entity->taskid=$request->get('taskid');
      $entity->status=$request->get('status');
      $entity->duration=$request->get('duration');
      $entity->comment=$request->get('comment');

      if ($file!='') {
        $imgFile=$file->store('results','public');
        $entity->imgFile=$imgFile;
      }
      $entity->save();

      Session::flash('message','Task Result with Id='.$id.' is changed');

      $task = \App\Task::find($request->get('taskid'));
      return Redirect::to('student/results?topicList='.$task['topic']);
    }
  }
}
