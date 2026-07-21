<?php

use App\Livewire\Clips;
use App\Livewire\ClipsAnimados;
use App\Livewire\Definicoes;
use App\Livewire\Monitorizacao;
use App\Livewire\Noticias;
use App\Livewire\Painel;
use App\Livewire\Publicacoes\Carrosseis;
use App\Livewire\Publicacoes\Posts;
use App\Livewire\Publicacoes\Publicacoes;
use App\Livewire\Rascunhos;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Painel::class)->name('painel');
Route::livewire('/monitorizacao', Monitorizacao::class)->name('monitorizacao');
Route::livewire('/clips', Clips::class)->name('clips');
Route::livewire('/clips-animados', ClipsAnimados::class)->name('clips-animados');

Route::livewire('/publicacoes', Publicacoes::class)->name('publicacoes');
Route::livewire('/publicacoes/posts', Posts::class)->name('publicacoes.posts');
Route::livewire('/publicacoes/carrosseis', Carrosseis::class)->name('publicacoes.carrosseis');

Route::livewire('/rascunhos', Rascunhos::class)->name('rascunhos');
Route::livewire('/noticias', Noticias::class)->name('noticias');
Route::livewire('/definicoes', Definicoes::class)->name('definicoes');
