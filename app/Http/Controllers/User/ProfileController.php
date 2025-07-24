<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordChangeRequest;
use Modules\Ecommerce\Entities\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Image;
use Str;

class ProfileController extends Controller
{
    public function dashboard(){

        $user = Auth::guard('web')->user();

        $orders = Order::where('user_id', $user->id)->latest()->take(5)->get();

        $pending_orders = Order::where('user_id', $user->id)->where(['order_status' => 0])->latest()->count();

        $complete_orders = Order::where('user_id', $user->id)->where(function ($query) {
            $query->where('order_status', 1);
        })->latest()->count();

        $total = Order::where('user_id', $user->id)
            ->latest()
            ->sum('total');

        return view('user.dashboard', [
            'pending_orders' => $pending_orders,
            'complete_orders' => $complete_orders,
            'total' => $total,
            'orders' => $orders,
        ]);

    }

    public function edit_profile(){

        $user = Auth::guard('web')->user();

        return view('user.edit_profile', ['user' => $user]);
    }

    public function update_profile(Request $request){

        $user = Auth::guard('web')->user();
        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->address = $request->address;
        $user->save();

        if($request->file('image')){
            $old_image = $user->image;
            $user_image = $request->image;
            $extention = $user_image->getClientOriginalExtension();
            $image_name = Str::slug($user->name).date('-Y-m-d-h-i-s-').rand(999,9999).'.'.$extention;
            $image_name = 'uploads/custom-images/'.$image_name;
            Image::make($user_image)->save(public_path().'/'.$image_name);
            $user->image = $image_name;
            $user->save();
            if($old_image){
                if(File::exists(public_path().'/'.$old_image))unlink(public_path().'/'.$old_image);
            }
        }

        $notify_message = trans('translate.Updated successfully');
        $notify_message = array('message' => $notify_message, 'alert-type' => 'success');
        return redirect()->back()->with($notify_message);

    }

    public function change_password(){
        return view('user.change_password');
    }

    public function update_password(PasswordChangeRequest $request){

        $user = Auth::guard('web')->user();

        if(Hash::check($request->current_password, $user->password)){
            $user->password = Hash::make($request->password);
            $user->save();

            $notify_message = trans('translate.Password changed successfully');
            $notify_message = array('message' => $notify_message, 'alert-type' => 'success');
            return redirect()->back()->with($notify_message);

        }else{
            $notify_message = trans('translate.Current password does not match');
            $notify_message = array('message' => $notify_message, 'alert-type' => 'error');
            return redirect()->back()->with($notify_message);
        }


    }

    public function orders(){
        $user = Auth::guard('web')->user();

        $orders = Order::with('order_detail')->where('user_id', $user->id)->latest()->paginate(10);

        return view('user.orders', ['orders' => $orders,]);
    }

    public function transactions(){
        $user = Auth::guard('web')->user();

        $orders = Order::with('order_detail')->where('user_id', $user->id)->latest()->paginate(10);

        return view('user.transactions', ['orders' => $orders,]);
    }

    public function order_show($order_id){

        $user = Auth::guard('web')->user();
        $order = Order::with('order_detail')->where('user_id', $user->id)->where('order_id', $order_id)->first();


        return view('user.order_show', [
            'order' => $order,
        ]);
    }

    public function account_delete(){
        return view('user.account_delete');
    }

    public function confirm_account_delete(Request $request)
    {
        try {
            $user = Auth::guard('web')->user();

            if (!$user) {
                Log::warning('Account deletion attempt failed: User not found');
                return redirect()
                    ->back()
                    ->with([
                        'message' => 'User not found',
                        'alert-type' => 'error'
                    ]);
            }

            if (!Hash::check($request->password, $user->password)) {
                Log::warning('Account deletion failed: Password mismatch for user ' . $user->id);
                return redirect()
                    ->back()
                    ->with([
                        'message' => trans('translate.Password does not match. Please try again.'),
                        'alert-type' => 'error'
                    ]);
            }

            DB::beginTransaction();

            try {
                // Handle image deletion
                if ($user->image) {
                    $imagePath = public_path($user->image);
                    if (File::exists($imagePath)) {
                        File::delete($imagePath);
                        Log::info('Deleted image for user: ' . $user->id);
                    } else {
                        Log::warning('Image not found or already null for user: ' . $user->id);
                    }
                }

                // Delete related records
                DB::table('product_reviews')->where('user_id', $user->id)->delete();
                DB::table('orders')->where('user_id', $user->id)->delete();
                DB::table('wishlists')->where('user_id', $user->id)->delete();
                DB::table('carts')->where('user_id', $user->id)->delete();

                // Force delete the user
                DB::enableQueryLog(); // Start query logging
                $user->forceDelete();
                Log::info('Query log after force delete: ', DB::getQueryLog());

                // Confirm user deletion
                $userExists = DB::table('users')->where('id', $user->id)->exists();
                if ($userExists) {
                    throw new \Exception('User still exists in the database after forceDelete.');
                }

                DB::commit();

                Auth::guard('web')->logout();
                Session::flush();

                Log::info('Successfully deleted account for user: ' . $user->id);

                return redirect()
                    ->route('home')
                    ->with([
                        'message' => trans('translate.Your account deleted successful'),
                        'alert-type' => 'success'
                    ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Account deletion transaction failed for user ' . $user->id . ': ' . $e->getMessage());
                return redirect()
                    ->back()
                    ->with([
                        'message' => 'Failed to delete account: ' . $e->getMessage(),
                        'alert-type' => 'error'
                    ]);
            }
        } catch (\Exception $e) {
            Log::error('Account deletion pre-check failed: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with([
                    'message' => trans('translate.Something went wrong. Please try again.'),
                    'alert-type' => 'error'
                ]);
        }
    }

}
