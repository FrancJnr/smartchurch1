<?php

namespace Database\Factories;

use App\Models\SmsCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmsRecipientFactory extends Factory
{
    public function definition(): array
    {
        return ['sms_campaign_id' => SmsCampaign::factory(), 'member_name' => fake()->name(), 'phone' => '2547'.fake()->unique()->numerify('########'), 'status' => 'pending'];
    }
}
