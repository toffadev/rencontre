<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'images'])->get();
        $categories = Category::where('is_active', true)->get();

        return Inertia::render('Products', [
            'products' => $products,
            'categories' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'sku' => 'required|string|unique:products,sku',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'images' => 'required|array|min:1',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:8048'
        ]);

        try {
            // Créer le produit
            $validated['slug'] = Str::slug($validated['name']);
            $product = Product::create($validated);

            // Gérer les images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $imagePath = $image->store('products', 'public');
                    $product->images()->create([
                        'image_path' => Storage::url($imagePath),
                        'is_main' => $index === 0 // La première image est l'image principale
                    ]);
                }
            }

            return redirect()->back()->with('success', 'Produit créé avec succès');
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la création du produit:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'Une erreur est survenue lors de la création du produit: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'sku' => 'required|string|unique:products,sku,' . $product->id,
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:8048'
        ]);

        try {
            $validated['slug'] = Str::slug($validated['name']);
            $product->update($validated);

            // Gérer les nouvelles images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $imagePath = $image->store('products', 'public');
                    $product->images()->create([
                        'image_path' => Storage::url($imagePath),
                        'is_main' => false
                    ]);
                }
            }

            return redirect()->back()->with('success', 'Produit mis à jour avec succès');
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la mise à jour du produit:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'Une erreur est survenue lors de la mise à jour du produit: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Product $product)
    {
        try {
            // Supprimer les images associées
            foreach ($product->images as $image) {
                $imagePath = str_replace('/storage/', '', $image->image_path);
                Storage::disk('public')->delete($imagePath);
            }

            $product->delete();
            return redirect()->back()->with('success', 'Produit supprimé avec succès');
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la suppression du produit:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'Une erreur est survenue lors de la suppression du produit: ' . $e->getMessage()]);
        }
    }
}
