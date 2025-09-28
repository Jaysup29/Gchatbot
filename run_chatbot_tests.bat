@echo off
echo 🤖 Glacier Chatbot Testing Suite
echo =================================

REM Check if PHPUnit is available
if not exist "vendor\bin\phpunit.bat" (
    echo ❌ PHPUnit not found. Please run: composer install
    pause
    exit /b 1
)

REM Run all tests
echo 🧪 Running All Tests...
echo.

REM Test the PromptService functionality
echo 📝 Testing Prompt Service...
vendor\bin\phpunit tests\Unit\PromptServiceTest.php

REM Test the Prompt Model  
echo 🏷️  Testing Prompt Model...
vendor\bin\phpunit tests\Unit\PromptTest.php

REM Test the scoring algorithm
echo 🎯 Testing Scoring Algorithm...
vendor\bin\phpunit tests\Unit\PromptScoringAlgorithmTest.php

REM Test chatbot functionality
echo 💬 Testing Chatbot Functionality...
vendor\bin\phpunit tests\Feature\ChatbotFunctionalityTest.php

REM Test integration scenarios
echo 🔗 Testing Integration Scenarios...
vendor\bin\phpunit tests\Unit\ChatbotIntegrationTest.php

REM Run all tests together for summary
echo.
echo 📊 Running Complete Test Suite...
vendor\bin\phpunit --testdox

echo.
echo ✅ Testing Complete!
echo.
echo 📈 Test Coverage Areas:
echo   • Prompt matching and scoring algorithm
echo   • Chatbot conversation flow
echo   • Session management  
echo   • Response prioritization (Prompts ^> FAQs ^> AI)
echo   • Error handling and edge cases
echo   • Voice recognition integration
echo   • Chat history management
echo.
echo 🚀 Your chatbot is ready for high-quality responses!
echo.
pause
