<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Coupon;
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
    
}
