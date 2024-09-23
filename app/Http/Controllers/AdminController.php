<?php

namespace App\Http\Controllers;

 

use App\Models\Brand;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Slide;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;


class AdminController extends Controller
{
    public function index()
    {
        $orders = Order::orderBy('created_at', 'DESC')->get()->take(10);
        $dashboardData = DB::select("SELECT SUM(total) AS TotalAmount, 
                    SUM(CASE WHEN status='ordered' THEN total ELSE 0 END) AS totalOrderedAmount,
                    SUM(CASE WHEN status='delivered' THEN total ELSE 0 END) AS totalDeliveredAmount,
                    SUM(CASE WHEN status='cancelled' THEN total ELSE 0 END) AS totalCanceledAmount,
                    COUNT(*) AS Total,
                    SUM(CASE WHEN status='ordered' THEN 1 ELSE 0 END) AS totalOrdered,
                    SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) AS totalDelivered,
                    SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS totalCanceled
                    FROM Orders"); //sqlite specific code 

        $monthlyData = DB::SELECT ("SELECT M.id AS MonthNo, M.name AS MonthName,
                        IFNULL(O.TotalAmount, 0) AS TotalAmount,
                        IFNULL(O.TotalOrderedAmount, 0) AS TotalOrderedAmount,
                        IFNULL(O.TotalDeliveredAmount, 0) AS TotalDeliveredAmount,
                        IFNULL(O.TotalCanceledAmount, 0) AS TotalCanceledAmount
                         FROM month_names M
                         LEFT JOIN (
                        SELECT strftime('%m', created_at) AS MonthNo,
                            strftime('%b', created_at) AS MonthName,
                            SUM(total) AS TotalAmount,
                            SUM(CASE WHEN status = 'ordered' THEN total ELSE 0 END) AS TotalOrderedAmount,
                            SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END) AS TotalDeliveredAmount,
                            SUM(CASE WHEN status = 'cancelled' THEN total ELSE 0 END) AS TotalCanceledAmount
                        FROM Orders
                        WHERE strftime('%Y', created_at) = strftime('%Y', 'now')
                        GROUP BY strftime('%Y', created_at), strftime('%m', created_at), strftime('%b', created_at)
                        ) O ON O.MonthNo = M.id
                        ORDER BY M.id;
                    ");
        $AmountM = implode(",", collect($monthlyData)->pluck('TotalAmount')->toArray());
        $orderedAmountM = implode(",", collect($monthlyData)->pluck('TotalOrderedAmount')->toArray());
        $deliveredAmountM = implode(",", collect($monthlyData)->pluck('TotalDeliveredAmount')->toArray());      
        $canceledAmountM = implode(",", collect($monthlyData)->pluck('TotalCanceledAmount')->toArray());

        $totalAmount = collect($monthlyData)->sum('TotalAmount');
        $totalOrderedAmount = collect($monthlyData)->sum('TotalOrderedAmount');
        $totalDeliveredAmount = collect($monthlyData)->sum('TotalDeliveredAmount');
        $totalCanceledAmount = collect($monthlyData)->sum('TotalCanceledAmount');

        return view('admin.index', compact('orders','dashboardData','AmountM','orderedAmountM','deliveredAmountM','canceledAmountM','totalAmount','totalOrderedAmount','totalDeliveredAmount','totalCanceledAmount'));
    }

