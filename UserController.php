<?php

/**
  * UserController controller
  *
  * This file holds the methods used to deal with the users in the system.
  *
  * @author Yunior Rodriguez
  * @author yunior@thecomicsfactory.com
  */
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\User;
use App\Models\PdLevel;
use App\Models\PdPrinter;
use App\Models\PdAccess;
use App\Http\Controllers\Validator;
use Auth;
use Hash;
use Illuminate\Auth\Passwords;
use Illuminate\Support\Facades\Input;
use File;

/**
  * UserController class
  *
  * Extends Controller.
  * @package App\Http\Controllers
  *
  */
class UserController extends Controller {

    /**
     * Public UserController class constructor.
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
     * @param  int  $id  user id
     * @return  array  array with validation rules.
     *
     */
    protected function rules($id) {

        return [
            'first_name' => 'required|max:50',
            'last_name' => 'required|max:50',
            'email' => 'required|email|max:50|unique:pd_user,email,'.$id,
            'password' => 'confirmed|min:6',
            'pd_level_id' => 'required',
            'image' => 'image|mimes:jpg,jpeg',
        ];
    }

    /**
     * Validation rules for user creation.
     *
     * @return  array  array with validation rules.
     *
     */
    protected function rulesCreate() {

        return [
            'first_name' => 'required|max:50',
            'last_name' => 'required|max:50',
          //  'email' => 'required|email|max:50|unique:pd_user',
            'email' => 'required|email|max:50|unique:pd_user,email,NULL,id,deleted_at,NULL',
            'password' => 'confirmed|min:6',
            'pd_level_id' => 'required',
            'image' => 'image|mimes:jpg,jpeg',
         ];

    }

    /**
     * Validation messages.
     *
     * @return  array  array with validation messages.
     *
     */
    protected function messages() {

        return [
            'pd_level_id.required' => 'Please select a Level',
            'printers.required' => 'User must have a printer at least',
            ];

    }
    /**
     * Displays a listing of the users in the system.
     *
     * @param  Request  $request  client request
     * @return  view
     */
    public function index(Request $request) {

        $levels = PdLevel::orderBy('name')->pluck('name', 'pd_level_id');
        $levels[''] = 'Level';
        $allowed_columns = ['id', 'name', 'email', 'level']; 
        $sort = in_array($request->query('sort'), $allowed_columns) ? $request->query('sort') : 'id'; 
        $order = $request->query('order') === 'asc' ? 'asc' : 'desc'; 
        $filters = 0;
        $sort_order = 'ASC';
        foreach ($request->session()->all() as $key => $value) {
            if (strpos($key, 'user') === 0) {
                $filters++;
            }
        }


            $users = User::name(session('user_name_filter'))
                ->email(session('user_email_filter'))
                ->level(session('user_level_filter'));

            if(isset($request['sort_field'])) {

              $users = $users->orderBy($request['sort_field'],$request['sort_order']);
              $sort_order = $request['sort_order'];

            }

        return view('users.index', ['users' => $users->paginate(30)->appends(Input::except('page')),'levels'=>$levels,'filters'=>$filters,'sort_order'=>$sort_order]);
    
    }

    /**
     * Sets the session filters for the users.
     *
     * @param  Request  $request  client request with the filter values.
     * @return  void
     */
    public function setFilter(Request $request) {

         if ($request->has('first_name')) {

                $request->session()->put('user_name_filter', $request['first_name']);

        }

         if ($request->has('email')) {

                $request->session()->put('user_email_filter', $request['email']);

        }

        if ($request->has('pd_level_id')) {

                $request->session()->put('user_level_filter', $request['pd_level_id']);

        }
        
    }

    /**
     * Deletes the session filters for users.
     *
     * @param  Request  $request  client request
     * @return  void
     */
    public function unsetFilter(Request $request) {

        if(session('user_name_filter')) {

            $request->session()->forget('user_name_filter');

        }

        if(session('user_email_filter')) {

            $request->session()->forget('user_email_filter');

        }

        if(session('user_level_filter')) {

            $request->session()->forget('user_level_filter');

        }


    }

    /**
     * Shows the form for creating a new user.
     *
     * @return  view
     */
    public function create() {

        $levels = PdLevel::orderBy('name')->pluck('name', 'pd_level_id');
        
       // $printers = PdPrinter::lists('name', 'pd_printer_id');
       
        return view('users.create',['levels'=>$levels]);
    
    }

