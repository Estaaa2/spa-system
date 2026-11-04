<?php

namespace App\Http\Controllers;

use App\Exports\ProductsExport;
use App\Exports\ProductsSampleExport;
use App\Imports\ProductsImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class InventoryImportExportController extends Controller
{
    public function exportProducts()
    {
        $user = auth()->user();

        if (!$user->currentBranchId()) {
            return back()->with('error', 'Please select a branch first.');
        }

        return Excel::download(
            new ProductsExport(),
            'inventory-products.csv'
        );
    }

    public function importProducts(Request $request)
    {
        $user = $request->user();

        if (!$user->currentBranchId()) {
            return back()->with('error', 'Please select a branch first.');
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        try {
            Excel::import(
                new ProductsImport(),
                $request->file('file')
            );
        } catch (ValidationException $e) {
            $messages = [];

            foreach ($e->failures() as $failure) {
                $messages[] = 'Row ' . $failure->row() . ': ' . implode(' ', $failure->errors());
            }

            return back()->with('error', implode(' | ', $messages));
        }

        return back()->with(
            'success',
            'Products imported successfully. Existing stock quantities were not changed.'
        );
    }

    public function sampleCsv()
    {
        return Excel::download(
            new ProductsSampleExport(),
            'inventory-products-sample.csv'
        );
    }
}