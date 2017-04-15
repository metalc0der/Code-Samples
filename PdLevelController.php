<?php

/**
  * PdLevelController controller
  *
  * This file holds the methods used to deal with the user levels in the system.
  *
  * @author Yunior Rodriguez
  * @author yunior@thecomicsfactory.com
  */

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\User;
use App\Models\PdLevel;
use App\Models\PdAccess;
use App\Http\Controllers\Validator;
use Auth;
use Illuminate\Support\Facades\Input;

/**
  * PdLevelController class
  *
  * Extends Controller.
  * @package App\Http\Controllers
  *
  */
class PdLevelController extends Controller {

    /**
     * Public PdLevelController class constructor.
     *
     * @return  void
     *
     */
    public function __construct() {
        
        $this->middleware(['web','auth','acl']);
    
    }

    /**
     * Validation rules.
     *
     * @return  array  array with validation rules.
     *
     */
    protected function rules() {

        return [
            'name' => 'required|max:50',
            
        ];
    }

    /**
     * Displays a listing of the levels in the system.
     *
     * @param  Request  $request  client request
     * @return  view
     */
    public function index(Request $request) {

        $filters = 0;
        $sort_order = 'ASC';
        foreach ($request->session()->all() as $key => $value) {
            if (strpos($key, 'level') === 0) {
                $filters++;
            }
        }

        $levels = Pdlevel::name(session('level_name_filter'));

        if(isset($request['sort_field'])) {

          $levels = $levels->orderBy($request['sort_field'],$request['sort_order']);
          $sort_order = $request['sort_order'];

        }

        return view('levels.index', ['levels' => $levels->paginate(30)->appends(Input::except('page')),'filters' => $filters,'sort_order'=>$sort_order]);
    
    }

    /**
     * Sets the session filters for levels.
     *
     * @param  Request  $request  client request with the filter values.
     * @return  void
     * 
     */
    public function setFilter(Request $request) {

         if ($request->has('name')) {

                $request->session()->put('level_name_filter', $request['name']);

        }

                 
    }

    /**
     * Deletes the session filters for levels.
     *
     * @param  Request  $request  client request.
     * @return  void
     * 
     */
    public function unsetFilter(Request $request) {

        if(session('level_name_filter')) {

            $request->session()->forget('level_name_filter');

        }

        
    }

    /**
     * Shows the form for creating a new level.
     *
     * @return  view
     */
    public function create() {

        $access = PdAccess::paginate(30);
       
        return view('levels.create',['access'=>$access]);
    
    }

    /**
     * Stores a newly created level in the system.
     *
     * Intended to be used with ajax.
     * @param  Request  $request  client request with the level info.
     * @return  string|int  Returns the level id on success or 'ko' on failure.
     */
    public function store(Request $request) {

        $input = $request->all();

        foreach ($input as $key => $value) {

            $input[$key] = strip_tags($value);
        }

        $input['created_by'] = Auth::user()->id;
        $input['updated_by'] = Auth::user()->id;

        $level = PdLevel::create($input);

        if ($level) {

            //Insert in the log
            $log_controller = new PdLogController();
            $log_controller->store($request->ip(),'pd_level',$level->pd_level_id,'Level created');

            return $level->pd_level_id;
              
        } else {
        
            return 'ko';

        }

    }


    /**
     * Shows the form for editing the specified level.
     *
     * @param  int  $id  level id in pd_level
     * @return  view
     */
    public function edit($id) {

        $level = PdLevel::findOrFail($id);
        $access = PdAccess::paginate(30);
        return view('levels.edit',['level'=>$level,'access'=>$access]);
    
    }


    /**
     * Removes the specified level from the system.
     *
     * @param  Request  $request  client request
     * @param  int  $id  level id in pd_level
     * @return  view
     */
    public function destroy(Request $request,$id) {

        $level = Pdlevel::find($id);
        $users = User::where('pd_level_id',$id)->get();

        if(count($users) == 0) {

            $level->updated_by = Auth::user()->id;
            $level->save();
            if($level->delete()) {

                //Insert in the log
                $log_controller = new PdLogController();
                $log_controller->store($request->ip(),'pd_level',$level->pd_level_id,'Level deleted');

                $level->accesses()->sync([]);

                return redirect()->back()
                    ->with('success','The level has been successfully removed !!!');

            } else {
                return redirect()->back()
                    ->with('error','There was a problem removing the level !!!');

            }

        } else {

            return redirect()->back()
                    ->with('error','The level can not be deleted because there are users using it !!!');

        }
        
        

    }

    
    
}
