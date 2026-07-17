<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Native PHP implementation of the Apriori algorithm for Market Basket Analysis.
 *
 * No external libraries — this is a from-scratch implementation of:
 * 1. Find frequent itemsets (items that appear together >= minSupport)
 * 2. Generate association rules (A → B) with minConfidence
 * 3. Calculate lift for each rule
 */
class AprioriService
{
    /**
     * Mine association rules from transaction baskets.
     *
     * @param  array<int, array<int>>  $baskets  Array of transactions, each an array of product IDs
     * @param  float  $minSupport  Minimum support threshold (0.0 – 1.0)
     * @param  float  $minConfidence  Minimum confidence threshold (0.0 – 1.0)
     * @return array<int, array{antecedent: array<int>, consequent: array<int>, support: float, confidence: float, lift: float}>
     *
     * @throws InvalidArgumentException If fewer than config('apriori.min_transactions') baskets
     */
    public function mine(array $baskets, ?float $minSupport = null, ?float $minConfidence = null): array
    {
        $minSupport ??= config('apriori.min_support', 0.02);
        $minConfidence ??= config('apriori.min_confidence', 0.6);
        $minTransactions = config('apriori.min_transactions', 50);

        if (count($baskets) < $minTransactions) {
            throw new InvalidArgumentException(
                "Need at least {$minTransactions} transactions to run Apriori. Got: ".count($baskets)
            );
        }

        // Export baskets to JSON for Python engine
        $dataDir = storage_path('app/apriori');
        if (! is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        file_put_contents($dataDir.'/baskets.json', json_encode($baskets));

        // Run Python mlxtend engine
        $script = base_path('scripts/apriori/mine.py');
        $command = escapeshellcmd("python {$script} {$minSupport} {$minConfidence} 2>&1");
        $output = shell_exec($command);

        if ($output === null) {
            throw new \RuntimeException('Python Apriori engine failed to execute.');
        }

        // Read results
        $rulesPath = $dataDir.'/rules.json';
        if (! file_exists($rulesPath)) {
            return [];
        }

        return json_decode(file_get_contents($rulesPath), true) ?? [];
    }

    /**
     * Find all frequent itemsets using the Apriori algorithm.
     *
     * Uses iterative level-wise search: k-itemsets are used to
     * generate candidate (k+1)-itemsets.
     *
     * @param  array<int, array<int>>  $baskets
     * @return array<int, array{items: array<int>, support: float}> All frequent itemsets (size >= 1)
     */
    public function findFrequentItemsets(array $baskets, float $minSupport, int $totalTransactions): array
    {
        $minCount = ceil($minSupport * $totalTransactions);

        // Count single items (1-itemsets)
        $itemCounts = [];
        foreach ($baskets as $basket) {
            $seen = [];
            foreach ($basket as $item) {
                if (! isset($seen[$item])) {
                    $seen[$item] = true;
                    $itemCounts[$item] = ($itemCounts[$item] ?? 0) + 1;
                }
            }
        }

        // Collect frequent 1-itemsets
        $frequentItemsets = [];
        $prevFrequent = [];

        foreach ($itemCounts as $item => $count) {
            if ($count >= $minCount) {
                $support = $count / $totalTransactions;
                $frequentItemsets[] = [
                    'items' => [$item],
                    'support' => $support,
                ];
                $prevFrequent[] = [$item];
            }
        }

        if (empty($prevFrequent)) {
            return [];
        }

        // Generate k-itemsets from (k-1)-itemsets, k=2,3,... until no more
        $k = 2;
        while (true) {
            $candidates = $this->generateCandidates($prevFrequent, $k);

            if (empty($candidates)) {
                break;
            }

            // Count candidates in baskets
            $candidateCounts = [];
            foreach ($baskets as $basket) {
                $basketSet = array_flip($basket);

                foreach ($candidates as $candidate) {
                    $allPresent = true;
                    foreach ($candidate as $item) {
                        if (! isset($basketSet[$item])) {
                            $allPresent = false;
                            break;
                        }
                    }
                    if ($allPresent) {
                        $key = implode(',', $candidate);
                        $candidateCounts[$key] = ($candidateCounts[$key] ?? 0) + 1;
                    }
                }
            }

            // Filter frequent k-itemsets
            $prevFrequent = [];
            foreach ($candidateCounts as $key => $count) {
                if ($count >= $minCount) {
                    $items = array_map('intval', explode(',', $key));
                    $support = $count / $totalTransactions;
                    $frequentItemsets[] = [
                        'items' => $items,
                        'support' => $support,
                    ];
                    $prevFrequent[] = $items;
                }
            }

            if (empty($prevFrequent)) {
                break;
            }
            $k++;
        }

        return $frequentItemsets;
    }

    /**
     * Generate candidate k-itemsets from frequent (k-1)-itemsets.
     *
     * @param  array<int, array<int>>  $prevFrequent  Array of sorted frequent (k-1)-itemsets
     * @return array<int, array<int>> Candidate k-itemsets (each sorted)
     */
    private function generateCandidates(array $prevFrequent, int $k): array
    {
        $n = count($prevFrequent);
        $candidates = [];
        $seen = [];

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $prevFrequent[$i];
                $b = $prevFrequent[$j];

                // If first (k-2) items match, merge to form candidate
                $prefixMatch = true;
                for ($idx = 0; $idx < $k - 2; $idx++) {
                    if ($a[$idx] !== $b[$idx]) {
                        $prefixMatch = false;
                        break;
                    }
                }

                if ($prefixMatch && $a[$k - 2] < $b[$k - 2]) {
                    // Merge: take first (k-2) items + the two differing items
                    $candidate = array_merge(
                        array_slice($a, 0, $k - 2),
                        [$a[$k - 2], $b[$k - 2]]
                    );
                    sort($candidate, SORT_NUMERIC);

                    $key = implode(',', $candidate);
                    if (! isset($seen[$key])) {
                        $seen[$key] = true;
                        $candidates[] = $candidate;
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * Generate association rules from frequent itemsets.
     *
     * For each frequent itemset of size >= 2, generate all possible
     * antecedent → consequent splits and calculate confidence + lift.
     *
     * @param  array  $frequentItemsets  From findFrequentItemsets()
     * @param  array<int, array<int>>  $baskets
     * @return array<int, array{antecedent: array<int>, consequent: array<int>, support: float, confidence: float, lift: float}>
     */
    public function generateRules(
        array $frequentItemsets,
        array $baskets,
        float $minConfidence,
        int $totalTransactions
    ): array {
        // Build lookup: item key → support
        $supportMap = [];
        foreach ($frequentItemsets as $fi) {
            sort($fi['items'], SORT_NUMERIC);
            $supportMap[implode(',', $fi['items'])] = $fi['support'];
        }

        // Build single-item support lookup
        $singleSupport = [];
        foreach ($frequentItemsets as $fi) {
            if (count($fi['items']) === 1) {
                $singleSupport[$fi['items'][0]] = $fi['support'];
            }
        }

        $rules = [];

        // Only process itemsets of size >= 2
        foreach ($frequentItemsets as $fi) {
            $items = $fi['items'];
            if (count($items) < 2) {
                continue;
            }

            $itemsetSupport = $fi['support'];

            // Generate all non-empty proper subsets as antecedents
            $subsets = $this->getSubsets($items);
            foreach ($subsets as $antecedent) {
                $consequent = array_values(array_diff($items, $antecedent));

                if (empty($consequent)) {
                    continue;
                }

                sort($antecedent, SORT_NUMERIC);
                sort($consequent, SORT_NUMERIC);

                $antKey = implode(',', $antecedent);
                $antSupport = $supportMap[$antKey]
                    ?? $singleSupport[$antecedent[0]]
                    ?? $this->countSupport($antecedent, $baskets, $totalTransactions);

                if ($antSupport <= 0) {
                    continue;
                }

                $confidence = $itemsetSupport / $antSupport;

                if ($confidence < $minConfidence) {
                    continue;
                }

                // Lift = confidence / P(consequent)
                $consKey = implode(',', $consequent);
                $consSupport = $supportMap[$consKey]
                    ?? $singleSupport[$consequent[0]]
                    ?? $this->countSupport($consequent, $baskets, $totalTransactions);

                $lift = $consSupport > 0 ? $confidence / $consSupport : 0;

                // Only keep rules with Lift > 1 (positive correlation)
                if ($lift <= 1.0) {
                    continue;
                }

                $rules[] = [
                    'antecedent' => $antecedent,
                    'consequent' => $consequent,
                    'support' => round($itemsetSupport, 6),
                    'confidence' => round($confidence, 6),
                    'lift' => round($lift, 6),
                ];
            }
        }

        // Sort by lift descending
        usort($rules, fn ($a, $b) => $b['lift'] <=> $a['lift']);

        return $rules;
    }

    /**
     * Generate all non-empty proper subsets of an array.
     *
     * @param  array<int>  $items
     * @return array<int, array<int>>
     */
    private function getSubsets(array $items): array
    {
        $n = count($items);
        $subsets = [];
        $max = (1 << $n) - 1; // All 1s = full set, excluded

        for ($mask = 1; $mask < $max; $mask++) {
            $subset = [];
            for ($i = 0; $i < $n; $i++) {
                if ($mask & (1 << $i)) {
                    $subset[] = $items[$i];
                }
            }
            $subsets[] = $subset;
        }

        return $subsets;
    }

    /**
     * Count support for an itemset by scanning all baskets.
     * Fallback when itemset not in pre-computed map.
     *
     * @param  array<int>  $items
     * @param  array<int, array<int>>  $baskets
     */
    private function countSupport(array $items, array $baskets, int $totalTransactions): float
    {
        $count = 0;
        foreach ($baskets as $basket) {
            $basketSet = array_flip($basket);
            $allPresent = true;
            foreach ($items as $item) {
                if (! isset($basketSet[$item])) {
                    $allPresent = false;
                    break;
                }
            }
            if ($allPresent) {
                $count++;
            }
        }

        return $count / $totalTransactions;
    }
}
