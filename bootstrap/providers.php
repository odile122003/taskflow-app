<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    // TelescopeServiceProvider est enregistré conditionnellement dans
    // AppServiceProvider::register() — jamais ici. Telescope est installé
    // en --dev uniquement (Module 8) ; un enregistrement inconditionnel ici
    // ferait planter tout `composer install --no-dev` en production, la
    // classe parente Laravel\Telescope\TelescopeApplicationServiceProvider
    // n'existant plus une fois le package absent.
];
