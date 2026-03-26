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
//
            $request->validate([
                'title' => 'required|string',
                'price' => 'required|numeric',
                'currency' => 'required|string',
                'category_id' => 'nullable|exists:categories,id',
                'new_parent' => 'nullable|string',
                'new_subcategory' => 'nullable|string',
                'parent_id' => 'nullable|exists:categories,id',
                'front_image' => 'nullable|image|max:2048',
                'back_image' => 'nullable|image|max:2048',
                'side_image' => 'nullable|image|max:2048',
                'images.*' => 'nullable|image|max:2048',
                'delivery_price' => 'nullable|numeric',
                'brand_name' => 'nullable|string',
                'company_type' => 'nullable|string',
                'sale_type' => 'nullable|string|in:online,physical',
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

    $user = auth()->user();
    \Log::info('Authenticated user:', ['user' => $user]);

    if (!$user) {
        return response()->json([
            'message' => 'User not authenticated'
        ], 401);
    }

    $categoryId = null;

    // handle new parent/subcategory safely...
    if ($request->new_parent) {
        $parent = Category::firstOrCreate(
            ['slug' => Str::slug($request->new_parent)],
            ['name' => $request->new_parent]
        );
        $parentId = $parent->id;
    } else {
        $parentId = $request->parent_id ?? null;
    }

    if ($request->new_subcategory) {
        if (!$parentId) {
            return response()->json([
                'message' => 'You must select or create a parent category for the new subcategory'
            ], 422);
        }

        $subcategory = Category::firstOrCreate(
            ['slug' => Str::slug($request->new_subcategory)],
            ['name' => $request->new_subcategory, 'parent_id' => $parentId]
        );

        $categoryId = $subcategory->id;
    } elseif ($request->category_id) {
        $categoryId = $request->category_id;
    } else {
        return response()->json([
            'message' => 'You must select a category or create a new subcategory'
        ], 422);
    }


            $saleType = $request->sale_type ?? 'physical';


            $product = Product::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'author' => $request->author,
            'description' => $request->description,
            'price' => $request->price,
            'discount' => $request->discount ?? 0,
            'charges' => $request->charges ?? 0,
            'currency' => $request->currency,
            'stock' => $request->stock ?? 0,
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
            'parent_id' => $parentId,
            'new_subcategory' => $request->new_subcategory,
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
    return Product::with('images') // <-- eager load images
        ->where('user_id', auth()->id())
        ->latest()
        ->get();
}




            // User Product Update
          public function update(Request $request, $id)
            {
                $product = Product::where('id', $id)
                    ->where('user_id', auth()->id())
                    ->firstOrFail();

                $data = $request->only([
                    'title','author','description','price','stock','discount',
                    'currency','location','delivery_time','delivery_method',
                    'delivery_price','downloadable','sale_type',
                    'company_type','company_available','brand_name',
                ]);

                $data = array_filter($data, fn($v) => !is_null($v));

                // JSON
                if ($request->has('key_features')) {
                    $data['key_features'] = json_decode($request->key_features, true);
                }

                if ($request->has('specifications')) {
                    $data['specifications'] = json_decode($request->specifications, true);
                }

                // Single files
                foreach (['front_image','back_image','side_image','pdf_file'] as $fileField) {
                    if ($request->hasFile($fileField)) {
                        $data[$fileField] = $request->file($fileField)->store('products','public');
                    }
                }

                $product->update($data);

                // ✅ DEBUG (VERY IMPORTANT)
                \Log::info('FILES RECEIVED:', $request->allFiles());

                // ✅ REPLACE IMAGES
                if ($request->hasFile('images')) {

                    // delete old
                    foreach ($product->images as $old) {
                        \Storage::disk('public')->delete($old->image_path);
                    }

                    $product->images()->delete();

                    // save new
                    foreach ($request->file('images') as $img) {
                        $path = $img->store('product_gallery', 'public');

                        $product->images()->create([
                            'image_path' => $path,
                            'position' => 'gallery'
                        ]);
                    }
                }

                return response()->json([
                    'message' => 'Updated successfully',
                    'product' => $product->load('images'),
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


    public function searchProduct(Request $request)
    {
    $q = $request->query('q');

    $products = Product::where('title', 'like', "%{$q}%")
        ->take(10)
        ->get()
        ->map(function ($product) {

            return [
                'id' => $product->id,
                'title' => $product->title,

                // ✅ FIXED IMAGE
                'image' => $product->front_image
                    ? asset('storage/' . $product->front_image)
                    : asset('placeholder.png'),
            ];
        });

    $categories = Category::where('name', 'like', "%{$q}%")
        ->take(10)
        ->get(['id', 'name', 'parent_id'])
        ->map(function ($cat) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'type' => $cat->parent_id ? 'child' : 'parent',
            ];
        });

    return response()->json([
        'products' => $products,
        'categories' => $categories,
    ]);
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
