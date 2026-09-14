<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

/*
 * Rotta jolly delle pagine CMS.
 *
 * Va registrata per ultima e non deve catturare i prefissi riservati
 * alle altre sezioni del sito.
 */
$reserved = [
    'amministrazione', 'area-azienda', 'attivazione', 'abbonamento',
    'account', 'aziende', 'prodotti', 'piani',
    'contatti', 'carrello', 'pagamento', 'accedi', 'registrati', 'esci',
    'verifica', 'password', 'lingua', 'banner', 'traccia-ordine', 'up',
    'inserzionisti', 'area-inserzionista', 'webhook', 'tls',
];

Route::get('/{page:slug}', [PageController::class, 'show'])
    ->where('page', '^(?!'.implode('|', $reserved).')[A-Za-z0-9\-_]+$')
    ->name('pages.show');
