<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Signer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Signer> */
class SignerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['document_id' => Document::factory(), 'name' => fake()->name(), 'email' => fake()->unique()->safeEmail()];
    }
}
