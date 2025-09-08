<?php
// run_tests.php

echo "PHP Event Loop Timer Benchmark - 50,000 Timers\n";
echo str_repeat("=", 60) . "\n";

// Set consistent random seed for fair comparison
srand(12345);

echo "\n1. Testing Your EventLoop...\n";
include 'hibla.php';

echo "\n" . str_repeat("-", 60) . "\n";

// Reset random seed for fairness
srand(12345);

echo "\n2. Testing ReactPHP...\n";
include 'react.php';

echo "\n" . str_repeat("-", 60) . "\n";

// Reset random seed for fairness
srand(12345);

echo "\n3. Testing Revolt...\n";
include 'revolt.php';

echo "\n" . str_repeat("=", 60) . "\n";
echo "All tests completed!\n";