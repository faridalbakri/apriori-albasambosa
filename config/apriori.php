<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Apriori Algorithm Parameters
    |--------------------------------------------------------------------------
    |
    | These parameters control the Market Basket Analysis algorithm.
    | - min_support: Minimum frequency threshold (0.02 = 2% of all transactions)
    | - min_confidence: Minimum confidence for association rules (0.6 = 60%)
    | - min_transactions: Do not run Apriori unless there are at least this
    |   many transactions in the dataset.
    | - batch_size: Number of transactions to process per batch.
    |
    */

    'min_support' => env('APRIORI_MIN_SUPPORT', 0.02),

    'min_confidence' => env('APRIORI_MIN_CONFIDENCE', 0.6),

    'min_transactions' => (int) env('APRIORI_MIN_TRANSACTIONS', 50),

];
