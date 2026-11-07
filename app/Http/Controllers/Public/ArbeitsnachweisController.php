<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Arbeitsnachweis;
use Illuminate\Http\JsonResponse;

/** Aufgabe für den Arbeitsnachweis im Formular (App\Support\Arbeitsnachweis). */
class ArbeitsnachweisController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(Arbeitsnachweis::aufgabe())
            ->header('Cache-Control', 'no-store');
    }
}
