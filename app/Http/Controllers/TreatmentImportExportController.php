<?php

namespace App\Http\Controllers;

use App\Exports\TreatmentsExport;
use App\Imports\TreatmentsImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class TreatmentImportExportController extends Controller
{
    public function export()
    {
        return Excel::download(new TreatmentsExport, 'treatments.csv');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        Excel::import(new TreatmentsImport, $request->file('file'));

        return back()->with('success', 'Treatments imported successfully.');
    }
}
