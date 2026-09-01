<?php

namespace App\Providers;

use App\Interfaces\CategoriaInterface;
use App\Interfaces\PlanoSelecionadoInterface;
use App\Interfaces\PlanosInterface;
use App\Repository\CategoriaRepository;
use App\Repository\Plano\PlanoRepository;
use App\Repository\Plano\PlanoSelecionado\PlanoSelecionadoRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CategoriaInterface::class, CategoriaRepository::class);
        $this->app->bind(PlanosInterface::class, PlanoRepository::class);
        $this->app->bind(PlanoSelecionadoInterface::class, PlanoSelecionadoRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
