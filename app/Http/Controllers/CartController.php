<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Surfsidemedia\Shoppingcart\Facades\Cart;

class CartController extends Controller
{
    //
    public function index()
    {
        $items = Cart::instance('cart')->content();
        return view('cart',compact('items'));
    }
    
    public function add_to_cart(Request $request)
    {
        
        Cart::instance('cart')->add($request->id,$request->name,$request->quantity,$request->price)->associate('App\Models\Product');
     
        return redirect()->back();
    }

    public function increase_cart_quantity($rowid)
    {
        $product = Cart::instance('cart')->get($rowid);
        $qty = $product->qty + 1;
        cart::instance('cart')->update($rowid,$qty);
        $this->calculate_discount();
        return redirect()->back();
    }
    public function decrease_cart_quantity($rowid)
    {
        $product = Cart::instance('cart')->get($rowid);
        $qty = $product->qty - 1;
        cart::instance('cart')->update($rowid,$qty);
        $this->calculate_discount();
        return redirect()->back();
    }

    public function remove_item($rowid)
    {
        Cart::instance('cart')->remove($rowid);
        $this->calculate_discount();
        return redirect()->back();
    }

    public function empty_cart()
    {
        Cart::instance('cart')->destroy();
        return redirect()->back();
    }

    public function apply_coupon_code(Request $request)
    {
        $coupon_code = $request->coupon_code;
       
        if(isset($coupon_code))
        {
            $coupon = Coupon::where('code', $coupon_code)->where('expiry_date','>=',Carbon::today())
            ->where('cart_value','<=',Cart::instance('cart')->subtotal())->first();
        
            if(!$coupon)
            {
                return redirect()->back()->with('error', 'Please enter valid coupon code');
            }
            else
            {
                Session::put('coupon', [
                    'code' => $coupon->code,
                    'type' => $coupon->type,
                    'value' => $coupon->value,
                    'cart_value' => $coupon->cart_value
                ]);
               
                $this->calculate_discount();
                return redirect()->back()->with('success', 'Coupon code applied successfully');
            }
        }
        else
        {
            return redirect()->back()->with('error', 'Please enter valid coupon code');
        }
    }
    public function calculate_discount()
    {
        $discount = 0;
        $cart = Cart::instance('cart');
    
        if(Session::has('coupon'))
        {
            $coupon = Session::get('coupon');
            if($coupon['type'] == 'fixed')
            {
                $discount = $coupon['value'];
            } 
            else
            {
                $discount = ($cart->subtotal() * $coupon['value']) / 100;
            }
    
            $subtotalAfterDiscount = $cart->subtotal() - $discount;
            $taxAfterDiscount = ($subtotalAfterDiscount * config('cart.tax')) / 100;
            $totalAfterDiscount = $subtotalAfterDiscount + $taxAfterDiscount;
    
            Session::put('discounts', [
                'discount' => number_format($discount, 2, '.', ''),
                'subtotal' => number_format($subtotalAfterDiscount, 2, '.', ''),
                'tax' => number_format($taxAfterDiscount, 2, '.', ''),
                'total' => number_format($totalAfterDiscount, 2, '.', ''),
            ]);
        }
    }

    public function remove_coupon_code()
    {
        Session::forget('coupon');
        Session::forget('discounts');
        return back()->with('success','Coupon has been remove');
    }

    public function checkout()
    {
        if(!Auth::check())
        {
            return redirect()->route('login');
        }        //Address model                        
        $address = Address::where('user_id',Auth::user()->id)->where('is_default',1)->first();
        return view('checkout',compact('address'));
    }
    
    public function place_an_order(Request $request)
    {
       
        $user_id = Auth::user()->id;
        $address = Address::where('user_id',$user_id)->where('is_default',1)->first();

        if(!$address)
        {
            $request->validate([
                'name' => 'required|max:100',
                'phone' => 'required|numeric|digits:10',
                'zip' => 'required|numeric|digits:6',
                'state' => 'required',
                'city' => 'required',
                'address' => 'required',
                'locality' => 'required',
                'landmark' => 'required',
            ]);

            $address = new Address();
            $address->user_id = $user_id;
            $address->name = $request->name;
            $address->phone = $request->phone;
            $address->zip = $request->zip;
            $address->state = $request->state;
            $address->city = $request->city;
            $address->address = $request->address;
            $address->locality = $request->locality;
            $address->landmark = $request->landmark;
            $address->country = $request->country;
           $address->is_default = 1;
            $address->save();

        }
         $this->setAmountforCheckout();

         // NEW: Check if checkout session data exists
         if (!Session::has('checkout')) {
             return redirect()->route('cart.index')->with('error', 'Checkout information is missing. Please try again.');
         }

         // NEW: Retrieve checkout data once
         $checkout = Session::get('checkout');

         $order = new Order();
         $order->user_id = $user_id;
         // CHANGED: Use $checkout instead of Session::get('checkout')
         $order->subtotal = $checkout['subtotal'];
         $order->discount = $checkout['discount'];
         $order->tax = $checkout['tax'];
         $order->total = $checkout['total'];
         $order->name = $address->name;
         $order->phone = $address->phone;
         $order->locality = $address->locality;
         $order->address = $address->address;
         $order->city = $address->city;
         $order->state = $address->state;
         $order->country = $address->country;
         $order->landmark = $address->landmark;
         $order->zip = $address->zip;
       
         $order->save();

        

        foreach(Cart::instance('cart')->content() as $item)
        {
            $orderItem = new OrderItem(); // create new object
            $orderItem->order_id = $order->id;
            $orderItem->price = $item->price;
            $orderItem->quantity = $item->qty;
            $orderItem->product_id = $item->id;
            $orderItem->save();
        }
        if($request->mode == 'card')
        {
            //

        }
        else if($request->mode == 'paypal')
        {
            //

        }
        if($request->mode == 'cod')
        {
        
        $transaction = new Transaction();
        $transaction->user_id = $user_id;
        $transaction->order_id = $order->id;
        $transaction->mode = $request->mode;
        $transaction->status = "pending";
        $transaction->save();

        }
        
         Cart::instance('cart')->destroy();
         Session::forget('checkout');
         Session::forget('coupon');
         Session::forget('discounts');
        Session::put('order_id', $order->id);
        
        // Add this debugging line
       
        
        return redirect()->route('cart.order.confirmation');
    }

    public function setAmountforCheckout()
    {
        // CHANGED: Check cart count instead of content()->count()
        if (!Cart::instance('cart')->count() > 0) {
            Session::forget('checkout');
            return;
        }

        // NEW: Default checkout data
        $checkout = [
            'discount' => 0,
            'subtotal' => Cart::instance('cart')->subtotal(),
            'tax' => Cart::instance('cart')->tax(),
            'total' => Cart::instance('cart')->total(),
        ];

        // CHANGED: Simplified coupon check and data assignment
        if (Session::has('coupon')) {
            $checkout = [
                'discount' => Session::get('discounts')['discount'],
                'subtotal' => Session::get('discounts')['subtotal'],
                'tax' => Session::get('discounts')['tax'],
                'total' => Session::get('discounts')['total'],
            ];
        }

        // CHANGED: Always set checkout data
        Session::put('checkout', $checkout);
    }
    public function order_confirmation()
    {
        if(Session::has('order_id'))
        {
            $order = Order::find(Session::get('order_id'));
            return view('order_confirmation', compact('order'));
        }
        return redirect()->route('cart.index'); // or wherever you want to redirect if there's no order_id
    }
}
