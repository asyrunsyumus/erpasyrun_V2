<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Satu alamat resmi: kunjungan GET ke www. atau domain sementara Hostinger dialihkan permanen ke asyrunsyumus.com.
 * Hanya di produksi, hanya GET/HEAD (form POST tidak dialihkan agar tidak kehilangan data),
 * dan endpoint pasca-deploy dikecualikan.
 */
class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = config('erp.canonical_host');

        if ($canonical && app()->isProduction()
            && $request->getHost() !== $canonical
            && $request->isMethodSafe()
            && ! $request->is('_deploy')) {
            return redirect()->away('https://'.$canonical.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