        /* $orders = Order::orderBy('created_at', 'DESC')->get()->take(10); //user this for mysql
$dashboardData = DB::select("select sum(total) As TotalAmount,
                            sum(if(status='ordered',total,0)) As totalOrderedAmount,
                            sum(if(status='delivered',total,0)) As totalDeliveredAmount,
                            sum(if(status='canceled',total,0)) As totalCanceledAmount,
                            Count(*) As Total,
                            sum(if(status='ordered',1,0)) As totalOrdered,
                            sum(if(status='delivered',1,0)) As totalDelivered,
                            sum(if(status='canceled',1,0)) As totalCanceled
                            From Orders"); 
                            
      //just add the dbstring                     SELECT M.id AS MonthNo, M.name AS MonthName,
    IFNULL(O.TotalAmount,0) AS TotalAmount,
    IFNULL(O.TotalOrderedAmount,0) AS TotalOrderedAmount,
    IFNULL(O.TotalDeliveredAmount,0) AS TotalDeliveredAmount,
    IFNULL(O.TotalCanceledAmount,0) AS TotalCanceledAmount 
FROM month_names M
LEFT JOIN (Select DATE_FORMAT(created_at, '%b') AS MonthName,
    MONTH(created_at) AS MonthNo,
    sum(total) AS TotalAmount,
    sum(if(status='ordered',total,0)) AS TotalOrderedAmount,
    sum(if(status='delivered',total,0)) AS TotalDeliveredAmount,
    sum(if(status='canceled',total,0)) AS TotalCanceledAmount
    From Orders WHERE YEAR(created_at)=YEAR(NOW()) GROUP BY YEAR(created_at), MONTH(created_at), DATE_FORMAT(created_at, '%b')
) O ON O.MonthNo=M.id
Order By MONTH(created_at)
                            
                            */
   
    //brands
    public function brands()
    {
        $brands = Brand::orderBy('id', 'DESC')->paginate(10);
        return view('admin.brands', compact('brands'));
    }

    public function add_brand()
    {
        return view('admin.brand-add');
    }

    public function brand_store(Request $request)
    {        
         $request->validate([
              'name' => 'required',
              'slug' => 'required|unique:brands,slug',
              'image' => 'mimes:png,jpg,jpeg|max:2048'
         ]);
    
         $brand = new Brand();
         $brand->name = $request->name;
         $brand->slug = Str::slug($request->name);
         $image = $request->file('image');
         $file_extention = $request->file('image')->extension();
         $file_name = Carbon::now()->timestamp . '.' . $file_extention;        
         $this->generateBrandThumbnailImage($image,$file_name);
         $brand->image = $file_name;        
         $brand->save();
         return redirect()->route('admin.brands')->with('status','Record has been added successfully !');
    }
    public function brand_edit($id)
    {
        $brand = Brand::findOrFail($id);
        return view('admin.brand-edit', compact('brand'));
    }

