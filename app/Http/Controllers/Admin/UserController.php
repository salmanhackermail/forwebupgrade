<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Ecommerce\Entities\Order;
use  File;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function user_list(){

        $users = User::where('status', 'enable')->latest()->get();

        $title = trans('translate.Users List');

        return view('admin.user.user_list', ['users' => $users, 'title' => $title]);
    }

    public function pending_user(){

        $users = User::where('status', 'disable')->latest()->get();

        $title = trans('translate.Pending Users');

        return view('admin.user.user_list', ['users' => $users, 'title' => $title]);
    }

    public function user_show($id){

        $user = User::findOrFail($id);

        $total_order = Order::where('user_id', $user->id)->count();

        $success_order = Order::where('order_status', 'complete')->where('user_id', $user->id)->count();


        $orders = Order::with('order_detail')->where('user_id', $user->id)->latest()->get();

        return view('admin.user.user_show', [
            'user' => $user,
            'total_order' => $total_order,
            'success_order' => $success_order,
            'orders' => $orders,
        ]);

    }

    public function update(Request $request ,$id){

        $user = User::findOrFail($id);

        $rules = [
            'name'=>'required',
            'phone'=>'required',
            'address'=>'required|max:220',
        ];

        $customMessages = [
            'name.required' => trans('translate.Name is required'),
            'phone.required' => trans('translate.Phone is required'),
            'address.required' => trans('translate.Address is required')
        ];

        $this->validate($request, $rules,$customMessages);

        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->address = $request->address;
        $user->status = $request->status ? 'enable' : 'disable';
        $user->save();

        $notify_message= trans('translate.User updated successful');
        $notify_message=array('message'=>$notify_message,'alert-type'=>'success');
        return redirect()->back()->with($notify_message);
    }

    public function user_destroy($id){


        $user = User::find($id);
        $user_image = $user->image;

        if($user_image){
            if(File::exists(public_path().'/'.$user_image))unlink(public_path().'/'.$user_image);
        }

        $user->delete();

        $notify_message = trans('translate.Delete Successfully');
        $notify_message = array('message'=>$notify_message,'alert-type'=>'success');
        return redirect()->route('admin.user-list')->with($notify_message);
    }

    public function user_status($id){
        $user = User::findOrFail($id);
        if($user->status == 'enable'){
            $user->status = 'disable';
            $user->save();
            $message = trans('translate.Status Changed Successfully');
        }else{
            $user->status = 'enable';
            $user->save();
            $message = trans('translate.Status Changed Successfully');
        }
        return response()->json($message);
    }


    public function user_feez($id){
        $user = User::findOrFail($id);

        if($user->feez_status == 1){
            $user->feez_status = 0;
            $user->save();
            $notify_message = trans('translate.Unfreeze This Profile Successfully');
        }else{
            $user->feez_status = 1;
            $user->save();

            $notify_message = trans('translate.Freeze This Profile Successfully');
        }
        $notify_message = array('message'=>$notify_message,'alert-type'=>'success');
        return redirect()->back()->with($notify_message);
    }
}