    /**
     * Stores a newly created user in the system.
     *
     * @param  Request  $request  client request with user info.
     * @return  view
     */
    public function store(Request $request) {

        $this->validate($request,$this->rulesCreate(),$this->messages());

        $input = $request->all();

        foreach ($input as $key => $value) {

            $input[$key] = strip_tags($value);
        }
        
        $input['api_token'] = str_random(60);
        $input['password'] = bcrypt($input['password']);
        //$input['password'] = Hash::make($input['password']);

        $user = User::create($input);

        if ($user) {

            $id = $user->id;
            $user->created_by = Auth::user()->id;
            $user->updated_by = Auth::user()->id;
            $user->save();

            //Insert in the log
            $log_controller = new PdLogController();
            $log_controller->store($request->ip(),'pd_user',$user->id,'User created');
            //$user->printers()->sync($request->get('printers'));

            if ($request->hasFile('image')) {

                $file = array('image' => $request->file('image'));
                // checking file is valid.
                if ($request->file('image')->isValid()) {

                    $destinationPath = public_path().'/img/users'; // upload path
                    $extension = $request->file('image')->getClientOriginalExtension(); // getting image extension
                    $fileName = $id.'.'.$extension; // renameing image
                    $request->file('image')->move($destinationPath, $fileName); // uploading file to given path

                }

            }

           // $user->postEmail($request);

            return redirect('users')
                ->with('success','The user has been successfully created !!!');
              
        } else {
        
            return redirect()->back()
                ->with('error','There was a problem creating the user !!!');

        }

    }

    /**
     * Returns the permissions for an access.
     *
     * @param  Request  $request  client request with pd_access_id
     * @return  array
     */
    public function getUserPermissions(Request $request) {

        $user_access = Auth::user()->getUserAccess($request->input('access_id'));

        $level_access = Auth::user()->getLevelAccess($request->input('access_id'));

        return [$user_access,$level_access];
    
    }

    /**
     * Shows the form for editing the specified user.
     *
     * @param  int  $id  user id
     * @return  view
     */
    public function edit($id) {

        $user = User::findOrFail($id);
        $access = PdAccess::paginate(30);
        $levels = PdLevel::orderBy('name')->pluck('name', 'pd_level_id');

       // $printers = PdPrinter::lists('name', 'pd_printer_id');
        return view('users.edit',['user'=>$user,'levels'=>$levels,'access'=>$access]);
    
    }

    /**
     * Updates the specified user in the system.
     *
     * @param  Request  $request  client request with user info.
     * @param  int  $id  user id
     * @return  view
     */
    public function update(Request $request, $id) {

        $this->validate($request,$this->rules($id),$this->messages());
        
        $user = User::find($id);
        if ($user->update([
            'first_name' => $request['first_name'],
            'last_name' => $request['last_name'],   
            'email' => $request['email'],
            'password' => $request['password'] === '' ? $user->password : bcrypt($request['password']),
           // 'password' => $request['password'] === '' ? $user->password : Hash::make($request['password']),
            'pd_level_id' => $request['pd_level_id'],
            'computer_id' => $request['computer_id']
        ]))
        {
            $user->updated_by = Auth::user()->id;
            $user->save();
            //Insert in the log
            $log_controller = new PdLogController();
            $log_controller->store($request->ip(),'pd_user',$user->id,'User updated');
           // $user->printers()->sync($request->get('printers'));
            if ($request->hasFile('image'))  {

                $file = array('image' => $request->file('image'));
                // checking file is valid.
                if ($request->file('image')->isValid()) {
                    $destinationPath = public_path().'/img/users'; // upload path
                    $extension = $request->file('image')->getClientOriginalExtension(); // getting image extension
                    $fileName = $id.'.'.$extension; // renameing image
                    $request->file('image')->move($destinationPath, $fileName); // uploading file to given path

                }

            }

            return redirect('/users')
                ->with('success','The user has been successfully updated !!!');
        } else {
            return redirect()->back()
                ->with('error','There was a problem updating the user !!!');

        }

    }
 
    /**
     * Removes the specified user from the system.
     *
     * @param  Request  $request  client request
     * @param  int  $id  user id
     * @return  view
     */
    public function destroy(Request $request,$id) {

        $user = User::find($id);
        
        $user->updated_by = Auth::user()->id;
        $user->save();
        if($user->delete()) {

            //Insert in the log
            $log_controller = new PdLogController();
            $log_controller->store($request->ip(),'pd_user',$user->id,'User deleted');

            if(File::exists(public_path('img/users')."/$id.jpg")) {

                unlink(public_path('img/users')."/$id.jpg");

            }

            //$user->printers()->sync([]);
            return redirect()->back()
                ->with('success','The user has been successfully removed !!!');

        } else {
            return redirect()->back()
                ->with('error','There was a problem removing the user !!!');

        }

    }

    /**
     * Sends reset password link.
     *
     * @param  int  $id  user id
     * @return  view
     */
    public function sendReset($id) {

        $user = User::findOrFail($id);
        $request = new Request();
        $request['email'] = $user->email;
        $user->postEmail($request);
        return redirect()->back()
            ->with('success','The reset password link has been successfully sent !!!');
    
    }
    
}
