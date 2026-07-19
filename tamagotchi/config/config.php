<?php
return array (
  'db' => 
  array (
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'tamagotchi',
    'user' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
  ),
  'game' => 
  array (
    'hunger_rate' => 8,
    'health_rate' => 3,
    'happiness_rate' => 5,
    'energy_rate' => 6,
    'feed_hunger' => -30,
    'play_happiness' => 20,
    'play_energy' => -15,
    'sleep_energy' => 40,
    'max_stat' => 100,
    'death_threshold' => 0,
    'evolve_stages' => 
    array (
      0 => 24,
      1 => 72,
      2 => 168,
    ),
  ),
  'learning' => 
  array (
    'points_per_correct' => 5,
    'knowledge_per_correct' => 2,
    'happiness_per_correct' => 3,
    'bonus' => 
    array (
      5 => 10,
      10 => 25,
      25 => 75,
    ),
    'perfect_multiplier' => 1.5,
    'secret' => 'change-moi-en-prod-svp',
  ),
  'google' => 
  array (
    'client_id' => '878381681024-6qnsarrvcrj935f56vln5uugc091gg7c.apps.googleusercontent.com',
  ),
  'app' => 
  array (
    'debug' => true,
    'timezone' => 'Europe/Paris',
  ),
);
