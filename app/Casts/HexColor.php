<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Normalise une couleur hexadécimale à l'écriture ("FFAA00", "#ffaa00", " #FFAA00 "
 * deviennent tous "#ffaa00" en base) — évite d'avoir à gérer ces variantes partout où
 * la couleur est affichée.
 *
 * @implements CastsAttributes<?string, ?string>
 */
class HexColor implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return '#'.strtolower(ltrim(trim($value), '#'));
    }
}
