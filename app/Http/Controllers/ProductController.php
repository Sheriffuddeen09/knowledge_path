<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductImage;

class ProductController extends Controller
{
 public function store(Request $request)
{

            $request->validate([
                'title' => 'required|string',
                'price' => 'required|numeric',
                'brand_name' => 'nullable|string',
                'company_name' => 'nullable|string',
                'title' => 'required|string',
                'price' => 'required|numeric',
                'currency' => 'required|string',

                'front_image' => 'nullable|image|max:2048',
                'back_image' => 'nullable|image|max:2048',
                'side_image' => 'nullable|image|max:2048',

                'images.*' => 'nullable|image|max:2048',

                'location' => 'nullable|string',
                'delivery_method' => 'nullable|string',
                'delivery_time_ratio' => 'nullable|string',

                'discount' => 'nullable|numeric',
                'charges' => 'nullable|numeric',

                ]);
            $front = null;
            $back = null;
            $side = null;

            if($request->hasFile('front_image')){
            $front = $request->file('front_image')->store('products','public');
            }

            if($request->hasFile('back_image')){
            $back = $request->file('back_image')->store('products','public');
            }

            if($request->hasFile('side_image')){
            $side = $request->file('side_image')->store('products','public');
            }

            $pdf = null;

            if($request->hasFile('pdf_file')){
            $pdf = $request->file('pdf_file')->store('books','public');
            }

            $totalPrice = $request->price;

            if($request->discount){
                $totalPrice = $totalPrice - $request->discount;
            }

            if($request->charges){
                $totalPrice = $totalPrice + $request->charges;
            }

           $product = Product::create([

                'title'=>$request->title,
                'author'=>$request->author,
                'description'=>$request->description,

                'price'=>$request->price,
                'discount'=>$request->discount,
                'charges'=>$request->charges,

                'currency'=>$request->currency,

                'stock'=>$request->stock,

                'color'=>$request->color,
                'size'=>$request->size,
                'weight'=>$request->weight,

                'brand_name'=>$request->brand_name,
                'company_name'=>$request->company_name,
                'sale_type'=>$request->sale_type,
                'company_available'=>$request->company_available,

                'location'=>$request->location,
                'delivery_method'=>$request->delivery_method,
                'delivery_time_ratio'=>$request->delivery_time_ratio,

                'category_id' =>$request->category_id,

                'front_image'=>$front,
                'back_image'=>$back,
                'side_image'=>$side,
                'pdf_file'=>$pdf,

                'is_digital'=>$request->is_digital

                ]);

            if($request->hasFile('images')){

            foreach($request->file('images') as $index => $img){

            $path = $img->store('product_gallery','public');

            ProductImage::create([
            'product_id'=>$product->id,
            'image_path'=>$path,
            'position'=>$request->positions[$index] ?? 'gallery'
            ]);

            }

            }

            return response()->json([
            'message'=>'Product created',
            'product'=>$product
            ]);

            }




            public function index()
            {

            $products = Product::with('images')
            ->latest()
            ->paginate(20);

            return response()->json($products);

            }


            public function show($id)
            {

            $product = Product::with('images')->findOrFail($id);

            return response()->json($product);

            }

            public function download($id)
            {

            $order = OrderItem::where('product_id',$id)
            ->where('user_id',auth()->id())
            ->firstOrFail();

            $product = Product::find($id);

            return Storage::download($product->pdf_file);

            }

        }