    public function brand_update(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'slug' => 'required|unique:brands,slug,'.$request->id,
            'image' => 'mimes:png,jpg,jpeg|max:2048'
        ]);
        $brand = Brand::find($request->id);
        $brand->name = $request->name;
        $brand->slug = Str::slug($request->slug);
        if($request->hasFile('image'))
        {            
            if (File::exists(public_path('uploads/brands').'/'.$brand->image)) 
            {
                File::delete(public_path('uploads/brands').'/'.$brand->image);
            }
            $image = $request->file('image');
            $file_extention = $request->file('image')->extension();
            $file_name = Carbon::now()->timestamp . '.' . $file_extention;
            $this->generateBrandThumbnailImage($image,$file_name);
            $brand->image = $file_name;
        }        
        $brand->update();        
        return redirect()->route('admin.brands')->with('status','Record has been updated successfully !');
    }

    public function generateBrandThumbnailImage($image, $imageName)
    {
        $destinationPath = public_path('uploads/brands');
        $img = Image::read($image->path()); // This should now work
        $img->cover(124, 124, 'top');
        $img->resize(124, 124, function ($constraint) {
            $constraint->aspectRatio();
        });
        // Crop the image to the exact dimensions
      
        $img->save($destinationPath . '/' . $imageName);
    }

    public function brand_delete($id)
    {
        $brand = Brand::find(request('id'));
        if(File::exists(public_path('uploads/brands').'/'.$brand->image))
        {
            File::delete(public_path('uploads/brands').'/'.$brand->image);
        }
        $brand->delete();
        return redirect()->route('admin.brands')->with('status','Record has been deleted successfully !');
    }

    //Categories
    public function categories()
    {
        $categories = Category::orderBy('id', 'DESC')->paginate(10);
        return view('admin.categories', compact('categories'));
    }

    public function category_add()
    {
        return view('admin.category-add');    
    }
    
    public function category_store(Request $request)
    {   //validate
        $request->validate([
            'name' => 'required',
            'slug' => 'required|unique:categories,slug',
            'image' => 'mimes:png,jpg,jpeg|max:2048'
       ]);
       //process the data
       $category = new Category();
       $category->name = $request->name;
       $category->slug = Str::slug($request->name);
       $image = $request->file('image');
       $file_extention = $request->file('image')->extension();
       $file_name = Carbon::now()->timestamp . '.' . $file_extention;        
       $this->generateCategoryThumbnailImage($image,$file_name);
       $category->image = $file_name;        
       //save
       $category->save();
       //redirect
       return redirect()->route('admin.categories')->with('status','Category has been added successfully !');
    }

    public function generateCategoryThumbnailImage($image, $imageName)
    {
        $destinationPath = public_path('uploads/categories'); // upload path
        $img = Image::read($image->path()); //read image
        $img->cover(124, 124, 'top'); 
        $img->resize(124, 124, function ($constraint) { //resize image
            $constraint->aspectRatio(); //keep ratio
        });
        // Crop the image to the exact dimensions
      
        $img->save($destinationPath . '/' . $imageName); // save image to destination path 
    }
    public function category_edit($id)
    {
        $category = Category::findOrFail($id);
        return view('admin.category-edit', compact('category'));
    }
    public function category_update(Request $request)
    {
        //validate
        $request->validate([
            'name' => 'required',
            'slug' => 'required|unique:categories,slug,'.$request->id,
            'image' => 'mimes:png,jpg,jpeg|max:2048'
        ]);
        //process
        $category = Category::find($request->id); //find id
        $category->name = $request->name; //pass value
        $category->slug = Str::slug($request->slug); //pass value process slug
        if($request->hasFile('image')) //check if it has a file uploaded 
        {            
            if (File::exists(public_path('uploads/categories').'/'.$category->image)) //check if image exists
            {
                File::delete(public_path('uploads/categories').'/'.$category->image); //delete image
            }
            $image = $request->file('image'); //get image from request
            $file_extention = $request->file('image')->extension(); //get image extension
            $file_name = Carbon::now()->timestamp . '.' . $file_extention; //get image name
            $this->generateCategoryThumbnailImage($image,$file_name);
            $category->image = $file_name; //pass image name and extension
        }        
        //update
        $category->update();        
        //redirect
        return redirect()->route('admin.categories')->with('status','Record has been updated successfully !');

    }

    public function category_delete($id)
    {
        $category = Category::find(request('id')); //find id for image location
        if(File::exists(public_path('uploads/categories').'/'.$category->image)) //check if image exists
        {
            File::delete(public_path('uploads/categories').'/'.$category->image); //delete image
        }
        $category->delete(); //delete record
        return redirect()->route('admin.categories')->with('status','Record has been deleted successfully !');
    }

    //product section
    public function products()
    {
        $products = Product::orderBy('created_at', 'DESC')->paginate(10);
        return view('admin.products', compact('products'));
    }
    public function product_add()
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);

        // $categories = Category::select('id','name')->orderBy('name')->get();
        $brands = Brand::orderBy('name')->get(['id', 'name']);

       // dd($categories);
        return view('admin.product-add', compact('categories', 'brands'));
    }
    public function product_store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'slug' => 'required|unique:products,slug',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'short_description' => 'required',
            'description' => 'required',
            'regular_price' => 'required',
            'sale_price' => 'required',
            'SKU' => 'required|unique:products,SKU',
            'stock_status' => 'required',
            'featured' => 'required',
            'quantity' => 'required',
            'image' => 'required|mimes:png,jpg,jpeg|max:2048'
        ]);
    
        $product = new Product();
        $product->name = $request->name;
        $product->slug = Str::slug($request->name);
        $product->short_description = $request->short_description;
        $product->description = $request->description;
        $product->regular_price = $request->regular_price;
        $product->sale_price = $request->sale_price;
        $product->SKU = $request->SKU;
        $product->stock_status = $request->stock_status;
        $product->featured = $request->featured;
        $product->quantity = $request->quantity;
        $product->category_id = $request->category_id;
        $product->brand_id = $request->brand_id;
        $current_timestamp = Carbon::now()->timestamp;

        if ($request->hasFile('image')) 
        {
            $image = $request->file('image');
            $imageName = $current_timestamp . '.' . $image->extension();
            $this->generateProductThumbnailImage($image, $imageName);
            $product->image = $imageName;
        }
    
        $gallery_arr = array();
        $gallery_images = "";
        $counter = 1;
    
        if ($request->hasFile('images')) {
               
            $allowedfileExtension = ['jpg', 'png', 'jpeg'];
            $files = $request->file('images');
    
            foreach ($files as $file) {
                $gextension = $file->getClientOriginalExtension();
                $check = in_array($gextension, $allowedfileExtension);
    
                if ($check) {
                    $gfilename = $current_timestamp . "-" . $counter . "." . $gextension;
                    $this->generateProductThumbnailImage($file, $gfilename);
                    array_push($gallery_arr, $gfilename);
                    $counter = $counter + 1;
                }
            }
            $gallery_images = implode(',', $gallery_arr);
        }
       
        $product->images = $gallery_images;
        $product->save();
        
        return redirect()->route('admin.products')->with('status', 'Record has been added successfully!');
    }
