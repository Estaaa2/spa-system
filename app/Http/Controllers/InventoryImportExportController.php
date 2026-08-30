<?php

namespace App\Http\Controllers;

use App\Exports\ProductsExport;
use App\Imports\ProductsImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class InventoryImportExportController extends Controller
{
    public function exportProducts()
    {
        return Excel::download(new ProductsExport, 'products.csv');
    }

    public function importProducts(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        try {
            Excel::import(new ProductsImport, $request->file('file'));
        } catch (ValidationException $e) {
            $messages = [];

            foreach ($e->failures() as $failure) {
                $messages[] = 'Row ' . $failure->row() . ': ' . implode(' ', $failure->errors());
            }

            return back()->with('error', implode(' | ', $messages));
        }

        return back()->with('success', 'Products imported successfully.');
    }

    public function sampleCsv()
    {
        return Excel::download(new ProductsExport, 'inventory-products-sample.csv');
    }
}
