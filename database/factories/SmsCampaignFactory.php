<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmsCampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'created_by' => User::factory()->administrator(),
            'audience' => 'all', 'audience_label' => 'All active members',
            'message' => 'A test church announcement.', 'status' => 'draft',
        ];
    }
}