public function generateProductThumbnailImage($image, $imageName)
{
    $destinationPathThumbnail = public_path('uploads/products/thumbnails');
    $destinationPath = public_path('uploads/products');
    $img = Image::read($image->path()); // Use make() instead of read()

    // Save the full-size image
    $img->cover(540,689,"top");
    $img->resize(540, 689, function($constraint) {
        $constraint->aspectRatio();
    })->save($destinationPath . '/' . $imageName);

    // Save the thumbnail
   
    $img->resize(104, 104, function ($constraint) {
        $constraint->aspectRatio();
    })->save($destinationPathThumbnail . '/' . $imageName);
}

    public function product_edit($id)
    {
        $product =  Product::findOrFail($id); 
        $categories = Category::orderBy('name')->get(['id', 'name']); //fetch catergory from database
        $brands = Brand::orderBy('name')->get(['id', 'name']); //fetch band from database
        return view('admin.product-edit',compact('product','brands','categories'));
    }
    
    public function product_update(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'slug' => 'required|unique:products,slug,'.$request->id,
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'short_description' => 'required',
            'description' => 'required',
            'regular_price' => 'required',
            'sale_price' => 'required',
            'SKU' => 'required',
            'stock_status' => 'required',
            'featured' => 'required',
            'quantity' => 'required',
            'image' => 'mimes:png,jpg,jpeg|max:2048'
        ]);
    
        $product = Product::findOrFail($request->id);
        $product->name = $request->name;
        $product->slug = Str::slug($request->name);
        $product->short_description = $request->short_description;
        $product->description = $request->description;
        $product->regular_price = $request->regular_price;
        $product->sale_price = $request->sale_price;
        $product->SKU = $request->SKU;
        $product->stock_status = $request->stock_status;
        $product->featured = $request->featured;
        $product->quantity = $request->quantity;
        $product->category_id = $request->category_id;
        $product->brand_id = $request->brand_id;
        $current_timestamp = Carbon::now()->timestamp;

        if ($request->hasFile('image')) 
        {
            if(File::exists(public_path('uploads/products').'/'.$product->image))
            {
                File::delete(public_path('uploads/products').'/'.$product->image);
            }
            if(File::exists(public_path('uploads/products/thumbnails').'/'.$product->image))
            {
                File::delete(public_path('uploads/products/thumbnails').'/'.$product->image);
            }
            
            
                $image = $request->file('image');
                $imageName = $current_timestamp . '.' . $image->extension();
                $this->generateProductThumbnailImage($image, $imageName);
                $product->image = $imageName;
            
        }
    
        $gallery_arr = array();
        $gallery_images = "";
        $counter = 1;
    
        if ($request->hasFile('images')) {
         
            foreach ( explode(',', $product->images) as $ofile)
            {
                if(File::exists(public_path('uploads/products').'/'.$ofile))
                {
                    File::delete(public_path('uploads/products').'/'.$ofile);
                }
                if(File::exists(public_path('uploads/products/thumbnails').'/'.$ofile))
                {
                    File::delete(public_path('uploads/products/thumbnails').'/'.$ofile);
                }
            }
        
            $allowedfileExtension = ['jpg', 'png', 'jpeg'];
            $files = $request->file('images');
    
            foreach ($files as $file) {
                $gextension = $file->getClientOriginalExtension();
                $check = in_array($gextension, $allowedfileExtension);
    
                if ($check) {
                    $gfilename = $current_timestamp . "-" . $counter . "." . $gextension;
                    $this->generateProductThumbnailImage($file, $gfilename);
                    array_push($gallery_arr, $gfilename);
                    $counter = $counter + 1;
                }
            }
            $gallery_images = implode(',', $gallery_arr);
            $product->images = $gallery_images;
        }
         $product->save();
               
        return redirect()->route('admin.products')->with('status', 'Record has been updated successfully!');

    }

    public function product_delete(Request $request)
    {
        $product = Product::findOrFail($request->id);
        if (File::exists(public_path('uploads/products').'/'.$product->image)) {
            File::delete(public_path('uploads/products').'/'.$product->image);
        }
        if (File::exists(public_path('uploads/products/thumbnails').'/'.$product->image)) {
            File::delete(public_path('uploads/products/thumbnails').'/'.$product->image);
        }

        foreach ( explode(',', $product->images) as $ofile)
        {
            if(File::exists(public_path('uploads/products').'/'.$ofile))
            {
                File::delete(public_path('uploads/products').'/'.$ofile);
            }
            if(File::exists(public_path('uploads/products/thumbnails').'/'.$ofile))
            {
                File::delete(public_path('uploads/products/thumbnails').'/'.$ofile);
            }
        }
        
        $product->delete();
        return redirect()->route('admin.products')->with('status', 'Record has been deleted successfully!');
    }

    public function coupons()
    {
        $coupons = Coupon::orderBy('expiry_date', 'DESC')->paginate(10);
        return view('admin.coupons', compact('coupons'));
    }

    public function coupon_add()
    {
        return view('admin.coupon-add');
    }

    public function coupon_store(Request $request)
    {
        $request->validate([
            'code' => 'required',
            'type' => 'required',
            'value' => 'required|numeric',
            'cart_value' => 'required|numeric',
            'expiry_date' => 'required|date',
        ]);

        $coupon =  new Coupon();
        $coupon->code = $request->code;
        $coupon->type = $request->type;
        $coupon->value = $request->value;
        $coupon->cart_value = $request->cart_value;
        $coupon->expiry_date = $request->expiry_date;
        $coupon->save();

        return redirect()->route('admin.coupons')->with('status', 'Record has been added successfully!');
    }

    public function coupon_edit($id)
    {
        $coupon = Coupon::findOrFail($id);
        return view('admin.coupon-edit', compact('coupon'));
    }

    public function coupon_update(Request $request)
    {
        $request->validate([
            'code' => 'required',   
            'type' => 'required',
            'value' => 'required|numeric',
            'cart_value' => 'required|numeric',
            'expiry_date' => 'required|date',
        ]);

        $coupon =  Coupon::findOrFail($request->id);
        $coupon->code = $request->code;
        $coupon->type = $request->type;
        $coupon->value = $request->value;
        $coupon->cart_value = $request->cart_value;
        $coupon->expiry_date = $request->expiry_date;
        $coupon->save();

        return redirect()->route('admin.coupons')->with('status', 'Record has been updated successfully!');
    }

    public function coupon_delete($id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();
        return redirect()->route('admin.coupons')->with('status', 'Record has been deleted successfully!');
    }

    public function orders()
    {
       // $orders = Order::orderBy('id', 'DESC')->paginate(10); // non eloquent
        $orders = Order::latest('created_at')->paginate(10); // eloquent instead of orderby id desc use latest to remove desc order
        return view('admin.orders', compact('orders'));
    }

    public function order_details($order_id)
    {
        $order = Order::findOrFail($order_id);
        $orderItems = OrderItem::where('order_id', $order_id)->latest('id')->paginate(10);
        $transaction = Transaction::where('order_id', $order_id)->first();
      
        return view('admin.order-details', compact('order', 'orderItems', 'transaction'));
    }

    public function update_order_status(Request $request)
    {
        
        $order = Order::findOrFail($request->order_id);
       
        $order->status = $request->order_status;   
        if($request->order_status == "delivered")
        {
            $order->delivered_date = Carbon::now();
        }
        else if($request->order_status == "cancelled")
        {
            $order->cancelled_date = Carbon::now();
        }
        $order->save();
      
        if($request->order_status == "delivered")
        {
            $transaction = Transaction::where('order_id', $request->order_id)->first();
            $transaction->status = "approved";
            $transaction->save();
        }
        return back()->with('status', 'Order status has been updated successfully!');
    }

    public function slides()
    {
        $slides = Slide::orderby('id','DESC')->paginate(10);
        return view('admin.slides', compact('slides'));
    }

    public function slide_add()
    {
        return view('admin.slide-add');
    }

    public function slide_store(Request $request)
    {
        $request->validate([
            'tagline' => 'required',
            'title' => 'required',
            'subtitle'  => 'required',
            'link' => 'required',
            'status'  => 'required',
            'image' => 'required|mimes:png,jpg,jpeg|max:2048'
        ]);
        $slide = new Slide();
        $slide->tagline = $request->tagline;
        $slide->title = $request->title;
        $slide->subtitle = $request->subtitle;
        $slide->link = $request->link;
        $slide->status = $request->status;
       
        $image = $request->file('image');
        $file_extention = $request->file('image')->extension();
        $file_name = Carbon::now()->timestamp . '.' . $file_extention;        
        $this->generateSlideThumbnailImage($image,$file_name);
        $slide->image = $file_name;        
        
        $slide->save();
        return redirect()->route('admin.slides')->with('status', 'Record has been added successfully!');

    }
    public function generateSlideThumbnailImage($image , $imageName)
    {
    $destinationPath = public_path('uploads/slides'); // upload path
    $img = Image::read($image->path()); //read image
    $img->cover(400, 690, 'top'); 
    $img->resize(400, 690, function ($constraint) { //resize image
        $constraint->aspectRatio(); //keep ratio
    });
    // Crop the image to the exact dimensions
  
    $img->save($destinationPath . '/' . $imageName); // save image to destination path 
    }

    public function slide_edit($id)
    {
        $slide = Slide::findOrFail($id);
        return view('admin.slide-edit', compact('slide'));
    }
    public function slide_update(Request $request)
    {
        $request->validate([
            'tagline' => 'required',
            'title' => 'required',
            'subtitle'  => 'required',
            'link' => 'required',
            'status'  => 'required',
            'image' => 'mimes:png,jpg,jpeg|max:2048'
        ]);
        $slide = Slide::findOrFail($request->id);
        $slide->tagline = $request->tagline;
        $slide->title = $request->title;
        $slide->subtitle = $request->subtitle;
        $slide->link = $request->link;
        $slide->status = $request->status;
       
        if($request->hasFile('image'))
        {
            if(File::exists(public_path('uploads/slides').'/'.$slide->image)) // check if image exists
            {
                File::delete(public_path('uploads/slides').'/'.$slide->image); //delete image
            }
            $image = $request->file('image');
            $file_extention = $request->file('image')->extension();
            $file_name = Carbon::now()->timestamp . '.' . $file_extention;        
            $this->generateSlideThumbnailImage($image,$file_name);
            $slide->image = $file_name;       
        }
        
        
        $slide->save();
        return redirect()->route('admin.slides')->with('status', 'Record has been updated successfully!');

    }

    public function slide_delete($id)
    {
        $slide = Slide::findOrFail($id);
        if (File::exists(public_path('uploads/slides').'/'.$slide->image)) {
            File::delete(public_path('uploads/slides').'/'.$slide->image);
        }
        $slide->delete();
        return back()->with('status', 'Record has been deleted successfully!');
    }

    public function contacts()
    {
        $contacts = Contact::orderBy('created_at', 'DESC')->paginate(10);
        return view('admin.contacts', compact('contacts'));
    }

    public function contact_delete($id)
    {
        $contact = Contact::findOrFail($id);
        $contact->delete();
        return back()->with('status', 'Record has been deleted successfully!');
    }

}
