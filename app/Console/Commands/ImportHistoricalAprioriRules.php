<?php

namespace App\Console\Commands;

use App\Models\AprioriRule;
use App\Models\Product;
use Illuminate\Console\Command;

class ImportHistoricalAprioriRules extends Command
{
    protected $signature = 'apriori:import-historical';

    protected $description = 'Import Apriori rules from storage/app/apriori/rules.json into the database.';

    public function handle(): int
    {
        $path = storage_path('app/apriori/rules.json');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");
            $this->info('Run scripts/apriori/clean_and_mine.py first to generate the rules.');

            return self::FAILURE;
        }

        $json = file_get_contents($path);
        $rules = json_decode($json, true);

        if (! is_array($rules) || empty($rules)) {
            $this->error('No rules found in JSON file.');

            return self::FAILURE;
        }

        // Validate all product names exist in database and build name→id map.
        // The Python script (clean_and_mine.py) outputs product names; we convert to IDs
        // so rules survive product renames (H3 fix).
        $productMap = Product::pluck('id', 'name')->toArray();
        $skipped = 0;
        $validRules = [];

        foreach ($rules as $rule) {
            $allValid = true;

            foreach ([...$rule['antecedent'], ...$rule['consequent']] as $name) {
                if (! isset($productMap[$name])) {
                    $this->warn("Skipping rule — product not found in DB: {$name}");
                    $allValid = false;
                    break;
                }
            }

            if ($allValid) {
                $validRules[] = $rule;
            } else {
                $skipped++;
            }
        }

        if (empty($validRules)) {
            $this->error('No valid rules to import (all rules reference products not in database).');

            return self::FAILURE;
        }

        // Confirm before replacing existing rules
        $existingCount = AprioriRule::count();
        if ($existingCount > 0) {
            $this->warn("There are {$existingCount} existing rules in the database.");
            if (! $this->confirm('Truncate and replace with historical rules?')) {
                $this->info('Import cancelled.');

                return self::SUCCESS;
            }
        }

        AprioriRule::truncate();

        $inserts = [];
        foreach ($validRules as $rule) {
            // Convert product names to IDs for storage
            $antecedentIds = array_map(fn ($name) => $productMap[$name], $rule['antecedent']);
            $consequentIds = array_map(fn ($name) => $productMap[$name], $rule['consequent']);

            $inserts[] = [
                'antecedent' => json_encode(array_values($antecedentIds)),
                'consequent' => json_encode(array_values($consequentIds)),
                'support' => $rule['support'],
                'confidence' => $rule['confidence'],
                'lift' => $rule['lift'],
            ];
        }

        AprioriRule::insert($inserts);

        $count = count($validRules);
        $avgLift = collect($inserts)->avg('lift');
        $maxConf = collect($inserts)->max('confidence');

        $this->info("Imported {$count} rules into apriori_rules.");
        $this->info("  Skipped: {$skipped}");
        $this->info('  Avg Lift: '.number_format($avgLift, 4));
        $this->info('  Max Confidence: '.number_format($maxConf * 100, 1).'%');

        if ($skipped > 0) {
            $this->warn('Some rules were skipped. Check if the products exist in the database.');
        }

        return self::SUCCESS;
    }
}
