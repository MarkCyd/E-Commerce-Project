<?php

namespace App\Http\Controllers;

use App\Models\Order;
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
}
