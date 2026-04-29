<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Parser le header Accept-Language (ex: "fr_FR,fr;q=0.9,en_US;q=0.8")
        // et extraire la première locale valide
        $acceptLanguage = $request->header('Accept-Language', 'fr');

        // Extraire la première partie avant la virgule et nettoyer
        $locale = explode(',', $acceptLanguage)[0];
        $locale = explode(';', $locale)[0];
        $locale = explode('_', $locale)[0]; // fr_FR -> fr
        $locale = strtolower(trim($locale));

        // Limiter aux locales supportées
        $supportedLocales = ['fr', 'en'];
        if (!in_array($locale, $supportedLocales)) {
            $locale = 'fr';
        }

        App::setLocale($locale);

        return $next($request);
    }
}