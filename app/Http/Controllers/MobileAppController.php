<?php

namespace App\Http\Controllers;

use App\Support\MobileApp;
use Illuminate\Support\Facades\Log;

/**
 * Mobile app Phase 2: the public "Get the app" page and the APK download.
 */
class MobileAppController extends Controller
{
    public function show()
    {
        return view('pages.get-the-app');
    }

    public function download()
    {
        if (! MobileApp::available()) {
            Log::warning('Mobile app download requested but the APK is not available.', [
                'path_set' => (string) config('levictas.mobile_app.file'),
                'version_set' => MobileApp::version() !== '',
            ]);

            // Back to the page, which explains it's not available right now.
            return redirect()->route('mobile-app');
        }

        return response()->download(MobileApp::path(), MobileApp::downloadName(), [
            'Content-Type' => 'application/vnd.android.package-archive',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }
}
