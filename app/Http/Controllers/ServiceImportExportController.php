<?php

namespace App\Http\Controllers;

use App\Exports\TreatmentsExport;
use App\Exports\PackagesExport;
use App\Imports\TreatmentsImport;
use App\Imports\PackagesImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ServiceImportExportController extends Controller
{
    public function exportTreatments()
    {
        return Excel::download(new TreatmentsExport, 'treatments.csv');
    }

    public function importTreatments(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        Excel::import(new TreatmentsImport, $request->file('file'));

        return back()->with('success', 'Treatments imported successfully.');
    }

    public function exportPackages()
    {
        return Excel::download(new PackagesExport, 'packages.csv');
    }

    public function importPackages(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        Excel::import(new PackagesImport, $request->file('file'));

        return back()->with('success', 'Packages imported successfully.');
    }
}
