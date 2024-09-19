<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index()
    {
        return view('user.index');
    }
    public function orders()
    { 
        $orders = Order::where('user_id',Auth::user()->id)->orderby('created_at','desc')->paginate(10);
        return view('user.orders',compact('orders'));
    }

    public function order_details($order_id)
    {
        $order = Order::where('user_id',Auth::user()->id)->where('id',$order_id)->first();
        if($order)
    {
        $orderItems = OrderItem::where('order_id', $order_id)->orderby('id')->paginate(10);
        $transaction = Transaction::where('order_id', $order_id)->first();
        return view('user.order-details',compact('order','orderItems','transaction'));
    }
    else
    {
        return redirect()->route('login');
       
    }
    }

    public function order_cancel(Request $request)
    {
        $order = Order::findOrFail($request->order_id);
        $order->status = "cancelled";
     
        $order->cancelled_date = Carbon::now();
        $order->save();

        return back()->with('status', 'Order has been cancelled successfully!');
    }
}
