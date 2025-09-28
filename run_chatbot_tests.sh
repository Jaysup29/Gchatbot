#!/bin/bash

# Chatbot Testing Script
# This script runs comprehensive tests for the chatbot functionality and prompting service

echo "🤖 Glacier Chatbot Testing Suite"
echo "================================="

# Check if PHPUnit is available
if ! command -v ./vendor/bin/phpunit &> /dev/null; then
    echo "❌ PHPUnit not found. Please run: composer install"
    exit 1
fi

# Run all tests with coverage if possible
echo "🧪 Running All Tests..."
echo ""

# Test the PromptService functionality
echo "📝 Testing Prompt Service..."
./vendor/bin/phpunit tests/Unit/PromptServiceTest.php --verbose

# Test the Prompt Model
echo "🏷️  Testing Prompt Model..."
./vendor/bin/phpunit tests/Unit/PromptTest.php --verbose

# Test the scoring algorithm
echo "🎯 Testing Scoring Algorithm..."
./vendor/bin/phpunit tests/Unit/PromptScoringAlgorithmTest.php --verbose

# Test chatbot functionality
echo "💬 Testing Chatbot Functionality..."
./vendor/bin/phpunit tests/Feature/ChatbotFunctionalityTest.php --verbose

# Test integration scenarios
echo "🔗 Testing Integration Scenarios..."
./vendor/bin/phpunit tests/Unit/ChatbotIntegrationTest.php --verbose

# Run all tests together for summary
echo ""
echo "📊 Running Complete Test Suite..."
./vendor/bin/phpunit --testdox

echo ""
echo "✅ Testing Complete!"
echo ""
echo "📈 Test Coverage Areas:"
echo "  • Prompt matching and scoring algorithm"
echo "  • Chatbot conversation flow"
echo "  • Session management"
echo "  • Response prioritization (Prompts > FAQs > AI)"
echo "  • Error handling and edge cases"
echo "  • Voice recognition integration"
echo "  • Chat history management"
echo ""
echo "🚀 Your chatbot is ready for high-quality responses!"
