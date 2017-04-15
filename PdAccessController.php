<?php

/**
  * PdAccess controller
  *
  * This file holds the methods used to deal with the accesses in the system..
  *
  * @author Yunior Rodriguez
  * @author yunior@thecomicsfactory.com
  */

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\User;
use App\Models\PdAccess;
use App\Http\Controllers\Validator;
use Auth;

/**
  * PdAccessController class
  *
  * Extends Controller.
  * @package App\Http\Controllers
  *
  */
class PdAccessController extends Controller {

    /**
     * Public PdAccessController class constructor.
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
            'route' => 'required|max:100',
            
        ];
    }

    /**
     * Displays a listing of the system accesses.
     *
     * @param  Request  $request  client request
     * @return  view
     */
    public function index(Request $request) {

        $filters = 0;
        foreach ($request->session()->all() as $key => $value) {
            if (strpos($key, 'access') === 0) {
                $filters++;
            }
        }

        $accesses = PdAccess::route(session('access_route_filter'))
                 ->paginate(30);

        return view('access.index', ['accesses' => $accesses,'filters' => $filters]);
    
    }

    /**
     * Sets the session filters for access list.
     *
     * @param  Request  $request  client request with the session values.
     * @return  void
     * 
     */
    public function setFilter(Request $request) {

         if ($request->has('route')) {

                $request->session()->put('access_route_filter', $request['route']);

        }

                 
    }

    /**
     * Deletes all the session filters for access list.
     *
     * @param  Request  $request client request
     * @return  void
     */
    public function unsetFilter(Request $request) {

        if(session('access_route_filter')) {

            $request->session()->forget('access_route_filter');

        }

        
    }

    /**
     * Shows the form for creating a new access.
     *
     * @return  view
     *
     */
    public function create() {

   
        return view('access.create');
    
    }

    /**
     * Stores a newly created acccess in the system.
     *
     * @param  Request  $request client request with new access info.
     * @return  view
     *
     */
    public function store(Request $request) {

        $this->validate($request,$this->rules());

        $input = $request->all();

        foreach ($input as $key => $value) {

            $input[$key] = strip_tags($value);
        }

        $input['created_by'] = Auth::user()->id;
        $input['updated_by'] = Auth::user()->id;

        $access = PdAccess::create($input);

        if ($access) {

            //Insert in the log
            $log_controller = new PdLogController();
            $log_controller->store($request->ip(),'pd_access',$access->pd_access_id,'New access created');


            return redirect()->back()
                ->with('success','The access has been successfully created !!!');
              
        } else {
        
            return redirect()->back()
                ->with('error','There was a problem creating the access !!!');

        }

    }


    /**
     * Shows the form for editing the specified access.
     *
     * @param  int  $id  id of the access in pd_access
     * @return  view
     *
     */
    public function edit($id) {

        $access = PdAccess::findOrFail($id);

        return view('access.edit',['access'=>$access]);
    
    }

    /**
     * Updates the specified access in the system.
     *
     * @param  Request  $request  client request with access info.
     * @param  int  $id  id of the access in pd_access
     * @return  view
     */
    public function update(Request $request, $id) {

        $this->validate($request,$this->rules());

        $input = $request->all();

        foreach ($input as $key => $value) {

            $input[$key] = strip_tags($value);
        }
        
        $access = PdAccess::find($id);
        if ($access->update($input))
        {
            $access->updated_by = Auth::user()->id;
            $access->save();

            //Insert in the log
            $log_controller = new PdLogController();
            $log_controller->store($request->ip(),'pd_access',$access->pd_access_id,'Access updated');
            
            return redirect()->back()
                ->with('success','The access has been successfully updated !!!');
        } else {
            return redirect()->back()
                ->with('error','There was a problem updating the access !!!');

        }

    }
 
    /**
     * Removes the specified access from the system.
     *
     * @param  Request  $request client request
     * @param  int  $id  id of the access in pd_access
     * @return  view
     */
    public function destroy(Request $request,$id) {

        $access = PdAccess::find($id);


            $access->updated_by = Auth::user()->id;
            $access->save();
            if($access->delete()) {

                //Insert in the log
                $log_controller = new PdLogController();
                $log_controller->store($request->ip(),'pd_access',$access->pd_access_id,'Access deleted');

                $access->levels()->sync([]);

                return redirect()->back()
                    ->with('success','The access has been successfully removed !!!');

            } else {
                return redirect()->back()
                    ->with('error','There was a problem removing the access !!!');

            }

       
    }

    
}
