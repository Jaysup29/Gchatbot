<?php

// Quick diagnostic script to check testing setup
echo "🔍 Gchatbot Testing Diagnostic\n";
echo "============================\n\n";

// Check PHP version
echo "✅ PHP Version: " . PHP_VERSION . "\n";

// Check if we're in the right directory
$currentDir = getcwd();
echo "📁 Current Directory: " . $currentDir . "\n";

// Check if vendor directory exists
if (file_exists('vendor/autoload.php')) {
    echo "✅ Composer Dependencies: Installed\n";
    require_once 'vendor/autoload.php';
} else {
    echo "❌ Composer Dependencies: Missing (run: composer install)\n";
    exit(1);
}

// Check if PHPUnit is available
if (file_exists('vendor/bin/phpunit') || file_exists('vendor/bin/phpunit.bat')) {
    echo "✅ PHPUnit: Available\n";
} else {
    echo "❌ PHPUnit: Missing\n";
}

// Check Laravel configuration
if (file_exists('.env')) {
    echo "✅ .env file: Found\n";
} else {
    echo "⚠️ .env file: Missing\n";
}

if (file_exists('.env.testing')) {
    echo "✅ .env.testing file: Found\n";
} else {
    echo "⚠️ .env.testing file: Missing\n";
}

// Check if app key is set
if (function_exists('env')) {
    try {
        $app = new \Illuminate\Foundation\Application(realpath(__DIR__));
        echo "✅ Laravel Application: Can be instantiated\n";
    } catch (Exception $e) {
        echo "❌ Laravel Application: Error - " . $e->getMessage() . "\n";
    }
}

// Check test directories
$testDirs = ['tests/Unit', 'tests/Feature', 'database/factories'];
foreach ($testDirs as $dir) {
    if (is_dir($dir)) {
        echo "✅ Directory {$dir}: Exists\n";
    } else {
        echo "❌ Directory {$dir}: Missing\n";
    }
}

// Check specific test files
$testFiles = [
    'tests/Unit/PromptServiceTest.php',
    'tests/Feature/ChatbotFunctionalityTest.php',
    'database/factories/PromptFactory.php'
];

foreach ($testFiles as $file) {
    if (file_exists($file)) {
        echo "✅ File {$file}: Exists\n";
    } else {
        echo "❌ File {$file}: Missing\n";
    }
}

// Check database configuration
if (file_exists('database/database.sqlite')) {
    echo "✅ SQLite Database: Found\n";
} else {
    echo "ℹ️ SQLite Database: Will be created in memory for tests\n";
}

echo "\n🎯 Ready to run tests!\n";
echo "Run: vendor\\bin\\phpunit --verbose\n";
