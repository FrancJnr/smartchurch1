<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\GivingType;
class GivingTypeSeeder extends Seeder {public function run():void {foreach(['Tithe','Offering','Thanksgiving','Development Fund','Welfare','Other'] as $name)GivingType::firstOrCreate(['name'=>$name]);}}
