<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Category;
use Illuminate\Support\Str;

class ProductController extends Controller
{


            // Create Product
        
            public function store(Request $request)
            {

            $request->validate([
                'title' => 'required|string',
                'price' => 'required|numeric',
                'delivery_price' => 'nullable|numeric',
                'brand_name' => 'nullable|string',
                'company_type' => 'nullable|string',
                'sale_type' => 'nullable|string|in:online,physical',
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
                'key_features' => 'nullable|array',
                'specifications' => 'nullable|array',

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

            if ($request->new_subcategory) {

                $newCategory = Category::create([
                    'name' => $request->new_subcategory,
                    'slug' => Str::slug($request->new_subcategory),
                    'parent_id' => $request->parent_id
                ]);

                $categoryId = $newCategory->id;

            } else {
                $categoryId = $request->category_id;
            }


            $saleType = $request->sale_type ?? 'physical';


            $product = Product::create([
            'title' => $request->title,
            'author' => $request->author,
            'description' => $request->description,
            'price' => $request->price,
            'discount' => $request->discount ?? 0,
            'charges' => $request->charges ?? 0,
            'currency' => $request->currency,
            'stock' => $request->stock ?? 0,
            'color' => $request->color,
            'size' => $request->size,
            'weight' => $request->weight,
            'brand_name' => $request->brand_name,
            'company_type' => $request->company_type,
            'company_available' => $request->company_available,
            'location' => $request->location,
            'delivery_method' => $request->delivery_method,
            'delivery_time' => $request->delivery_time,
            'delivery_price' => $request->delivery_price,
            'category_id' => $categoryId,
            'front_image' => $front,
            'back_image' => $back,
            'side_image' => $side,
            'pdf_file' => $pdf,
            'is_digital' => $request->is_digital ?? false,
            'sale_type' => $saleType,
            'downloadable' => $request->downloadable ?? 'no',
            'key_features' => $request->key_features ?? [],
            'specifications' => $request->specifications ?? [],
            'total_price' => $totalPrice,
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

            
            // Product Index

            public function index()
            {

            $products = Product::with('images')
            ->latest()
            ->paginate(20);

            return response()->json($products);

            }



            // Product Id
            public function show($id)
            {

            $product = Product::with('images')->findOrFail($id);

            return response()->json($product);

            }



            // User Product
            public function myProducts()
                {
                    return Product::where('user_id', auth()->id())
                        ->latest()
                        ->get();
                }




            // User Product Update
            public function update(Request $request, $id)
            {
                $product = Product::where('id', $id)
                    ->where('user_id', auth()->id())
                    ->firstOrFail();

                $product->update([
                    'title' => $request->title,
                    'price' => $request->price,
                    'stock' => $request->stock,
                    'sale_type' => $request->sale_type ?? 'physical',
                ]);

                return response()->json([
                    'message' => 'Product updated',
                    'product' => $product
                ]);
            }
        


            // User Product Delete
            public function destroy($id)
                {
                    $product = Product::where('id', $id)
                        ->where('user_id', auth()->id())
                        ->firstOrFail();

                    $product->delete();

                    return response()->json([
                        'message' => 'Product deleted'
                    ]);
                }



            public function productsByIds(Request $request)
                {
                    return Product::with('images')
                        ->whereIn('id',$request->ids)
                        ->get();
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
