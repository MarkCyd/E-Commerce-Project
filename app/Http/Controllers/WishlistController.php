<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Surfsidemedia\Shoppingcart\Facades\Cart;


class WishlistController extends Controller
{
    //
    public function index()
    {
        $items = cart::instance('wishlist')->content();
        return view('wishlist',compact('items'));
    }

    public function add_to_wishlist(Request $request)
    {
        Cart::instance('wishlist')->add($request->id,$request->name,$request->quantity,$request->price)->associate(Product::class); 
        return redirect()->back();
    }

    public function remove_item($rowid)
    {
        Cart::instance('wishlist')->remove($rowid);
        return redirect()->back();
    }

    public function empty_wishlist()
    {
        Cart::instance('wishlist')->destroy();
        return redirect()->back();
    }

    public function  move_to_cart($rowid)
    {
        $item = Cart::instance('wishlist')->get($rowid);
        Cart::instance('wishlist')->remove($rowid);
        Cart::instance('cart')->add($item->id,$item->name,$item->qty,$item->price)->associate(Product::class);
        return redirect()->back();
    }
    
}
