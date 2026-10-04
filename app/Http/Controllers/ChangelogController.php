<?php

namespace App\Http\Controllers;

use App\Support\Changelog;
use Illuminate\View\View;

/** Journal des nouveautes, ouvert a tous les comptes. */
class ChangelogController extends Controller
{
    public function index(): View
    {
        return view('changelog.index', [
            'releases' => Changelog::releases(),
            'types' => Changelog::TYPES,
        ]);
    }
}
