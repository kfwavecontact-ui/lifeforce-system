<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class MaterialDownloadController extends Controller
{
    public function test()
    {
        $url = Storage::disk('s3')->temporaryUrl(
            'test.txt',
            now()->addMinutes(5)
        );

        return redirect($url);
    }
}
