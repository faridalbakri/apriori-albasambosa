#!/usr/bin/env python3
"""
Apriori mining engine — called by PHP AprioriService.
Reads baskets.json, runs mlxtend, outputs rules.json.

Usage: python scripts/apriori/mine.py [--minsupport 0.02] [--minconfidence 0.6]
"""

import json
import sys
from pathlib import Path

import pandas as pd
from mlxtend.frequent_patterns import apriori, association_rules
from mlxtend.preprocessing import TransactionEncoder

PROJECT_ROOT = Path(__file__).resolve().parent.parent.parent
DATA_DIR = PROJECT_ROOT / 'storage' / 'app' / 'apriori'
BASKETS_PATH = DATA_DIR / 'baskets.json'
RULES_PATH = DATA_DIR / 'rules.json'


def main():
    min_support = float(sys.argv[1]) if len(sys.argv) > 1 else 0.02
    min_confidence = float(sys.argv[2]) if len(sys.argv) > 2 else 0.6

    if not BASKETS_PATH.exists():
        print(f"ERROR: {BASKETS_PATH} not found.", file=sys.stderr)
        sys.exit(1)

    baskets = json.load(open(BASKETS_PATH))

    # One-hot encode
    te = TransactionEncoder()
    te_ary = te.fit_transform(baskets)
    df = pd.DataFrame(te_ary, columns=te.columns_)

    # Mine
    frequent = apriori(df, min_support=min_support, use_colnames=True)

    if frequent.empty:
        rules = []
    else:
        rules_df = association_rules(frequent, metric='confidence', min_threshold=min_confidence)
        # Only rules with positive correlation
        rules_df = rules_df[rules_df['lift'] > 1.0]
        rules = []
        for _, row in rules_df.iterrows():
            rules.append({
                'antecedent': sorted([int(x) for x in row['antecedents']]),
                'consequent': sorted([int(x) for x in row['consequents']]),
                'support': round(float(row['support']), 6),
                'confidence': round(float(row['confidence']), 6),
                'lift': round(float(row['lift']), 6),
            })

    json.dump(rules, open(RULES_PATH, 'w'))
    print(f"Generated {len(rules)} rules.")


if __name__ == '__main__':
    main()
