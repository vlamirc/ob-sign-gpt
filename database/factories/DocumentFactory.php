<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'title' => 'Contrato de teste', 'original_name' => 'teste.pdf', 'disk' => 'local',
            'path' => 'tests/original.pdf', 'sha256' => hash('sha256', '%PDF-1.4 teste'), 'size' => 14, 'status' => 'draft'];
    }
}
